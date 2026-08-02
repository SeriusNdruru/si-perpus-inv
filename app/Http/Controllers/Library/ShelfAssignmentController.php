<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\BulkShelfAssignmentRequest;
use App\Http\Requests\Library\RemoveShelfAssignmentRequest;
use App\Http\Requests\Library\UpdateShelfAssignmentRequest;
use App\Models\Asset;
use App\Models\LibraryShelf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ShelfAssignmentController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const ASSET_STATUSES = [
        'unprocessed' => 'Belum diproses',
        'available' => 'Tersedia',
        'borrowed' => 'Dipinjam',
        'reserved' => 'Direservasi',
        'maintenance' => 'Perawatan',
        'damaged' => 'Rusak',
        'lost' => 'Hilang',
        'disposed' => 'Dihapuskan',
    ];

    /**
     * @var array<string, string>
     */
    private const CATALOG_STATUSES = [
        'incomplete' => 'Belum lengkap',
        'complete' => 'Lengkap',
        'verified' => 'Terverifikasi',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $assignment = (string) $request->query('assignment');
        $shelfId = $request->filled('shelf_id') ? (int) $request->query('shelf_id') : null;
        $assetStatus = (string) $request->query('asset_status');
        $catalogStatus = (string) $request->query('catalog_status');

        $assets = Asset::query()
            ->whereHas('item', fn ($query) => $query->where('item_type', 'book'))
            ->with([
                'item:id,item_code,item_name,item_type',
                'item.bookDetail:item_id,completion_status,classification_code,call_number',
                'item.authors:id,author_name',
                'shelf:id,shelf_code,shelf_name,location_id,status,capacity',
                'shelf.location:id,location_code,location_name',
                'location:id,location_code,location_name',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhereHas('item', function ($itemQuery) use ($search): void {
                            $itemQuery
                                ->where('item_code', 'like', "%{$search}%")
                                ->orWhere('item_name', 'like', "%{$search}%")
                                ->orWhereHas('bookDetail', function ($bookQuery) use ($search): void {
                                    $bookQuery
                                        ->where('isbn_10', 'like', "%{$search}%")
                                        ->orWhere('isbn_13', 'like', "%{$search}%")
                                        ->orWhere('call_number', 'like', "%{$search}%");
                                })
                                ->orWhereHas('authors', fn ($authorQuery) => $authorQuery->where('author_name', 'like', "%{$search}%"));
                        });
                });
            })
            ->when($assignment === 'without_shelf', fn ($query) => $query->whereNull('current_shelf_id'))
            ->when($assignment === 'assigned', fn ($query) => $query->whereNotNull('current_shelf_id'))
            ->when($shelfId !== null, fn ($query) => $query->where('current_shelf_id', $shelfId))
            ->when(
                array_key_exists($assetStatus, self::ASSET_STATUSES),
                fn ($query) => $query->where('asset_status', $assetStatus)
            )
            ->when(
                array_key_exists($catalogStatus, self::CATALOG_STATUSES),
                fn ($query) => $query->whereHas(
                    'item.bookDetail',
                    fn ($bookQuery) => $bookQuery->where('completion_status', $catalogStatus)
                )
            )
            ->orderByRaw('current_shelf_id IS NULL DESC')
            ->orderByDesc('created_at')
            ->orderBy('asset_code')
            ->paginate(15)
            ->withQueryString();

        $baseBookAssets = Asset::query()
            ->whereHas('item', fn ($query) => $query->where('item_type', 'book'))
            ->whereNotIn('asset_status', ['disposed', 'lost']);

        $summary = [
            'total' => (clone $baseBookAssets)->count(),
            'without_shelf' => (clone $baseBookAssets)->whereNull('current_shelf_id')->count(),
            'assigned' => (clone $baseBookAssets)->whereNotNull('current_shelf_id')->count(),
            'ready' => (clone $baseBookAssets)->where('asset_status', 'available')->count(),
        ];

        return view('library.shelf-assignments.index', [
            'assets' => $assets,
            'summary' => $summary,
            'shelves' => $this->shelfOptions(),
            'filterShelves' => LibraryShelf::query()->orderBy('shelf_code')->get(['id', 'shelf_code', 'shelf_name', 'status']),
            'assetStatuses' => self::ASSET_STATUSES,
            'catalogStatuses' => self::CATALOG_STATUSES,
        ]);
    }

    public function edit(Asset $asset): View
    {
        $this->ensureBookAsset($asset);

        $asset->load([
            'item.category:id,category_code,category_name',
            'item.bookDetail:item_id,completion_status,classification_code,call_number,isbn_10,isbn_13',
            'item.authors:id,author_name',
            'shelf.location:id,location_code,location_name',
            'location:id,location_code,location_name',
        ]);

        $history = $asset->shelfHistory()
            ->with([
                'oldShelf:id,shelf_code,shelf_name',
                'newShelf:id,shelf_code,shelf_name',
                'changedBy:id,full_name',
            ])
            ->limit(10)
            ->get();

        return view('library.shelf-assignments.edit', [
            'asset' => $asset,
            'history' => $history,
            'shelves' => $this->shelfOptions($asset->current_shelf_id),
            'assetStatuses' => self::ASSET_STATUSES,
            'catalogStatuses' => self::CATALOG_STATUSES,
            'canChangeShelf' => $this->canChangeShelf($asset),
        ]);
    }

    public function update(UpdateShelfAssignmentRequest $request, Asset $asset): RedirectResponse
    {
        $this->ensureBookAsset($asset);
        $data = $request->validated();
        $userId = (int) $request->user()->id;

        try {
            DB::transaction(function () use ($asset, $data, $userId): void {
                $lockedAsset = Asset::query()
                    ->with('item.bookDetail')
                    ->lockForUpdate()
                    ->findOrFail($asset->id);

                $this->assertShelfCanBeChanged($lockedAsset);

                $shelf = LibraryShelf::query()->lockForUpdate()->findOrFail((int) $data['shelf_id']);

                if ($shelf->status !== 'active') {
                    throw ValidationException::withMessages([
                        'shelf_id' => 'Rak tujuan sedang tidak aktif.',
                    ]);
                }

                $additionalCopies = $lockedAsset->current_shelf_id === $shelf->id ? 0 : 1;
                $this->assertShelfCapacity($shelf, $additionalCopies);

                $this->setHistoryUser($userId);

                try {
                    $lockedAsset->update([
                        'current_shelf_id' => $shelf->id,
                        'current_location_id' => $shelf->location_id ?? $lockedAsset->current_location_id,
                        'asset_status' => $this->statusAfterAssignment($lockedAsset),
                        'updated_by' => $userId,
                    ]);
                } finally {
                    $this->clearHistoryUser();
                }

                if ($additionalCopies === 1) {
                    $this->updateLatestHistoryNote(
                        $lockedAsset->id,
                        $data['notes'] ?? 'Penempatan eksemplar ke rak melalui modul perpustakaan.'
                    );
                }
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Penempatan rak gagal disimpan. Periksa data lalu coba kembali.');
        }

        return redirect()
            ->route('library.shelf-assignments.edit', $asset)
            ->with('success', 'Rak eksemplar berhasil diperbarui.');
    }

    public function bulkUpdate(BulkShelfAssignmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = (int) $request->user()->id;
        $assetIds = array_values(array_unique(array_map('intval', $data['asset_ids'])));
        $movedCount = 0;

        try {
            DB::transaction(function () use ($assetIds, $data, $userId, &$movedCount): void {
                $shelf = LibraryShelf::query()->lockForUpdate()->findOrFail((int) $data['shelf_id']);

                if ($shelf->status !== 'active') {
                    throw ValidationException::withMessages([
                        'shelf_id' => 'Rak tujuan sedang tidak aktif.',
                    ]);
                }

                $assets = Asset::query()
                    ->with('item.bookDetail')
                    ->whereIn('id', $assetIds)
                    ->lockForUpdate()
                    ->get();

                if ($assets->count() !== count($assetIds)) {
                    throw ValidationException::withMessages([
                        'asset_ids' => 'Sebagian eksemplar tidak ditemukan. Muat ulang halaman lalu pilih kembali.',
                    ]);
                }

                foreach ($assets as $asset) {
                    $this->ensureBookAsset($asset);
                    $this->assertShelfCanBeChanged($asset);
                }

                $additionalCopies = $assets
                    ->filter(fn (Asset $asset) => $asset->current_shelf_id !== $shelf->id)
                    ->count();

                $this->assertShelfCapacity($shelf, $additionalCopies);
                $this->setHistoryUser($userId);

                try {
                    foreach ($assets as $asset) {
                        if ($asset->current_shelf_id === $shelf->id) {
                            continue;
                        }

                        $asset->update([
                            'current_shelf_id' => $shelf->id,
                            'current_location_id' => $shelf->location_id ?? $asset->current_location_id,
                            'asset_status' => $this->statusAfterAssignment($asset),
                            'updated_by' => $userId,
                        ]);

                        $this->updateLatestHistoryNote(
                            $asset->id,
                            $data['notes'] ?? 'Penempatan massal eksemplar ke rak melalui modul perpustakaan.'
                        );
                        $movedCount++;
                    }
                } finally {
                    $this->clearHistoryUser();
                }
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Penempatan rak secara massal gagal. Periksa data lalu coba kembali.');
        }

        return back()->with(
            'success',
            $movedCount > 0
                ? $movedCount.' eksemplar berhasil ditempatkan ke rak tujuan.'
                : 'Semua eksemplar pilihan sudah berada pada rak tujuan.'
        );
    }

    public function remove(RemoveShelfAssignmentRequest $request, Asset $asset): RedirectResponse
    {
        $this->ensureBookAsset($asset);
        $data = $request->validated();
        $userId = (int) $request->user()->id;

        try {
            DB::transaction(function () use ($asset, $data, $userId): void {
                $lockedAsset = Asset::query()
                    ->with('item.bookDetail')
                    ->lockForUpdate()
                    ->findOrFail($asset->id);

                $this->assertShelfCanBeChanged($lockedAsset);

                if ($lockedAsset->current_shelf_id === null) {
                    return;
                }

                $this->setHistoryUser($userId);

                try {
                    $lockedAsset->update([
                        'current_shelf_id' => null,
                        'asset_status' => 'unprocessed',
                        'updated_by' => $userId,
                    ]);
                } finally {
                    $this->clearHistoryUser();
                }

                $this->updateLatestHistoryNote(
                    $lockedAsset->id,
                    $data['remove_notes'] ?? 'Penempatan rak dilepas melalui modul perpustakaan.'
                );
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Penempatan rak gagal dilepas. Coba kembali.');
        }

        return redirect()
            ->route('library.shelf-assignments.edit', $asset)
            ->with('success', 'Penempatan rak berhasil dilepas. Eksemplar kembali berstatus belum diproses.');
    }

    private function ensureBookAsset(Asset $asset): void
    {
        $isBook = $asset->relationLoaded('item')
            ? $asset->item?->item_type === 'book'
            : $asset->item()->where('item_type', 'book')->exists();

        abort_unless($isBook, 404);
    }

    private function canChangeShelf(Asset $asset): bool
    {
        return in_array($asset->asset_status, ['unprocessed', 'available'], true);
    }

    private function assertShelfCanBeChanged(Asset $asset): void
    {
        if (! $this->canChangeShelf($asset)) {
            throw ValidationException::withMessages([
                'shelf_id' => 'Rak tidak dapat diubah saat eksemplar berstatus '.(self::ASSET_STATUSES[$asset->asset_status] ?? $asset->asset_status).'.',
            ]);
        }
    }

    private function statusAfterAssignment(Asset $asset): string
    {
        $completionStatus = $asset->item?->bookDetail?->completion_status;

        if (
            in_array($completionStatus, ['complete', 'verified'], true)
            && in_array($asset->condition_status, ['good', 'fair'], true)
        ) {
            return 'available';
        }

        return 'unprocessed';
    }

    private function assertShelfCapacity(LibraryShelf $shelf, int $additionalCopies): void
    {
        if ($additionalCopies <= 0 || $shelf->capacity === null) {
            return;
        }

        $occupied = Asset::query()
            ->where('current_shelf_id', $shelf->id)
            ->whereNotIn('asset_status', ['disposed', 'lost'])
            ->count();

        if ($occupied + $additionalCopies > $shelf->capacity) {
            $remaining = max(0, $shelf->capacity - $occupied);

            throw ValidationException::withMessages([
                'shelf_id' => "Kapasitas rak tidak cukup. Sisa kapasitas saat ini {$remaining} eksemplar.",
            ]);
        }
    }

    /**
     * @return Collection<int, LibraryShelf>
     */
    private function shelfOptions(?int $currentShelfId = null): Collection
    {
        return LibraryShelf::query()
            ->select('library_shelves.*')
            ->selectSub(function ($query): void {
                $query->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.current_shelf_id', 'library_shelves.id')
                    ->whereNotIn('assets.asset_status', ['disposed', 'lost']);
            }, 'occupied_count')
            ->with('location:id,location_code,location_name')
            ->where(function ($query) use ($currentShelfId): void {
                $query->where('status', 'active');

                if ($currentShelfId !== null) {
                    $query->orWhere('id', $currentShelfId);
                }
            })
            ->orderBy('shelf_code')
            ->get();
    }

    private function updateLatestHistoryNote(int $assetId, string $notes): void
    {
        $historyId = DB::table('asset_shelf_history')
            ->where('asset_id', $assetId)
            ->max('id');

        if ($historyId !== null) {
            DB::table('asset_shelf_history')
                ->where('id', $historyId)
                ->update(['notes' => $notes]);
        }
    }

    private function setHistoryUser(int $userId): void
    {
        DB::statement('SET @app_user_id = '.(int) $userId);
    }

    private function clearHistoryUser(): void
    {
        DB::statement('SET @app_user_id = NULL');
    }
}
