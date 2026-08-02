<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\CompleteMaintenanceRequest;
use App\Http\Requests\Inventory\StoreMaintenanceRequest;
use App\Http\Requests\Inventory\UpdateMaintenanceRequest;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class MaintenanceController extends Controller
{
    private const STATUSES = [
        'reported' => 'Dilaporkan',
        'in_progress' => 'Sedang diperbaiki',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');
        $dateFrom = (string) $request->query('date_from');
        $dateTo = (string) $request->query('date_to');

        $records = MaintenanceRecord::query()
            ->with([
                'asset:id,item_id,asset_code,barcode,condition_status,asset_status,current_location_id',
                'asset.item:id,item_code,item_name,item_type',
                'asset.location:id,location_code,location_name',
                'reporter:id,full_name',
                'handler:id,full_name',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery
                        ->where('maintenance_code', 'like', "%{$search}%")
                        ->orWhere('issue_description', 'like', "%{$search}%")
                        ->orWhere('vendor_name', 'like', "%{$search}%")
                        ->orWhereHas('asset', function (Builder $assetQuery) use ($search): void {
                            $assetQuery
                                ->where('asset_code', 'like', "%{$search}%")
                                ->orWhere('barcode', 'like', "%{$search}%")
                                ->orWhereHas('item', function (Builder $itemQuery) use ($search): void {
                                    $itemQuery
                                        ->where('item_code', 'like', "%{$search}%")
                                        ->orWhere('item_name', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when(array_key_exists($status, self::STATUSES), fn (Builder $query) => $query->where('status', $status))
            ->when($this->isDate($dateFrom), fn (Builder $query) => $query->whereDate('reported_at', '>=', $dateFrom))
            ->when($this->isDate($dateTo), fn (Builder $query) => $query->whereDate('reported_at', '<=', $dateTo))
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $summary = [
            'total' => MaintenanceRecord::query()->count(),
            'open' => MaintenanceRecord::query()->whereIn('status', ['reported', 'in_progress'])->count(),
            'completed' => MaintenanceRecord::query()->where('status', 'completed')->count(),
            'cost' => (float) MaintenanceRecord::query()->where('status', 'completed')->sum('cost'),
        ];

        return view('inventory.maintenance-records.index', [
            'records' => $records,
            'summary' => $summary,
            'statuses' => self::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedAssetId = (int) $request->query('asset_id');

        return view('inventory.maintenance-records.create', [
            'assets' => $this->eligibleAssets(),
            'selectedAssetId' => $selectedAssetId,
        ]);
    }

    public function store(StoreMaintenanceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $recordId = DB::transaction(function () use ($data, $request): int {
                /** @var Asset $asset */
                $asset = Asset::query()
                    ->with('item:id,item_type,status,tracking_type')
                    ->lockForUpdate()
                    ->findOrFail((int) $data['asset_id']);

                $this->assertAssetCanEnterMaintenance($asset);

                $hasOpenRecord = MaintenanceRecord::query()
                    ->where('asset_id', $asset->id)
                    ->whereIn('status', ['reported', 'in_progress'])
                    ->lockForUpdate()
                    ->exists();

                if ($hasOpenRecord) {
                    throw ValidationException::withMessages([
                        'asset_id' => 'Aset tersebut masih memiliki pemeliharaan yang belum selesai.',
                    ]);
                }

                $description = trim((string) $data['issue_description']);
                if (! empty($data['notes'])) {
                    $description .= "\n\nCatatan awal: ".trim((string) $data['notes']);
                }

                $record = MaintenanceRecord::query()->create([
                    'maintenance_code' => $this->nextCode(Carbon::parse((string) $data['reported_at'])->toDateString()),
                    'asset_id' => $asset->id,
                    'reported_at' => Carbon::parse((string) $data['reported_at']),
                    'issue_description' => $description,
                    'vendor_name' => $data['vendor_name'] ?? null,
                    'cost' => 0,
                    'status' => 'reported',
                    'reported_by' => $request->user()->id,
                ]);

                $asset->update([
                    'asset_status' => 'maintenance',
                    'updated_by' => $request->user()->id,
                ]);

                $this->insertMovement(
                    $record,
                    $asset,
                    'maintenance_out',
                    (int) $request->user()->id,
                    'Aset dikeluarkan dari ketersediaan untuk pemeliharaan.'
                );

                return (int) $record->id;
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['database' => $this->databaseMessage($exception)]);
        }

        return redirect()
            ->route('inventory.maintenance-records.show', $recordId)
            ->with('success', 'Laporan pemeliharaan berhasil dibuat dan aset ditandai sedang dipelihara.');
    }

    public function show(MaintenanceRecord $maintenanceRecord): View
    {
        $maintenanceRecord->load([
            'asset.item.category:id,category_code,category_name',
            'asset.location:id,location_code,location_name',
            'asset.shelf:id,shelf_code,shelf_name',
            'reporter:id,full_name',
            'handler:id,full_name',
        ]);

        return view('inventory.maintenance-records.show', [
            'record' => $maintenanceRecord,
        ]);
    }

    public function edit(MaintenanceRecord $maintenanceRecord): View|RedirectResponse
    {
        if (in_array($maintenanceRecord->status, ['completed', 'cancelled'], true)) {
            return redirect()
                ->route('inventory.maintenance-records.show', $maintenanceRecord)
                ->with('error', 'Pemeliharaan yang sudah selesai atau dibatalkan tidak dapat diubah.');
        }

        $maintenanceRecord->load('asset.item:id,item_code,item_name,item_type');

        return view('inventory.maintenance-records.edit', [
            'record' => $maintenanceRecord,
        ]);
    }

    public function update(UpdateMaintenanceRequest $request, MaintenanceRecord $maintenanceRecord): RedirectResponse
    {
        if (in_array($maintenanceRecord->status, ['completed', 'cancelled'], true)) {
            return redirect()
                ->route('inventory.maintenance-records.show', $maintenanceRecord)
                ->with('error', 'Pemeliharaan yang sudah selesai atau dibatalkan tidak dapat diubah.');
        }

        $data = $request->validated();
        $maintenanceRecord->update([
            'reported_at' => Carbon::parse((string) $data['reported_at']),
            'issue_description' => trim((string) $data['issue_description']),
            'action_taken' => $data['action_taken'] ?? null,
            'vendor_name' => $data['vendor_name'] ?? null,
            'cost' => $data['cost'] ?? 0,
        ]);

        return redirect()
            ->route('inventory.maintenance-records.show', $maintenanceRecord)
            ->with('success', 'Data pemeliharaan berhasil diperbarui.');
    }

    public function start(Request $request, MaintenanceRecord $maintenanceRecord): RedirectResponse
    {
        if ($maintenanceRecord->status !== 'reported') {
            return back()->with('error', 'Hanya pemeliharaan berstatus dilaporkan yang dapat dimulai.');
        }

        $maintenanceRecord->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'handled_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Proses perbaikan berhasil dimulai.');
    }

    public function completeForm(MaintenanceRecord $maintenanceRecord): View|RedirectResponse
    {
        if (! in_array($maintenanceRecord->status, ['reported', 'in_progress'], true)) {
            return redirect()
                ->route('inventory.maintenance-records.show', $maintenanceRecord)
                ->with('error', 'Pemeliharaan ini tidak dapat diselesaikan karena statusnya sudah final.');
        }

        $maintenanceRecord->load('asset.item:id,item_code,item_name,item_type');

        return view('inventory.maintenance-records.complete', [
            'record' => $maintenanceRecord,
        ]);
    }

    public function complete(
        CompleteMaintenanceRequest $request,
        MaintenanceRecord $maintenanceRecord
    ): RedirectResponse {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($data, $request, $maintenanceRecord): void {
                /** @var MaintenanceRecord $record */
                $record = MaintenanceRecord::query()
                    ->lockForUpdate()
                    ->findOrFail($maintenanceRecord->id);

                if (! in_array($record->status, ['reported', 'in_progress'], true)) {
                    throw new RuntimeException('Status pemeliharaan berubah. Muat ulang halaman lalu coba lagi.');
                }

                /** @var Asset $asset */
                $asset = Asset::query()
                    ->with(['item.bookDetail', 'shelf'])
                    ->lockForUpdate()
                    ->findOrFail($record->asset_id);

                $asset->condition_status = $data['result_condition'];
                $asset->asset_status = $this->statusAfterMaintenance($asset, $data['result_condition']);
                $asset->updated_by = $request->user()->id;
                $asset->save();

                $record->update([
                    'status' => 'completed',
                    'started_at' => $record->started_at ?? now(),
                    'completed_at' => Carbon::parse((string) $data['completed_at']),
                    'action_taken' => trim((string) $data['action_taken']),
                    'vendor_name' => $data['vendor_name'] ?? null,
                    'cost' => $data['cost'],
                    'handled_by' => $request->user()->id,
                ]);

                $this->insertMovement(
                    $record,
                    $asset,
                    'maintenance_in',
                    (int) $request->user()->id,
                    'Aset dikembalikan dari pemeliharaan dengan kondisi '.strtoupper((string) $data['result_condition']).'.'
                );
            }, 3);
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['database' => $this->databaseMessage($exception)]);
        }

        return redirect()
            ->route('inventory.maintenance-records.show', $maintenanceRecord)
            ->with('success', 'Pemeliharaan berhasil diselesaikan dan status aset telah diperbarui.');
    }

    public function cancel(Request $request, MaintenanceRecord $maintenanceRecord): RedirectResponse
    {
        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:2000'],
        ], [
            'cancellation_reason.required' => 'Alasan pembatalan wajib diisi.',
            'cancellation_reason.max' => 'Alasan pembatalan maksimal 2.000 karakter.',
        ]);

        try {
            DB::transaction(function () use ($data, $request, $maintenanceRecord): void {
                /** @var MaintenanceRecord $record */
                $record = MaintenanceRecord::query()
                    ->lockForUpdate()
                    ->findOrFail($maintenanceRecord->id);

                if (! in_array($record->status, ['reported', 'in_progress'], true)) {
                    throw new RuntimeException('Pemeliharaan yang sudah final tidak dapat dibatalkan.');
                }

                /** @var Asset $asset */
                $asset = Asset::query()
                    ->with(['item.bookDetail', 'shelf'])
                    ->lockForUpdate()
                    ->findOrFail($record->asset_id);

                $asset->asset_status = $this->statusAfterMaintenance($asset, (string) $asset->condition_status);
                $asset->updated_by = $request->user()->id;
                $asset->save();

                $existingAction = trim((string) $record->action_taken);
                $reason = 'Pemeliharaan dibatalkan: '.trim((string) $data['cancellation_reason']);

                $record->update([
                    'status' => 'cancelled',
                    'completed_at' => now(),
                    'action_taken' => $existingAction !== '' ? $existingAction."\n\n".$reason : $reason,
                    'handled_by' => $record->handled_by ?? $request->user()->id,
                ]);

                $this->insertMovement(
                    $record,
                    $asset,
                    'maintenance_in',
                    (int) $request->user()->id,
                    'Pemeliharaan dibatalkan. Aset dikembalikan ke status operasional yang sesuai.'
                );
            }, 3);
        } catch (Throwable $exception) {
            return back()->with('error', $this->databaseMessage($exception));
        }

        return redirect()
            ->route('inventory.maintenance-records.show', $maintenanceRecord)
            ->with('success', 'Pemeliharaan dibatalkan dan status aset telah dipulihkan.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Asset>
     */
    private function eligibleAssets()
    {
        return Asset::query()
            ->with([
                'item:id,item_code,item_name,item_type,status,tracking_type',
                'location:id,location_code,location_name',
            ])
            ->whereHas('item', fn (Builder $query) => $query
                ->where('status', 'active')
                ->where('tracking_type', 'asset'))
            ->whereNotIn('asset_status', ['borrowed', 'reserved', 'maintenance', 'lost', 'disposed'])
            ->whereDoesntHave('maintenanceRecords', fn (Builder $query) => $query
                ->whereIn('status', ['reported', 'in_progress']))
            ->orderBy('asset_code')
            ->get();
    }

    private function assertAssetCanEnterMaintenance(Asset $asset): void
    {
        if ($asset->item === null || $asset->item->status !== 'active' || $asset->item->tracking_type !== 'asset') {
            throw ValidationException::withMessages([
                'asset_id' => 'Aset tidak aktif atau bukan aset individual.',
            ]);
        }

        if (in_array($asset->asset_status, ['borrowed', 'reserved', 'maintenance', 'lost', 'disposed'], true)) {
            throw ValidationException::withMessages([
                'asset_id' => 'Aset dengan status saat ini tidak dapat dimasukkan ke pemeliharaan.',
            ]);
        }
    }

    private function statusAfterMaintenance(Asset $asset, string $condition): string
    {
        if ($condition === 'damaged') {
            return 'damaged';
        }

        if ($asset->item?->status !== 'active') {
            return 'unprocessed';
        }

        if ($asset->item?->item_type !== 'book') {
            return 'available';
        }

        $catalogReady = in_array($asset->item?->bookDetail?->completion_status, ['complete', 'verified'], true);
        $shelfReady = $asset->shelf !== null && $asset->shelf->status === 'active';

        return $catalogReady && $shelfReady ? 'available' : 'unprocessed';
    }

    private function insertMovement(
        MaintenanceRecord $record,
        Asset $asset,
        string $movementType,
        int $userId,
        string $notes
    ): void {
        DB::table('stock_movements')->insert([
            'movement_code' => 'MOV-'.str_replace('-', '', (string) Str::uuid()),
            'item_id' => $asset->item_id,
            'asset_id' => $asset->id,
            'movement_type' => $movementType,
            'quantity' => 1,
            'from_location_id' => $movementType === 'maintenance_out' ? $asset->current_location_id : null,
            'to_location_id' => $movementType === 'maintenance_in' ? $asset->current_location_id : null,
            'reference_type' => 'maintenance',
            'reference_id' => $record->id,
            'movement_date' => now(),
            'created_by' => $userId,
            'notes' => $notes.' Kode: '.$record->maintenance_code,
            'created_at' => now(),
        ]);
    }

    private function nextCode(string $date): string
    {
        $dateCode = str_replace('-', '', substr($date, 0, 10));
        $prefix = "MNT-{$dateCode}-";

        $lastCode = (string) MaintenanceRecord::query()
            ->where('maintenance_code', 'like', $prefix.'%')
            ->orderByDesc('maintenance_code')
            ->lockForUpdate()
            ->value('maintenance_code');

        $sequence = $lastCode !== '' ? ((int) substr($lastCode, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    private function databaseMessage(Throwable $exception): string
    {
        report($exception);

        if ($exception instanceof RuntimeException || $exception instanceof ValidationException) {
            return $exception->getMessage();
        }

        return 'Data pemeliharaan belum dapat diproses. Periksa kembali data lalu coba lagi.';
    }
}
