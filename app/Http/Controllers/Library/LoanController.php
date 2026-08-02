<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\StoreLoanRequest;
use App\Models\Asset;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Member;
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

class LoanController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService)
    {
    }

    public function index(Request $request): View
    {
        $this->synchronizeLoanStatuses();

        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');
        $dateFrom = (string) $request->query('date_from');
        $dateTo = (string) $request->query('date_to');

        $loans = Loan::query()
            ->with([
                'member:id,member_code,member_name,member_type',
                'processor:id,full_name',
            ])
            ->select('loans.*')
            ->selectSub(function ($query): void {
                $query->from('loan_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('loan_items.loan_id', 'loans.id');
            }, 'items_count')
            ->selectSub(function ($query): void {
                $query->from('loan_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('loan_items.loan_id', 'loans.id')
                    ->where('return_status', 'borrowed');
            }, 'borrowed_items_count')
            ->selectSub(function ($query): void {
                $query->from('loan_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('loan_items.loan_id', 'loans.id')
                    ->where('return_status', 'borrowed')
                    ->whereDate('due_date', '<', today());
            }, 'overdue_items_count')
            ->selectSub(function ($query): void {
                $query->from('loan_items')
                    ->selectRaw('MIN(due_date)')
                    ->whereColumn('loan_items.loan_id', 'loans.id')
                    ->where('return_status', 'borrowed');
            }, 'nearest_due_date')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('loans.loan_code', 'like', "%{$search}%")
                        ->orWhereHas('member', function ($memberQuery) use ($search): void {
                            $memberQuery
                                ->where('member_code', 'like', "%{$search}%")
                                ->orWhere('member_name', 'like', "%{$search}%")
                                ->orWhere('identity_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when(
                in_array($status, ['active', 'completed', 'overdue', 'cancelled'], true),
                fn ($query) => $query->where('loans.status', $status)
            )
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('loans.loan_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('loans.loan_date', '<=', $dateTo))
            ->orderByDesc('loans.loan_date')
            ->paginate(12)
            ->withQueryString();

        $summary = [
            'today' => Loan::query()->whereDate('loan_date', today())->count(),
            'active' => Loan::query()->where('status', 'active')->count(),
            'overdue' => Loan::query()->where('status', 'overdue')->count(),
            'borrowed_copies' => LoanItem::query()->where('return_status', 'borrowed')->count(),
        ];

        return view('library.loans.index', compact('loans', 'summary'));
    }

    public function create(Request $request): View
    {
        $this->synchronizeLoanStatuses();
        $this->synchronizeExpiredMembers();
        $this->reservationService->synchronizeAll();

        $settings = $this->loanSettings();
        $preselectedMemberId = $request->integer('member_id') ?: null;
        $preselectedItemId = $request->integer('item_id') ?: null;

        $members = Member::query()
            ->select('members.*')
            ->selectSub(function ($query): void {
                $query->from('loan_items')
                    ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('loans.member_id', 'members.id')
                    ->where('loan_items.return_status', 'borrowed');
            }, 'active_item_count')
            ->selectSub(function ($query): void {
                $query->from('loan_items')
                    ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('loans.member_id', 'members.id')
                    ->where('loan_items.return_status', 'borrowed')
                    ->whereDate('loan_items.due_date', '<', today());
            }, 'overdue_item_count')
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', today());
            })
            ->orderBy('member_name')
            ->get();

        $assets = Asset::query()
            ->with([
                'item:id,item_code,item_name,item_type,status',
                'item.bookDetail:item_id,isbn_10,isbn_13,completion_status,call_number',
                'item.authors:id,author_name',
                'shelf:id,shelf_code,shelf_name,status',
            ])
            ->where('asset_status', 'available')
            ->whereIn('condition_status', ['good', 'fair'])
            ->whereNotNull('current_shelf_id')
            ->whereHas('shelf', fn ($query) => $query->where('status', 'active'))
            ->whereHas('item', function ($query): void {
                $query->where('item_type', 'book')
                    ->where('status', 'active');
            })
            ->whereHas('item.bookDetail', function ($query): void {
                $query->whereIn('completion_status', ['complete', 'verified']);
            })
            ->orderBy('asset_code')
            ->limit(500)
            ->get();

        $preselectedAssetId = $preselectedItemId !== null
            ? $assets->firstWhere('item_id', $preselectedItemId)?->id
            : null;

        $defaultDueDate = today()->addDays($settings['default_days']);

        return view('library.loans.create', compact(
            'members',
            'assets',
            'settings',
            'defaultDueDate',
            'preselectedMemberId',
            'preselectedItemId',
            'preselectedAssetId'
        ));
    }

    public function store(StoreLoanRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $loan = DB::transaction(function () use ($request, $validated): Loan {
                $member = Member::query()->lockForUpdate()->findOrFail((int) $validated['member_id']);
                $this->assertMemberCanBorrow($member);

                $assetIds = collect($validated['asset_ids'])
                    ->map(static fn ($id): int => (int) $id)
                    ->unique()
                    ->values();

                $assets = Asset::query()
                    ->with(['item.bookDetail', 'shelf'])
                    ->whereIn('id', $assetIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($assets->count() !== $assetIds->count()) {
                    throw ValidationException::withMessages([
                        'asset_ids' => 'Satu atau beberapa eksemplar buku tidak ditemukan.',
                    ]);
                }

                foreach ($assetIds as $assetId) {
                    $this->assertAssetCanBeBorrowed($assets->get($assetId));
                }

                $this->reservationService->assertBorrowingAllowed($member->id, $assets->values());

                $settings = $this->loanSettings();
                $activeItemCount = LoanItem::query()
                    ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                    ->where('loans.member_id', $member->id)
                    ->where('loan_items.return_status', 'borrowed')
                    ->count();

                if ($activeItemCount + $assetIds->count() > $settings['max_active_loans']) {
                    $remaining = max($settings['max_active_loans'] - $activeItemCount, 0);

                    throw ValidationException::withMessages([
                        'asset_ids' => "Anggota hanya dapat menambah {$remaining} eksemplar lagi. Batas aktif adalah {$settings['max_active_loans']} eksemplar.",
                    ]);
                }

                $dueDate = Carbon::parse($validated['due_date'])->startOfDay();
                $loan = Loan::query()->create([
                    'loan_code' => $this->generateLoanCode(),
                    'member_id' => $member->id,
                    'loan_date' => now(),
                    'default_due_date' => $dueDate,
                    'status' => 'active',
                    'processed_by' => $request->user()?->id,
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($assetIds as $assetId) {
                    $asset = $assets->get($assetId);

                    LoanItem::query()->create([
                        'loan_id' => $loan->id,
                        'asset_id' => $asset->id,
                        'borrowed_at' => now(),
                        'due_date' => $dueDate,
                        'condition_out' => $asset->condition_status,
                        'return_status' => 'borrowed',
                    ]);
                }

                $completedReservations = $this->reservationService->completeForLoan(
                    $member->id,
                    $assets->values(),
                    $request->user()?->id
                );

                DB::table('audit_logs')->insert([
                    'user_id' => $request->user()?->id,
                    'action' => 'insert',
                    'module_name' => 'library_loans',
                    'table_name' => 'loans',
                    'record_id' => $loan->id,
                    'new_data' => json_encode([
                        'loan_code' => $loan->loan_code,
                        'member_id' => $member->id,
                        'asset_ids' => $assetIds->all(),
                        'due_date' => $dueDate->toDateString(),
                        'completed_reservations' => $completedReservations,
                    ], JSON_THROW_ON_ERROR),
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                    'created_at' => now(),
                ]);

                return $loan;
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            return back()
                ->withInput()
                ->withErrors(['asset_ids' => $this->databaseErrorMessage($exception)]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['asset_ids' => 'Transaksi peminjaman belum dapat disimpan. Periksa kembali anggota dan eksemplar yang dipilih.']);
        }

        return redirect()
            ->route('library.loans.show', $loan)
            ->with('success', 'Peminjaman berhasil dibuat dan status eksemplar berubah menjadi dipinjam.');
    }

    public function show(Loan $loan): View
    {
        $this->synchronizeLoanStatuses();
        $loan->refresh();
        $loan->load([
            'member:id,member_code,member_name,member_type,identity_number,department,phone,email,status',
            'processor:id,full_name,username',
            'items.asset.item:id,item_code,item_name,item_type',
            'items.asset.item.bookDetail:item_id,isbn_10,isbn_13,call_number',
            'items.asset.shelf:id,shelf_code,shelf_name',
            'items.returnedBy:id,full_name',
        ]);

        $finePerDay = $this->loanSettings()['fine_per_day'];
        $today = today();
        $itemRows = $loan->items->map(function (LoanItem $loanItem) use ($finePerDay, $today): array {
            $calculationDate = $loanItem->return_status === 'borrowed'
                ? $today
                : ($loanItem->returned_at?->copy()->startOfDay() ?? $today);

            $daysLate = $loanItem->due_date->isBefore($calculationDate)
                ? (int) $loanItem->due_date->diffInDays($calculationDate)
                : 0;

            $fineAmount = $loanItem->return_status === 'borrowed'
                ? $daysLate * $finePerDay
                : (float) $loanItem->fine_amount;

            return [
                'loan_item' => $loanItem,
                'days_late' => $daysLate,
                'fine_amount' => $fineAmount,
                'fine_is_final' => $loanItem->return_status !== 'borrowed',
            ];
        });

        $summary = [
            'total_items' => $loan->items->count(),
            'borrowed_items' => $loan->items->where('return_status', 'borrowed')->count(),
            'returned_items' => $loan->items->where('return_status', '!=', 'borrowed')->count(),
            'total_fine' => $itemRows->sum('fine_amount'),
        ];

        return view('library.loans.show', compact('loan', 'itemRows', 'summary', 'finePerDay'));
    }

    private function assertMemberCanBorrow(Member $member): void
    {
        if ($member->status !== 'active') {
            throw ValidationException::withMessages([
                'member_id' => 'Anggota tidak aktif dan tidak dapat melakukan peminjaman.',
            ]);
        }

        if ($member->expiry_date !== null && $member->expiry_date->isBefore(today())) {
            $member->update(['status' => 'expired']);

            throw ValidationException::withMessages([
                'member_id' => 'Masa berlaku keanggotaan sudah berakhir.',
            ]);
        }

        $hasOverdueBook = LoanItem::query()
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.member_id', $member->id)
            ->where('loan_items.return_status', 'borrowed')
            ->whereDate('loan_items.due_date', '<', today())
            ->exists();

        if ($hasOverdueBook) {
            throw ValidationException::withMessages([
                'member_id' => 'Anggota masih memiliki buku yang terlambat dan belum dapat membuat peminjaman baru.',
            ]);
        }
    }

    private function assertAssetCanBeBorrowed(?Asset $asset): void
    {
        if ($asset === null) {
            throw ValidationException::withMessages(['asset_ids' => 'Eksemplar buku tidak ditemukan.']);
        }

        if ($asset->item?->item_type !== 'book' || $asset->item?->status !== 'active') {
            throw ValidationException::withMessages(['asset_ids' => "Aset {$asset->asset_code} bukan buku aktif."]);
        }

        if ($asset->asset_status !== 'available') {
            throw ValidationException::withMessages(['asset_ids' => "Eksemplar {$asset->asset_code} tidak tersedia."]);
        }

        if (! in_array($asset->condition_status, ['good', 'fair'], true)) {
            throw ValidationException::withMessages(['asset_ids' => "Kondisi eksemplar {$asset->asset_code} tidak memungkinkan untuk dipinjam."]);
        }

        if ($asset->current_shelf_id === null || $asset->shelf?->status !== 'active') {
            throw ValidationException::withMessages(['asset_ids' => "Eksemplar {$asset->asset_code} belum ditempatkan pada rak aktif."]);
        }

        if (! in_array($asset->item?->bookDetail?->completion_status, ['complete', 'verified'], true)) {
            throw ValidationException::withMessages(['asset_ids' => "Katalog untuk eksemplar {$asset->asset_code} belum lengkap."]);
        }
    }

    /** @return array{default_days:int,max_active_loans:int,fine_per_day:float} */
    private function loanSettings(): array
    {
        $settings = DB::table('system_settings')
            ->whereIn('setting_key', [
                'library.default_loan_days',
                'library.max_active_loans',
                'library.fine_per_day',
            ])
            ->pluck('setting_value', 'setting_key');

        return [
            'default_days' => max((int) ($settings['library.default_loan_days'] ?? 7), 1),
            'max_active_loans' => max((int) ($settings['library.max_active_loans'] ?? 3), 1),
            'fine_per_day' => max((float) ($settings['library.fine_per_day'] ?? 1000), 0),
        ];
    }

    private function generateLoanCode(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = sprintf(
                'PJM-%s-%s',
                now()->format('Ymd-His'),
                Str::upper(Str::random(4))
            );

            if (! Loan::query()->where('loan_code', $candidate)->exists()) {
                return $candidate;
            }

            usleep(1000);
        }

        throw new \RuntimeException('Kode peminjaman tidak dapat dibuat.');
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

    private function synchronizeExpiredMembers(): void
    {
        Member::query()
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', today())
            ->update(['status' => 'expired']);
    }

    private function databaseErrorMessage(QueryException $exception): string
    {
        $message = (string) ($exception->getPrevious()?->getMessage() ?: $exception->getMessage());

        foreach ([
            'Buku tidak tersedia untuk dipinjam.',
            'Anggota telah mencapai batas maksimal peminjaman aktif.',
            'Anggota tidak aktif dan tidak dapat meminjam.',
            'Hanya aset bertipe buku yang dapat dipinjam.',
            'Transaksi peminjaman tidak aktif.',
        ] as $knownMessage) {
            if (str_contains($message, $knownMessage)) {
                return $knownMessage;
            }
        }

        return 'Peminjaman gagal diproses karena data berubah atau tidak lagi memenuhi syarat. Muat ulang halaman lalu coba kembali.';
    }
}
