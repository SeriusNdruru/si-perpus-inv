<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\StoreReturnRequest;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Services\Library\ReservationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ReturnController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService)
    {
    }

    public function index(Request $request): View
    {
        $this->synchronizeLoanStatuses();

        $search = trim((string) $request->query('search'));
        $timing = (string) $request->query('timing');

        $finePerDay = $this->finePerDay();

        $loanItems = LoanItem::query()
            ->with([
                'loan:id,loan_code,member_id,loan_date,status',
                'loan.member:id,member_code,member_name,member_type',
                'asset:id,item_id,asset_code,barcode,current_shelf_id,condition_status,asset_status',
                'asset.item:id,item_code,item_name,item_type',
                'asset.item.bookDetail:item_id,isbn_10,isbn_13,call_number',
                'asset.shelf:id,shelf_code,shelf_name',
            ])
            ->where('return_status', 'borrowed')
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
            ->when($timing === 'overdue', fn ($query) => $query->whereDate('due_date', '<', today()))
            ->when($timing === 'due_today', fn ($query) => $query->whereDate('due_date', today()))
            ->when($timing === 'on_time', fn ($query) => $query->whereDate('due_date', '>', today()))
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $lateDays = (int) DB::table('loan_items')
            ->where('return_status', 'borrowed')
            ->selectRaw('COALESCE(SUM(GREATEST(DATEDIFF(CURDATE(), due_date), 0)), 0) AS total_late_days')
            ->value('total_late_days');

        $summary = [
            'borrowed' => LoanItem::query()->where('return_status', 'borrowed')->count(),
            'overdue' => LoanItem::query()
                ->where('return_status', 'borrowed')
                ->whereDate('due_date', '<', today())
                ->count(),
            'due_today' => LoanItem::query()
                ->where('return_status', 'borrowed')
                ->whereDate('due_date', today())
                ->count(),
            'returned_today' => LoanItem::query()
                ->where('return_status', '<>', 'borrowed')
                ->whereDate('returned_at', today())
                ->count(),
            'estimated_fine' => $lateDays * $finePerDay,
        ];

        return view('library.returns.index', compact('loanItems', 'summary', 'finePerDay'));
    }

    public function edit(LoanItem $loanItem): View
    {
        if ($loanItem->return_status !== 'borrowed') {
            abort(404);
        }

        $loanItem->load([
            'loan.member:id,member_code,member_name,member_type,identity_number,department,phone,email',
            'loan.processor:id,full_name',
            'asset.item:id,item_code,item_name,item_type',
            'asset.item.bookDetail:item_id,isbn_10,isbn_13,call_number',
            'asset.shelf:id,shelf_code,shelf_name',
        ]);

        $finePerDay = $this->finePerDay();
        $daysLate = $this->calculateDaysLate($loanItem->due_date, today());
        $fineAmount = $daysLate * $finePerDay;

        return view('library.returns.edit', compact(
            'loanItem',
            'finePerDay',
            'daysLate',
            'fineAmount'
        ));
    }

    public function update(StoreReturnRequest $request, LoanItem $loanItem): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $result = DB::transaction(function () use ($request, $validated, $loanItem): array {
                $lockedItem = LoanItem::query()
                    ->with(['loan', 'asset.item'])
                    ->lockForUpdate()
                    ->findOrFail($loanItem->id);

                if ($lockedItem->return_status !== 'borrowed') {
                    throw ValidationException::withMessages([
                        'return_status' => 'Buku ini sudah diproses pengembaliannya oleh petugas lain.',
                    ]);
                }

                if ($lockedItem->asset?->asset_status !== 'borrowed') {
                    throw ValidationException::withMessages([
                        'return_status' => 'Status eksemplar tidak lagi tercatat sebagai dipinjam.',
                    ]);
                }

                $returnStatus = (string) $validated['return_status'];
                $conditionIn = match ($returnStatus) {
                    'damaged' => 'damaged',
                    'lost' => 'lost',
                    default => (string) $validated['condition_in'],
                };

                $returnedAt = now();
                $daysLate = $this->calculateDaysLate($lockedItem->due_date, $returnedAt);
                $fineAmount = $daysLate * $this->finePerDay();

                $oldData = [
                    'return_status' => $lockedItem->return_status,
                    'asset_status' => $lockedItem->asset?->asset_status,
                    'fine_amount' => (float) $lockedItem->fine_amount,
                ];

                $lockedItem->update([
                    'returned_at' => $returnedAt,
                    'condition_in' => $conditionIn,
                    'return_status' => $returnStatus,
                    'fine_amount' => $fineAmount,
                    'return_notes' => $validated['return_notes'] ?? null,
                    'returned_by' => $request->user()?->id,
                ]);

                $this->refreshLoanStatus($lockedItem->loan_id);

                DB::table('audit_logs')->insert([
                    'user_id' => $request->user()?->id,
                    'action' => 'update',
                    'module_name' => 'library_returns',
                    'table_name' => 'loan_items',
                    'record_id' => $lockedItem->id,
                    'old_data' => json_encode($oldData, JSON_THROW_ON_ERROR),
                    'new_data' => json_encode([
                        'return_status' => $returnStatus,
                        'condition_in' => $conditionIn,
                        'days_late' => $daysLate,
                        'fine_amount' => $fineAmount,
                        'returned_at' => $returnedAt->toDateTimeString(),
                    ], JSON_THROW_ON_ERROR),
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                    'created_at' => now(),
                ]);

                return [
                    'loan_id' => $lockedItem->loan_id,
                    'item_id' => (int) $lockedItem->asset->item_id,
                    'days_late' => $daysLate,
                    'fine_amount' => $fineAmount,
                    'return_status' => $returnStatus,
                ];
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            return back()
                ->withInput()
                ->withErrors(['return_status' => $this->databaseErrorMessage($exception)]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['return_status' => 'Pengembalian belum dapat disimpan. Periksa kembali data lalu coba lagi.']);
        }

        try {
            $this->reservationService->synchronizeItem((int) $result['item_id']);
        } catch (Throwable $exception) {
            report($exception);
        }

        $statusLabel = match ($result['return_status']) {
            'damaged' => 'rusak',
            'lost' => 'hilang',
            default => 'dikembalikan',
        };

        $message = "Buku berhasil diproses sebagai {$statusLabel}.";

        if ($result['days_late'] > 0) {
            $message .= sprintf(
                ' Terlambat %d hari dengan denda final Rp%s.',
                $result['days_late'],
                number_format((float) $result['fine_amount'], 0, ',', '.')
            );
        } else {
            $message .= ' Tidak ada denda keterlambatan.';
        }

        return redirect()
            ->route('library.loans.show', $result['loan_id'])
            ->with('success', $message);
    }

    private function finePerDay(): float
    {
        $value = DB::table('system_settings')
            ->where('setting_key', 'library.fine_per_day')
            ->value('setting_value');

        return max((float) ($value ?? 1000), 0);
    }

    private function calculateDaysLate(Carbon|string|null $dueDate, Carbon $returnDate): int
    {
        if ($dueDate === null) {
            return 0;
        }

        $due = $dueDate instanceof Carbon
            ? $dueDate->copy()->startOfDay()
            : Carbon::parse($dueDate)->startOfDay();

        $returned = $returnDate->copy()->startOfDay();

        return $due->isBefore($returned)
            ? (int) $due->diffInDays($returned)
            : 0;
    }

    private function synchronizeLoanStatuses(): void
    {
        Loan::query()
            ->where('status', 'active')
            ->whereHas('items', function ($query): void {
                $query->where('return_status', 'borrowed')
                    ->whereDate('due_date', '<', today());
            })
            ->update(['status' => 'overdue']);
    }

    private function refreshLoanStatus(int $loanId): void
    {
        $borrowedItems = LoanItem::query()
            ->where('loan_id', $loanId)
            ->where('return_status', 'borrowed');

        if (! $borrowedItems->exists()) {
            Loan::query()->whereKey($loanId)->update(['status' => 'completed']);

            return;
        }

        $hasOverdue = (clone $borrowedItems)
            ->whereDate('due_date', '<', today())
            ->exists();

        Loan::query()->whereKey($loanId)->update([
            'status' => $hasOverdue ? 'overdue' : 'active',
        ]);
    }

    private function databaseErrorMessage(QueryException $exception): string
    {
        $message = (string) ($exception->getPrevious()?->getMessage() ?: $exception->getMessage());

        foreach ([
            'Buku ini sudah diproses pengembaliannya.',
            'Buku belum dapat tersedia sebelum rak ditentukan.',
            'Buku belum dapat tersedia sebelum data katalog dilengkapi.',
            'Rak tidak ditemukan atau tidak aktif.',
        ] as $knownMessage) {
            if (str_contains($message, $knownMessage)) {
                return $knownMessage;
            }
        }

        return 'Pengembalian gagal diproses karena data berubah atau tidak lagi memenuhi syarat. Muat ulang halaman lalu coba kembali.';
    }
}
