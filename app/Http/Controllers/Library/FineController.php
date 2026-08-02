<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\StoreFinePaymentRequest;
use App\Models\FinePayment;
use App\Models\LoanItem;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class FineController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');
        $paidExpression = $this->paidExpression();

        $loanItems = LoanItem::query()
            ->select('loan_items.*')
            ->selectRaw("{$paidExpression} AS paid_amount")
            ->with([
                'loan:id,loan_code,member_id,loan_date,status',
                'loan.member:id,member_code,member_name,member_type,identity_number',
                'asset:id,item_id,asset_code,barcode,current_shelf_id',
                'asset.item:id,item_code,item_name,item_type',
                'asset.shelf:id,shelf_code,shelf_name',
            ])
            ->where('loan_items.fine_amount', '>', 0)
            ->where('loan_items.return_status', '<>', 'borrowed')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->whereHas('loan', function ($loanQuery) use ($search): void {
                            $loanQuery->where('loan_code', 'like', "%{$search}%")
                                ->orWhereHas('member', function ($memberQuery) use ($search): void {
                                    $memberQuery->where('member_code', 'like', "%{$search}%")
                                        ->orWhere('member_name', 'like', "%{$search}%")
                                        ->orWhere('identity_number', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('asset', function ($assetQuery) use ($search): void {
                            $assetQuery->where('asset_code', 'like', "%{$search}%")
                                ->orWhere('barcode', 'like', "%{$search}%")
                                ->orWhereHas('item', function ($itemQuery) use ($search): void {
                                    $itemQuery->where('item_code', 'like', "%{$search}%")
                                        ->orWhere('item_name', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when($status === 'unpaid', fn ($query) => $query->whereRaw("{$paidExpression} = 0"))
            ->when($status === 'partial', fn ($query) => $query->whereRaw("{$paidExpression} > 0 AND {$paidExpression} < loan_items.fine_amount"))
            ->when($status === 'paid', fn ($query) => $query->whereRaw("{$paidExpression} >= loan_items.fine_amount"))
            ->orderByRaw("GREATEST(loan_items.fine_amount - {$paidExpression}, 0) DESC")
            ->orderByDesc('loan_items.returned_at')
            ->paginate(15)
            ->withQueryString();

        $totalFines = (float) LoanItem::query()
            ->where('fine_amount', '>', 0)
            ->where('return_status', '<>', 'borrowed')
            ->sum('fine_amount');

        $totalPaid = (float) FinePayment::query()->sum('amount');

        $summary = [
            'total_fines' => $totalFines,
            'total_paid' => $totalPaid,
            'outstanding' => max($totalFines - $totalPaid, 0),
            'outstanding_items' => LoanItem::query()
                ->where('fine_amount', '>', 0)
                ->where('return_status', '<>', 'borrowed')
                ->whereRaw("{$paidExpression} < loan_items.fine_amount")
                ->count(),
            'paid_today' => (float) FinePayment::query()
                ->whereDate('payment_date', today())
                ->sum('amount'),
        ];

        return view('library.fines.index', compact('loanItems', 'summary'));
    }

    public function show(LoanItem $loanItem): View
    {
        $this->ensureFineIsFinal($loanItem);

        $loanItem->load([
            'loan.member:id,member_code,member_name,member_type,identity_number,department,phone,email',
            'loan.processor:id,full_name',
            'asset.item:id,item_code,item_name,item_type',
            'asset.item.bookDetail:item_id,isbn_10,isbn_13,call_number',
            'asset.shelf:id,shelf_code,shelf_name',
            'finePayments' => fn ($query) => $query
                ->with('receiver:id,full_name')
                ->orderByDesc('payment_date')
                ->orderByDesc('id'),
        ]);

        $paidAmount = $loanItem->paidFineAmount();
        $remainingAmount = max((float) $loanItem->fine_amount - $paidAmount, 0);

        return view('library.fines.show', compact('loanItem', 'paidAmount', 'remainingAmount'));
    }

    public function store(StoreFinePaymentRequest $request, LoanItem $loanItem): RedirectResponse
    {
        $validated = $request->validated();
        $amount = round((float) $validated['amount'], 2);

        try {
            $result = DB::transaction(function () use ($request, $validated, $loanItem, $amount): array {
                $lockedItem = LoanItem::query()
                    ->with(['loan.member', 'asset.item'])
                    ->lockForUpdate()
                    ->findOrFail($loanItem->id);

                $this->ensureFineIsFinal($lockedItem);

                $paidAmount = (float) FinePayment::query()
                    ->where('loan_item_id', $lockedItem->id)
                    ->lockForUpdate()
                    ->get()
                    ->sum('amount');

                $remainingAmount = max((float) $lockedItem->fine_amount - $paidAmount, 0);

                if ($remainingAmount <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'Denda ini sudah lunas dan tidak dapat menerima pembayaran tambahan.',
                    ]);
                }

                if ($amount > $remainingAmount + 0.001) {
                    throw ValidationException::withMessages([
                        'amount' => 'Nominal pembayaran melebihi sisa tagihan Rp'.number_format($remainingAmount, 0, ',', '.').'.',
                    ]);
                }

                $payment = FinePayment::query()->create([
                    'payment_code' => $this->generatePaymentCode(),
                    'loan_item_id' => $lockedItem->id,
                    'amount' => $amount,
                    'payment_date' => now(),
                    'payment_method' => $validated['payment_method'],
                    'received_by' => $request->user()?->id,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $remainingAfterPayment = max($remainingAmount - $amount, 0);

                DB::table('audit_logs')->insert([
                    'user_id' => $request->user()?->id,
                    'action' => 'insert',
                    'module_name' => 'library_fines',
                    'table_name' => 'fine_payments',
                    'record_id' => $payment->id,
                    'old_data' => null,
                    'new_data' => json_encode([
                        'payment_code' => $payment->payment_code,
                        'loan_item_id' => $lockedItem->id,
                        'amount' => $amount,
                        'payment_method' => $payment->payment_method,
                        'remaining_amount' => $remainingAfterPayment,
                    ], JSON_THROW_ON_ERROR),
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                    'created_at' => now(),
                ]);

                return [
                    'payment_id' => $payment->id,
                    'payment_code' => $payment->payment_code,
                    'remaining_amount' => $remainingAfterPayment,
                ];
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['amount' => 'Pembayaran gagal disimpan karena data berubah atau kode pembayaran berbenturan. Coba kembali.']);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['amount' => 'Pembayaran belum dapat disimpan. Periksa data lalu coba kembali.']);
        }

        $message = 'Pembayaran '.$result['payment_code'].' berhasil dicatat.';
        $message .= $result['remaining_amount'] > 0
            ? ' Sisa tagihan Rp'.number_format($result['remaining_amount'], 0, ',', '.').'.'
            : ' Denda telah lunas.';

        return redirect()
            ->route('library.fines.show', $loanItem)
            ->with('success', $message);
    }

    public function receipt(FinePayment $finePayment): View
    {
        $finePayment->load([
            'receiver:id,full_name',
            'loanItem.loan.member:id,member_code,member_name,member_type,identity_number',
            'loanItem.asset.item:id,item_code,item_name,item_type',
            'loanItem.asset:id,item_id,asset_code,barcode',
        ]);

        return view('library.fines.receipt', compact('finePayment'));
    }

    private function ensureFineIsFinal(LoanItem $loanItem): void
    {
        if ($loanItem->return_status === 'borrowed' || $loanItem->returned_at === null) {
            abort(404);
        }

        if ((float) $loanItem->fine_amount <= 0) {
            abort(404);
        }
    }

    private function paidExpression(): string
    {
        return '(SELECT COALESCE(SUM(fp.amount), 0) FROM fine_payments fp WHERE fp.loan_item_id = loan_items.id)';
    }

    private function generatePaymentCode(): string
    {
        do {
            $code = 'DND-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5));
        } while (FinePayment::query()->where('payment_code', $code)->exists());

        return $code;
    }
}
