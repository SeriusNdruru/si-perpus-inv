<?php

namespace App\Services\Library;

use App\Models\Asset;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    /** @return array{hold_days:int,max_active:int} */
    public function settings(): array
    {
        $settings = DB::table('system_settings')
            ->whereIn('setting_key', [
                'library.reservation_hold_days',
                'library.max_active_reservations',
            ])
            ->pluck('setting_value', 'setting_key');

        return [
            'hold_days' => max((int) ($settings['library.reservation_hold_days'] ?? 2), 1),
            'max_active' => max((int) ($settings['library.max_active_reservations'] ?? 3), 1),
        ];
    }

    public function synchronizeAll(): void
    {
        $itemIds = Reservation::query()
            ->whereIn('status', ['waiting', 'ready'])
            ->distinct()
            ->pluck('item_id');

        foreach ($itemIds as $itemId) {
            $this->synchronizeItem((int) $itemId);
        }
    }

    public function synchronizeItem(int $itemId): void
    {
        DB::transaction(function () use ($itemId): void {
            $this->synchronizeItemLocked($itemId);
        }, 3);
    }

    /**
     * Menyelaraskan antrean reservasi untuk satu judul.
     * Metode ini aman dipanggil dari transaksi yang sudah berjalan.
     */
    public function synchronizeItemLocked(int $itemId): void
    {
        $now = now();
        $holdDays = $this->settings()['hold_days'];

        Reservation::query()
            ->where('item_id', $itemId)
            ->where('status', 'ready')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->lockForUpdate()
            ->get()
            ->each(function (Reservation $reservation): void {
                $reservation->update([
                    'status' => 'expired',
                    'expires_at' => null,
                ]);
            });

        $queue = Reservation::query()
            ->where('item_id', $itemId)
            ->whereIn('status', ['waiting', 'ready'])
            ->orderBy('reservation_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($queue->isEmpty()) {
            return;
        }

        $availableCount = $this->eligibleAvailableAssetsQuery($itemId)
            ->lockForUpdate()
            ->get(['assets.id'])
            ->count();

        foreach ($queue->values() as $index => $reservation) {
            $queueNumber = $index + 1;
            $shouldBeReady = $index < $availableCount;

            if ($shouldBeReady) {
                $expiresAt = $reservation->status === 'ready'
                    && $reservation->expires_at !== null
                    && $reservation->expires_at->isFuture()
                        ? $reservation->expires_at
                        : $now->copy()->addDays($holdDays);

                $reservation->update([
                    'queue_number' => $queueNumber,
                    'status' => 'ready',
                    'expires_at' => $expiresAt,
                ]);

                continue;
            }

            $reservation->update([
                'queue_number' => $queueNumber,
                'status' => 'waiting',
                'expires_at' => null,
            ]);
        }
    }

    /**
     * @param Collection<int, Asset> $assets
     */
    public function assertBorrowingAllowed(int $memberId, Collection $assets): void
    {
        $assetsByItem = $assets->groupBy(static fn (Asset $asset): int => (int) $asset->item_id);

        foreach ($assetsByItem as $itemId => $itemAssets) {
            $itemId = (int) $itemId;
            $this->synchronizeItemLocked($itemId);

            $availableCount = $this->eligibleAvailableAssetsQuery($itemId)
                ->lockForUpdate()
                ->get(['assets.id'])
                ->count();

            $readyForOtherMembers = Reservation::query()
                ->where('item_id', $itemId)
                ->where('status', 'ready')
                ->where('member_id', '<>', $memberId)
                ->lockForUpdate()
                ->get(['id'])
                ->count();

            $unprotectedCopies = max($availableCount - $readyForOtherMembers, 0);

            if ($itemAssets->count() > $unprotectedCopies) {
                $title = $itemAssets->first()?->item?->item_name ?? 'Buku';

                throw ValidationException::withMessages([
                    'asset_ids' => "{$title} sedang diprioritaskan untuk anggota yang memiliki reservasi lebih dahulu.",
                ]);
            }
        }
    }

    /**
     * @param Collection<int, Asset> $assets
     */
    public function completeForLoan(int $memberId, Collection $assets, ?int $processedBy): int
    {
        $completed = 0;
        $itemIds = $assets->pluck('item_id')->map(static fn ($id): int => (int) $id)->unique();

        foreach ($itemIds as $itemId) {
            $reservation = Reservation::query()
                ->where('item_id', $itemId)
                ->where('member_id', $memberId)
                ->where('status', 'ready')
                ->orderBy('queue_number')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($reservation !== null) {
                $reservation->update([
                    'status' => 'completed',
                    'expires_at' => null,
                    'processed_by' => $processedBy,
                ]);

                $completed++;
            }

            $this->synchronizeItemLocked($itemId);
        }

        return $completed;
    }

    public function eligibleAvailableAssetsQuery(int $itemId): Builder
    {
        return Asset::query()
            ->where('assets.item_id', $itemId)
            ->where('assets.asset_status', 'available')
            ->whereIn('assets.condition_status', ['good', 'fair'])
            ->whereNotNull('assets.current_shelf_id')
            ->whereHas('shelf', fn (Builder $query) => $query->where('status', 'active'))
            ->whereHas('item', function (Builder $query): void {
                $query->where('item_type', 'book')
                    ->where('status', 'active');
            })
            ->whereHas('item.bookDetail', function (Builder $query): void {
                $query->whereIn('completion_status', ['complete', 'verified']);
            });
    }
}
