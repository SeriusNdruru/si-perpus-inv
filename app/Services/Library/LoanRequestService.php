<?php

namespace App\Services\Library;

use App\Models\Asset;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\LoanRequest;
use App\Models\LoanRequestItem;
use App\Models\Member;
use App\Models\User;
use App\Services\StudentEmailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LoanRequestService
{
    public function __construct(
        private readonly ReservationService $reservationService,
        private readonly StudentEmailService $emails,
    ) {
    }

    public function submit(Member $member, array $itemIds, ?string $notes): LoanRequest
    {
        $itemIds = collect($itemIds)
            ->map(static fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($itemIds->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Keranjang pengajuan masih kosong.',
            ]);
        }

        return DB::transaction(function () use ($member, $itemIds, $notes): LoanRequest {
            $lockedMember = Member::query()->lockForUpdate()->findOrFail($member->id);
            $this->assertMemberCanRequest($lockedMember);

            $settings = $this->settings();
            $activeLoans = LoanItem::query()
                ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->where('loans.member_id', $lockedMember->id)
                ->where('loan_items.return_status', 'borrowed')
                ->count();

            $pendingItems = LoanRequestItem::query()
                ->join('loan_requests', 'loan_requests.id', '=', 'loan_request_items.loan_request_id')
                ->where('loan_requests.member_id', $lockedMember->id)
                ->whereIn('loan_requests.status', ['submitted', 'approved', 'ready'])
                ->count();

            if ($activeLoans + $pendingItems + $itemIds->count() > $settings['max_active_loans']) {
                $remaining = max($settings['max_active_loans'] - $activeLoans - $pendingItems, 0);

                throw ValidationException::withMessages([
                    'cart' => "Anda hanya dapat mengajukan {$remaining} buku lagi. Batas pinjaman aktif dan pengajuan adalah {$settings['max_active_loans']} buku.",
                ]);
            }

            $items = DB::table('items')
                ->join('book_details', 'book_details.item_id', '=', 'items.id')
                ->whereIn('items.id', $itemIds)
                ->where('items.item_type', 'book')
                ->where('items.status', 'active')
                ->whereIn('book_details.completion_status', ['complete', 'verified'])
                ->lockForUpdate()
                ->get(['items.id', 'items.item_name']);

            if ($items->count() !== $itemIds->count()) {
                throw ValidationException::withMessages([
                    'cart' => 'Satu atau beberapa buku tidak aktif atau katalog belum lengkap.',
                ]);
            }

            foreach ($itemIds as $itemId) {
                if (! $this->eligibleAssetQuery($itemId)->exists()) {
                    $title = $items->firstWhere('id', $itemId)?->item_name ?? 'Buku';

                    throw ValidationException::withMessages([
                        'cart' => "{$title} tidak memiliki eksemplar yang tersedia saat ini.",
                    ]);
                }
            }

            $request = LoanRequest::query()->create([
                'request_code' => $this->generateRequestCode(),
                'member_id' => $lockedMember->id,
                'status' => 'submitted',
                'requested_at' => now(),
                'member_notes' => $notes,
            ]);

            foreach ($itemIds as $itemId) {
                LoanRequestItem::query()->create([
                    'loan_request_id' => $request->id,
                    'item_id' => $itemId,
                    'asset_id' => null,
                    'created_at' => now(),
                ]);
            }

            $this->notifyStatus($request, 'submitted');

            return $request;
        }, 3);
    }

    public function approve(LoanRequest $request, User $processor, ?string $notes): LoanRequest
    {
        return DB::transaction(function () use ($request, $processor, $notes): LoanRequest {
            $locked = LoanRequest::query()
                ->with('items.item')
                ->lockForUpdate()
                ->findOrFail($request->id);

            if ($locked->status !== 'submitted') {
                throw new RuntimeException('Hanya pengajuan baru yang dapat disetujui.');
            }

            $selectedAssets = collect();

            foreach ($locked->items as $requestItem) {
                $asset = $this->eligibleAssetQuery((int) $requestItem->item_id)
                    ->lockForUpdate()
                    ->first();

                if ($asset === null) {
                    throw new RuntimeException(
                        'Eksemplar untuk buku '.$requestItem->item?->item_name.' tidak lagi tersedia.'
                    );
                }

                $selectedAssets->push($asset);
            }

            $this->reservationService->assertBorrowingAllowed(
                $locked->member_id,
                $selectedAssets
            );

            foreach ($locked->items as $index => $requestItem) {
                /** @var Asset $asset */
                $asset = $selectedAssets->get($index);

                $asset->update([
                    'asset_status' => 'reserved',
                    'updated_by' => $processor->id,
                    'updated_at' => now(),
                ]);

                $requestItem->update(['asset_id' => $asset->id]);
            }

            $locked->update([
                'status' => 'approved',
                'approved_at' => now(),
                'processed_by' => $processor->id,
                'admin_notes' => $notes,
            ]);

            $this->notifyStatus($locked, 'approved');

            return $locked->refresh();
        }, 3);
    }

    public function markReady(LoanRequest $request, User $processor, ?string $notes): LoanRequest
    {
        return DB::transaction(function () use ($request, $processor, $notes): LoanRequest {
            $locked = LoanRequest::query()
                ->with('items.asset')
                ->lockForUpdate()
                ->findOrFail($request->id);

            if ($locked->status !== 'approved') {
                throw new RuntimeException('Pengajuan belum disetujui atau statusnya sudah berubah.');
            }

            if ($locked->items->contains(fn (LoanRequestItem $item): bool => $item->asset_id === null || $item->asset?->asset_status !== 'reserved')) {
                throw new RuntimeException('Satu atau beberapa eksemplar tidak lagi berstatus dipesan.');
            }

            $holdDays = $this->settings()['hold_days'];

            $locked->update([
                'status' => 'ready',
                'ready_at' => now(),
                'pickup_expires_at' => now()->addDays($holdDays),
                'processed_by' => $processor->id,
                'admin_notes' => $notes ?: $locked->admin_notes,
            ]);

            $this->notifyStatus($locked, 'ready');

            return $locked->refresh();
        }, 3);
    }

    public function collect(LoanRequest $request, User $processor, ?string $notes): Loan
    {
        return DB::transaction(function () use ($request, $processor, $notes): Loan {
            $locked = LoanRequest::query()
                ->with(['member', 'items.asset.item.bookDetail', 'items.asset.shelf'])
                ->lockForUpdate()
                ->findOrFail($request->id);

            if ($locked->status !== 'ready') {
                throw new RuntimeException('Hanya pengajuan berstatus siap diambil yang dapat dikonfirmasi.');
            }

            if ($locked->pickup_expires_at !== null && $locked->pickup_expires_at->isPast()) {
                $this->expireLocked($locked, $processor->id);
                throw new RuntimeException('Masa pengambilan sudah berakhir. Pengajuan telah kedaluwarsa.');
            }

            $this->assertMemberCanRequest($locked->member);

            $settings = $this->settings();
            $dueDate = today()->addDays($settings['default_days']);

            $loan = Loan::query()->create([
                'loan_code' => $this->generateLoanCode(),
                'member_id' => $locked->member_id,
                'loan_date' => now(),
                'default_due_date' => $dueDate,
                'status' => 'active',
                'processed_by' => $processor->id,
                'notes' => trim((string) $notes) !== ''
                    ? $notes
                    : 'Dibuat dari pengajuan '.$locked->request_code,
            ]);

            foreach ($locked->items as $requestItem) {
                $asset = Asset::query()
                    ->lockForUpdate()
                    ->findOrFail($requestItem->asset_id);

                if ($asset->asset_status !== 'reserved') {
                    throw new RuntimeException("Aset {$asset->asset_code} tidak lagi berstatus dipesan.");
                }

                $asset->update([
                    'asset_status' => 'available',
                    'updated_by' => $processor->id,
                    'updated_at' => now(),
                ]);

                LoanItem::query()->create([
                    'loan_id' => $loan->id,
                    'asset_id' => $asset->id,
                    'borrowed_at' => now(),
                    'due_date' => $dueDate,
                    'condition_out' => $asset->condition_status,
                    'return_status' => 'borrowed',
                ]);
            }

            $this->reservationService->completeForLoan(
                $locked->member_id,
                $locked->items->pluck('asset')->filter()->values(),
                $processor->id
            );

            $locked->update([
                'status' => 'collected',
                'collected_at' => now(),
                'processed_by' => $processor->id,
                'admin_notes' => $notes ?: $locked->admin_notes,
            ]);

            $this->notifyStatus($locked, 'collected');

            return $loan;
        }, 3);
    }

    public function reject(LoanRequest $request, User $processor, ?string $notes): LoanRequest
    {
        return DB::transaction(function () use ($request, $processor, $notes): LoanRequest {
            $locked = LoanRequest::query()
                ->with('items.asset')
                ->lockForUpdate()
                ->findOrFail($request->id);

            if (! in_array($locked->status, ['submitted', 'approved', 'ready'], true)) {
                throw new RuntimeException('Pengajuan ini tidak dapat ditolak lagi.');
            }

            $this->releaseAssets($locked, $processor->id);

            $locked->update([
                'status' => 'rejected',
                'processed_by' => $processor->id,
                'admin_notes' => $notes,
                'pickup_expires_at' => null,
            ]);

            $this->notifyStatus($locked, 'rejected');

            return $locked->refresh();
        }, 3);
    }

    public function cancelByMember(LoanRequest $request, Member $member): LoanRequest
    {
        return DB::transaction(function () use ($request, $member): LoanRequest {
            $locked = LoanRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->member_id !== $member->id) {
                abort(404);
            }

            if ($locked->status !== 'submitted') {
                throw new RuntimeException('Pengajuan hanya dapat dibatalkan sebelum disetujui.');
            }

            $locked->update([
                'status' => 'cancelled',
                'pickup_expires_at' => null,
            ]);

            $this->notifyStatus($locked, 'cancelled');

            return $locked->refresh();
        }, 3);
    }

    public function syncExpired(?int $memberId = null): int
    {
        $requests = LoanRequest::query()
            ->where('status', 'ready')
            ->whereNotNull('pickup_expires_at')
            ->where('pickup_expires_at', '<=', now())
            ->when($memberId !== null, fn (Builder $query) => $query->where('member_id', $memberId))
            ->pluck('id');

        $count = 0;

        foreach ($requests as $requestId) {
            DB::transaction(function () use ($requestId, &$count): void {
                $request = LoanRequest::query()
                    ->with('items.asset')
                    ->lockForUpdate()
                    ->find($requestId);

                if ($request === null || $request->status !== 'ready') {
                    return;
                }

                $this->expireLocked($request, null);
                $count++;
            }, 3);
        }

        return $count;
    }

    public function settings(): array
    {
        $settings = DB::table('system_settings')
            ->whereIn('setting_key', [
                'library.default_loan_days',
                'library.max_active_loans',
                'library.loan_request_hold_days',
            ])
            ->pluck('setting_value', 'setting_key');

        return [
            'default_days' => max((int) ($settings['library.default_loan_days'] ?? 7), 1),
            'max_active_loans' => max((int) ($settings['library.max_active_loans'] ?? 3), 1),
            'hold_days' => max((int) ($settings['library.loan_request_hold_days'] ?? 2), 1),
        ];
    }

    public function eligibleAssetQuery(int $itemId): Builder
    {
        return Asset::query()
            ->where('item_id', $itemId)
            ->where('asset_status', 'available')
            ->whereIn('condition_status', ['good', 'fair'])
            ->whereNotNull('current_shelf_id')
            ->whereHas('shelf', fn (Builder $query) => $query->where('status', 'active'))
            ->whereHas('item', function (Builder $query): void {
                $query->where('item_type', 'book')
                    ->where('status', 'active');
            })
            ->whereHas('item.bookDetail', function (Builder $query): void {
                $query->whereIn('completion_status', ['complete', 'verified']);
            })
            ->orderBy('id');
    }

    private function assertMemberCanRequest(Member $member): void
    {
        if ($member->status !== 'active') {
            throw ValidationException::withMessages([
                'member' => 'Keanggotaan tidak aktif.',
            ]);
        }

        if ($member->expiry_date !== null && $member->expiry_date->isBefore(today())) {
            throw ValidationException::withMessages([
                'member' => 'Masa berlaku keanggotaan sudah berakhir.',
            ]);
        }

        $hasOverdue = LoanItem::query()
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.member_id', $member->id)
            ->where('loan_items.return_status', 'borrowed')
            ->whereDate('loan_items.due_date', '<', today())
            ->exists();

        if ($hasOverdue) {
            throw ValidationException::withMessages([
                'member' => 'Anda masih memiliki buku terlambat. Kembalikan buku sebelum membuat pengajuan baru.',
            ]);
        }
    }

    private function expireLocked(LoanRequest $request, ?int $processorId): void
    {
        $this->releaseAssets($request, $processorId);

        $request->update([
            'status' => 'expired',
            'processed_by' => $processorId ?: $request->processed_by,
            'pickup_expires_at' => null,
        ]);

        $this->notifyStatus($request, 'expired');
    }

    private function releaseAssets(LoanRequest $request, ?int $processorId): void
    {
        $request->loadMissing('items.asset');

        foreach ($request->items as $requestItem) {
            $asset = $requestItem->asset;

            if ($asset !== null && $asset->asset_status === 'reserved') {
                $asset->update([
                    'asset_status' => 'available',
                    'updated_by' => $processorId,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function notifyStatus(LoanRequest $request, string $status): void
    {
        $messages = [
            'submitted' => [
                'Pengajuan peminjaman diterima',
                "Pengajuan {$request->request_code} sudah diterima dan menunggu persetujuan petugas.",
            ],
            'approved' => [
                'Pengajuan peminjaman disetujui',
                "Pengajuan {$request->request_code} disetujui. Petugas sedang menyiapkan buku.",
            ],
            'ready' => [
                'Buku siap diambil',
                "Buku untuk pengajuan {$request->request_code} siap diambil sebelum {$request->pickup_expires_at?->format('d/m/Y H:i')}.",
            ],
            'collected' => [
                'Pengambilan buku dikonfirmasi',
                "Buku pada pengajuan {$request->request_code} sudah dicatat sebagai peminjaman aktif.",
            ],
            'rejected' => [
                'Pengajuan peminjaman ditolak',
                "Pengajuan {$request->request_code} ditolak. Periksa catatan petugas pada detail pengajuan.",
            ],
            'cancelled' => [
                'Pengajuan dibatalkan',
                "Pengajuan {$request->request_code} telah dibatalkan.",
            ],
            'expired' => [
                'Masa pengambilan berakhir',
                "Pengajuan {$request->request_code} kedaluwarsa karena buku tidak diambil dalam batas waktu.",
            ],
        ];

        [$title, $message] = $messages[$status] ?? ['Pembaruan pengajuan', 'Status pengajuan berubah.'];

        DB::table('member_notifications')->insertOrIgnore([
            'member_id' => $request->member_id,
            'loan_item_id' => null,
            'notification_key' => "loan-request:{$request->id}:{$status}",
            'notification_type' => 'request_status',
            'title' => $title,
            'message' => $message,
            'is_read' => 0,
            'read_at' => null,
            'created_at' => now(),
        ]);

        $requestId = $request->id;

        DB::afterCommit(function () use ($requestId, $status, $title, $message): void {
            $this->emails->sendLoanRequestStatus(
                loanRequestId: $requestId,
                status: $status,
                title: $title,
                message: $message,
            );
        });
    }

    private function generateRequestCode(): string
    {
        do {
            $code = 'REQ-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
        } while (LoanRequest::query()->where('request_code', $code)->exists());

        return $code;
    }

    private function generateLoanCode(): string
    {
        do {
            $code = 'PJM-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
        } while (Loan::query()->where('loan_code', $code)->exists());

        return $code;
    }
}
