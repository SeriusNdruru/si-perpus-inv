<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockOpnameRequest;
use App\Http\Requests\Inventory\UpdateStockOpnameItemsRequest;
use App\Models\Asset;
use App\Models\Location;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class StockOpnameController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const STATUSES = [
        'draft' => 'Draf',
        'in_progress' => 'Sedang diperiksa',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');
        $locationId = $request->integer('location_id');
        $dateFrom = (string) $request->query('date_from');
        $dateTo = (string) $request->query('date_to');

        $stockOpnames = StockOpname::query()
            ->with([
                'location:id,location_code,location_name',
                'creator:id,full_name',
                'approver:id,full_name',
            ])
            ->withCount([
                'items as total_lines_count',
                'items as checked_lines_count' => fn ($query) => $query->whereNotNull('checked_at'),
                'items as issue_lines_count' => fn ($query) => $query->where('finding_status', '<>', 'matched'),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('opname_code', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('location', function ($locationQuery) use ($search): void {
                            $locationQuery
                                ->where('location_code', 'like', "%{$search}%")
                                ->orWhere('location_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when(array_key_exists($status, self::STATUSES), fn ($query) => $query->where('status', $status))
            ->when($locationId > 0, fn ($query) => $query->where('location_id', $locationId))
            ->when($this->isDate($dateFrom), fn ($query) => $query->whereDate('opname_date', '>=', $dateFrom))
            ->when($this->isDate($dateTo), fn ($query) => $query->whereDate('opname_date', '<=', $dateTo))
            ->orderByDesc('opname_date')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $summary = [
            'total' => StockOpname::query()->count(),
            'pending' => StockOpname::query()->whereIn('status', ['draft', 'in_progress'])->count(),
            'completed' => StockOpname::query()->where('status', 'completed')->count(),
            'issues' => StockOpnameItem::query()
                ->whereHas('stockOpname', fn ($query) => $query->where('status', 'completed'))
                ->where('finding_status', '<>', 'matched')
                ->count(),
        ];

        return view('inventory.stock-opnames.index', [
            'stockOpnames' => $stockOpnames,
            'summary' => $summary,
            'statuses' => self::STATUSES,
            'locations' => $this->activeLocations(),
        ]);
    }

    public function create(): View
    {
        return view('inventory.stock-opnames.create', [
            'locations' => $this->activeLocations(),
        ]);
    }

    public function store(StoreStockOpnameRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $locationId = (int) $data['location_id'];

        $existing = StockOpname::query()
            ->where('location_id', $locationId)
            ->whereIn('status', ['draft', 'in_progress'])
            ->first();

        if ($existing !== null) {
            return redirect()
                ->route('inventory.stock-opnames.show', $existing)
                ->with('error', 'Lokasi tersebut masih memiliki stock opname yang belum diselesaikan.');
        }

        try {
            $stockOpnameId = DB::transaction(function () use ($data, $locationId, $request): int {
                $stockOpname = StockOpname::query()->create([
                    'opname_code' => $this->nextCode((string) $data['opname_date']),
                    'location_id' => $locationId,
                    'opname_date' => $data['opname_date'],
                    'status' => 'draft',
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                $lines = $this->buildInitialLines($locationId, (int) $stockOpname->id);

                if ($lines === []) {
                    throw new RuntimeException('Lokasi ini belum memiliki aset atau saldo stok yang dapat diperiksa.');
                }

                DB::table('stock_opname_items')->insert($lines);

                return (int) $stockOpname->id;
            }, 3);
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['database' => $this->databaseMessage($exception)]);
        }

        return redirect()
            ->route('inventory.stock-opnames.show', $stockOpnameId)
            ->with('success', 'Draf stock opname berhasil dibuat. Data awal diambil dari lokasi terpilih.');
    }

    public function show(StockOpname $stockOpname): View
    {
        $stockOpname->load([
            'location:id,location_code,location_name',
            'creator:id,full_name',
            'approver:id,full_name',
        ]);

        $lines = $this->linesFor($stockOpname);

        return view('inventory.stock-opnames.show', [
            'stockOpname' => $stockOpname,
            'lines' => $lines,
            'summary' => $this->lineSummary($lines),
        ]);
    }

    public function edit(StockOpname $stockOpname): View|RedirectResponse
    {
        if (in_array($stockOpname->status, ['completed', 'cancelled'], true)) {
            return redirect()
                ->route('inventory.stock-opnames.show', $stockOpname)
                ->with('error', 'Stock opname yang sudah selesai atau dibatalkan tidak dapat diubah.');
        }

        $stockOpname->load('location:id,location_code,location_name');

        return view('inventory.stock-opnames.edit', [
            'stockOpname' => $stockOpname,
            'lines' => $this->linesFor($stockOpname),
        ]);
    }

    public function update(UpdateStockOpnameItemsRequest $request, StockOpname $stockOpname): RedirectResponse
    {
        if (in_array($stockOpname->status, ['completed', 'cancelled'], true)) {
            return redirect()
                ->route('inventory.stock-opnames.show', $stockOpname)
                ->with('error', 'Stock opname yang sudah selesai atau dibatalkan tidak dapat diubah.');
        }

        $submitted = collect($request->validated('items'));
        $submittedIds = $submitted->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $rows = StockOpnameItem::query()
            ->where('stock_opname_id', $stockOpname->id)
            ->whereIn('id', $submittedIds)
            ->get()
            ->keyBy('id');

        $totalRows = StockOpnameItem::query()
            ->where('stock_opname_id', $stockOpname->id)
            ->count();

        if ($rows->count() !== $submitted->count() || $submitted->count() !== $totalRows) {
            throw ValidationException::withMessages([
                'items' => 'Data pemeriksaan tidak lengkap atau tidak sesuai dengan stock opname ini. Muat ulang halaman lalu coba lagi.',
            ]);
        }

        DB::transaction(function () use ($submitted, $rows, $stockOpname, $request): void {
            foreach ($submitted as $index => $input) {
                /** @var StockOpnameItem $row */
                $row = $rows->get((int) $input['id']);
                $actual = round((float) $input['actual_quantity'], 2);
                $finding = 'matched';

                if ($row->asset_id !== null) {
                    if (! in_array($actual, [0.0, 1.0], true)) {
                        throw ValidationException::withMessages([
                            "items.{$index}.actual_quantity" => 'Jumlah fisik untuk aset individual hanya boleh 0 atau 1.',
                        ]);
                    }

                    $finding = $actual === 0.0
                        ? 'missing'
                        : (($input['finding_status'] ?? 'matched') === 'damaged' ? 'damaged' : 'matched');
                } else {
                    $difference = round($actual - (float) $row->expected_quantity, 2);
                    $finding = match (true) {
                        $difference > 0 => 'surplus',
                        $difference < 0 => 'shortage',
                        default => 'matched',
                    };
                }

                $row->update([
                    'actual_quantity' => $actual,
                    'difference_quantity' => round($actual - (float) $row->expected_quantity, 2),
                    'finding_status' => $finding,
                    'notes' => $input['notes'] ?? null,
                    'checked_by' => $request->user()->id,
                    'checked_at' => now(),
                ]);
            }

            if ($stockOpname->status === 'draft') {
                $stockOpname->update(['status' => 'in_progress']);
            }
        }, 3);

        return redirect()
            ->route('inventory.stock-opnames.show', $stockOpname)
            ->with('success', 'Hasil pemeriksaan fisik berhasil disimpan.');
    }

    public function start(StockOpname $stockOpname): RedirectResponse
    {
        if ($stockOpname->status !== 'draft') {
            return back()->with('error', 'Hanya stock opname berstatus draf yang dapat dimulai.');
        }

        $stockOpname->update(['status' => 'in_progress']);

        return redirect()
            ->route('inventory.stock-opnames.edit', $stockOpname)
            ->with('success', 'Stock opname dimulai. Masukkan hasil pemeriksaan fisik setiap baris.');
    }

    public function complete(StockOpname $stockOpname, Request $request): RedirectResponse
    {
        if (! in_array($stockOpname->status, ['draft', 'in_progress'], true)) {
            return back()->with('error', 'Stock opname ini tidak dapat diselesaikan.');
        }

        $unchecked = $stockOpname->items()->whereNull('checked_at')->count();
        if ($unchecked > 0) {
            return back()->with('error', "Masih ada {$unchecked} baris yang belum diperiksa.");
        }

        try {
            DB::transaction(function () use ($stockOpname, $request): void {
                /** @var StockOpname $lockedOpname */
                $lockedOpname = StockOpname::query()
                    ->lockForUpdate()
                    ->findOrFail($stockOpname->id);

                if (! in_array($lockedOpname->status, ['draft', 'in_progress'], true)) {
                    throw new RuntimeException('Status stock opname berubah. Muat ulang halaman.');
                }

                $lines = StockOpnameItem::query()
                    ->where('stock_opname_id', $lockedOpname->id)
                    ->with(['asset', 'item'])
                    ->lockForUpdate()
                    ->get();

                foreach ($lines as $line) {
                    if ($line->asset_id !== null) {
                        $this->finalizeAssetLine($lockedOpname, $line, (int) $request->user()->id);
                    } else {
                        $this->finalizeQuantityLine($lockedOpname, $line, (int) $request->user()->id);
                    }
                }

                $lockedOpname->update([
                    'status' => 'completed',
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);

                DB::table('audit_logs')->insert([
                    'user_id' => $request->user()->id,
                    'action' => 'approve',
                    'module_name' => 'inventory_stock_opname',
                    'table_name' => 'stock_opnames',
                    'record_id' => $lockedOpname->id,
                    'old_data' => json_encode(['status' => $stockOpname->status], JSON_UNESCAPED_UNICODE),
                    'new_data' => json_encode(['status' => 'completed'], JSON_UNESCAPED_UNICODE),
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                    'created_at' => now(),
                ]);
            }, 3);
        } catch (Throwable $exception) {
            return back()->with('error', $this->databaseMessage($exception));
        }

        return redirect()
            ->route('inventory.stock-opnames.show', $stockOpname)
            ->with('success', 'Stock opname selesai. Selisih stok dan status aset telah diterapkan.');
    }

    public function cancel(StockOpname $stockOpname): RedirectResponse
    {
        if (! in_array($stockOpname->status, ['draft', 'in_progress'], true)) {
            return back()->with('error', 'Stock opname yang sudah selesai tidak dapat dibatalkan.');
        }

        $stockOpname->update(['status' => 'cancelled']);

        return redirect()
            ->route('inventory.stock-opnames.show', $stockOpname)
            ->with('success', 'Stock opname dibatalkan. Tidak ada saldo atau status aset yang diubah.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildInitialLines(int $locationId, int $stockOpnameId): array
    {
        $now = now();
        $lines = [];

        $assets = Asset::query()
            ->where('current_location_id', $locationId)
            ->whereIn('asset_status', ['unprocessed', 'available', 'reserved', 'damaged'])
            ->whereHas('item', function ($query): void {
                $query->where('status', 'active')->where('tracking_type', 'asset');
            })
            ->orderBy('item_id')
            ->orderBy('asset_code')
            ->get(['id', 'item_id', 'condition_status']);

        foreach ($assets as $asset) {
            $finding = $asset->condition_status === 'damaged' ? 'damaged' : 'matched';

            $lines[] = [
                'stock_opname_id' => $stockOpnameId,
                'item_id' => $asset->item_id,
                'asset_id' => $asset->id,
                'expected_quantity' => 1,
                'actual_quantity' => 1,
                'difference_quantity' => 0,
                'finding_status' => $finding,
                'notes' => null,
                'checked_by' => null,
                'checked_at' => null,
            ];
        }

        $balances = DB::table('stock_balances')
            ->join('items', 'items.id', '=', 'stock_balances.item_id')
            ->where('stock_balances.location_id', $locationId)
            ->where('items.status', 'active')
            ->where('items.tracking_type', 'quantity')
            ->where('stock_balances.quantity', '>', 0)
            ->orderBy('items.item_name')
            ->get(['stock_balances.item_id', 'stock_balances.quantity']);

        foreach ($balances as $balance) {
            $quantity = round((float) $balance->quantity, 2);

            $lines[] = [
                'stock_opname_id' => $stockOpnameId,
                'item_id' => $balance->item_id,
                'asset_id' => null,
                'expected_quantity' => $quantity,
                'actual_quantity' => $quantity,
                'difference_quantity' => 0,
                'finding_status' => 'matched',
                'notes' => null,
                'checked_by' => null,
                'checked_at' => null,
            ];
        }

        return $lines;
    }

    private function finalizeAssetLine(StockOpname $stockOpname, StockOpnameItem $line, int $userId): void
    {
        /** @var Asset|null $asset */
        $asset = Asset::query()->lockForUpdate()->find($line->asset_id);

        if ($asset === null) {
            throw new RuntimeException("Aset pada baris {$line->id} tidak lagi tersedia.");
        }

        if (in_array($asset->asset_status, ['borrowed', 'maintenance', 'disposed'], true)) {
            throw new RuntimeException("Status aset {$asset->asset_code} berubah menjadi {$asset->asset_status}. Stock opname tidak dapat diselesaikan.");
        }

        if ($line->finding_status === 'missing') {
            if ($asset->current_shelf_id !== null) {
                DB::table('asset_shelf_history')->insert([
                    'asset_id' => $asset->id,
                    'old_shelf_id' => $asset->current_shelf_id,
                    'new_shelf_id' => null,
                    'changed_by' => $userId,
                    'changed_at' => now(),
                    'notes' => 'Rak dilepas karena aset tidak ditemukan saat stock opname '.$stockOpname->opname_code.'.',
                ]);
            }

            $asset->update([
                'condition_status' => 'lost',
                'asset_status' => 'lost',
                'current_location_id' => null,
                'current_shelf_id' => null,
                'updated_by' => $userId,
            ]);

            $this->insertMovement(
                $stockOpname,
                (int) $line->item_id,
                (int) $asset->id,
                1,
                (int) $stockOpname->location_id,
                null,
                $userId,
                'Aset tidak ditemukan saat stock opname.'
            );
        } elseif ($line->finding_status === 'damaged') {
            $asset->update([
                'condition_status' => 'damaged',
                'asset_status' => 'damaged',
                'updated_by' => $userId,
            ]);

            $this->insertMovement(
                $stockOpname,
                (int) $line->item_id,
                (int) $asset->id,
                1,
                (int) $stockOpname->location_id,
                (int) $stockOpname->location_id,
                $userId,
                'Aset ditemukan dalam kondisi rusak saat stock opname.'
            );
        }
    }

    private function finalizeQuantityLine(StockOpname $stockOpname, StockOpnameItem $line, int $userId): void
    {
        $balance = DB::table('stock_balances')
            ->where('item_id', $line->item_id)
            ->where('location_id', $stockOpname->location_id)
            ->lockForUpdate()
            ->first();

        $currentQuantity = round((float) ($balance?->quantity ?? 0), 2);
        $expectedQuantity = round((float) $line->expected_quantity, 2);

        if (abs($currentQuantity - $expectedQuantity) > 0.005) {
            throw new RuntimeException(
                "Saldo {$line->item?->item_name} berubah sejak stock opname dibuat. Batalkan stock opname ini lalu buat kembali agar data tidak tertimpa."
            );
        }

        $actualQuantity = round((float) $line->actual_quantity, 2);
        $difference = round($actualQuantity - $expectedQuantity, 2);

        DB::table('stock_balances')->updateOrInsert(
            [
                'item_id' => $line->item_id,
                'location_id' => $stockOpname->location_id,
            ],
            [
                'quantity' => $actualQuantity,
                'updated_at' => now(),
            ]
        );

        if (abs($difference) > 0.005) {
            $this->insertMovement(
                $stockOpname,
                (int) $line->item_id,
                null,
                abs($difference),
                $difference < 0 ? (int) $stockOpname->location_id : null,
                $difference > 0 ? (int) $stockOpname->location_id : null,
                $userId,
                $difference > 0
                    ? 'Penyesuaian stok lebih berdasarkan hasil stock opname.'
                    : 'Penyesuaian stok kurang berdasarkan hasil stock opname.'
            );
        }
    }

    private function insertMovement(
        StockOpname $stockOpname,
        int $itemId,
        ?int $assetId,
        float $quantity,
        ?int $fromLocationId,
        ?int $toLocationId,
        int $userId,
        string $notes
    ): void {
        DB::table('stock_movements')->insert([
            'movement_code' => 'MOV-'.str_replace('-', '', (string) Str::uuid()),
            'item_id' => $itemId,
            'asset_id' => $assetId,
            'movement_type' => 'opname',
            'quantity' => $quantity,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'reference_type' => 'stock_opname',
            'reference_id' => $stockOpname->id,
            'movement_date' => now(),
            'created_by' => $userId,
            'notes' => $notes.' Kode: '.$stockOpname->opname_code,
            'created_at' => now(),
        ]);
    }

    private function nextCode(string $date): string
    {
        $dateCode = str_replace('-', '', $date);
        $prefix = "OPN-{$dateCode}-";

        $lastCode = (string) StockOpname::query()
            ->where('opname_code', 'like', $prefix.'%')
            ->orderByDesc('opname_code')
            ->value('opname_code');

        $sequence = 1;
        if ($lastCode !== '') {
            $sequence = ((int) substr($lastCode, -4)) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return Collection<int, StockOpnameItem>
     */
    private function linesFor(StockOpname $stockOpname): Collection
    {
        return StockOpnameItem::query()
            ->with([
                'item:id,item_code,item_name,tracking_type,unit_id',
                'item.unit:id,unit_code,unit_name',
                'asset:id,asset_code,barcode,condition_status,asset_status',
                'checker:id,full_name',
            ])
            ->where('stock_opname_id', $stockOpname->id)
            ->orderBy('item_id')
            ->orderByRaw('CASE WHEN asset_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('asset_id')
            ->get();
    }

    /**
     * @param Collection<int, StockOpnameItem> $lines
     * @return array<string, int|float>
     */
    private function lineSummary(Collection $lines): array
    {
        return [
            'total' => $lines->count(),
            'checked' => $lines->whereNotNull('checked_at')->count(),
            'matched' => $lines->where('finding_status', 'matched')->count(),
            'surplus' => $lines->where('finding_status', 'surplus')->count(),
            'shortage' => $lines->where('finding_status', 'shortage')->count(),
            'damaged' => $lines->where('finding_status', 'damaged')->count(),
            'missing' => $lines->where('finding_status', 'missing')->count(),
            'expected' => round((float) $lines->sum('expected_quantity'), 2),
            'actual' => round((float) $lines->sum('actual_quantity'), 2),
        ];
    }

    /**
     * @return Collection<int, Location>
     */
    private function activeLocations(): Collection
    {
        return Location::query()
            ->where('status', 'active')
            ->orderBy('location_name')
            ->get(['id', 'location_code', 'location_name', 'location_type']);
    }

    private function isDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function databaseMessage(Throwable $exception): string
    {
        $message = (string) ($exception->getPrevious()?->getMessage() ?? $exception->getMessage());
        $message = preg_replace('/^SQLSTATE\[[^\]]+\]:.*?:\s*\d+\s*/', '', $message) ?: $message;

        if (str_contains(strtolower($message), 'duplicate entry')) {
            return 'Kode stock opname sudah digunakan. Muat ulang halaman lalu coba lagi.';
        }

        return mb_substr($message, 0, 500);
    }
}
