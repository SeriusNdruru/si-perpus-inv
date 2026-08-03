<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreItemRequest;
use App\Http\Requests\Inventory\UpdateItemRequest;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ItemController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const ITEM_TYPES = [
        'book' => 'Buku',
        'equipment' => 'Peralatan',
        'electronic' => 'Elektronik',
        'furniture' => 'Furnitur',
        'consumable' => 'Barang Habis Pakai',
        'other' => 'Lainnya',
    ];

    /**
     * @var array<string, string>
     */
    private const TRACKING_TYPES = [
        'asset' => 'Per Aset',
        'quantity' => 'Berdasarkan Jumlah',
    ];

    /**
     * @var array<string, string>
     */
    private const ACQUISITION_SOURCES = [
        'purchase' => 'Pembelian',
        'donation' => 'Donasi',
        'grant' => 'Hibah',
        'transfer' => 'Transfer',
        'other' => 'Lainnya',
    ];

    public function index(Request $request): View
    {
        $items = $this->filteredItemsQuery($request)
            ->where('items.status', 'active')
            ->orderByDesc('items.created_at')
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'active' => Item::query()->where('status', 'active')->count(),
            'deleted' => Item::query()->where('status', 'inactive')->count(),
            'books' => Item::query()
                ->where('item_type', 'book')
                ->where('status', 'active')
                ->count(),
            'unprocessed_books' => Asset::query()
                ->where('asset_status', 'unprocessed')
                ->whereHas('item', fn ($query) => $query
                    ->where('item_type', 'book')
                    ->where('status', 'active'))
                ->count(),
        ];

        return view('inventory.items.index', [
            'items' => $items,
            'summary' => $summary,
            'itemTypes' => self::ITEM_TYPES,
            'trackingTypes' => self::TRACKING_TYPES,
        ]);
    }

    public function deleted(Request $request): View
    {
        $items = $this->filteredItemsQuery($request)
            ->where('items.status', 'inactive')
            ->orderByDesc('items.updated_at')
            ->paginate(10)
            ->withQueryString();

        return view('inventory.items.deleted', [
            'items' => $items,
            'deletedCount' => Item::query()->where('status', 'inactive')->count(),
            'itemTypes' => self::ITEM_TYPES,
            'trackingTypes' => self::TRACKING_TYPES,
        ]);
    }

    public function create(): View
    {
        return view('inventory.items.create', $this->formOptions());
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = (int) $request->user()->id;
        $assetCodeSeparator = $this->assetCodeSeparator();
        $imagePath = $request->file('item_image')->store('item-images', 'public');

        try {
            $itemId = DB::transaction(function () use ($data, $userId, $assetCodeSeparator, $imagePath): int {
                $item = Item::query()->create([
                    'item_code' => $data['item_code'],
                    'item_name' => $data['item_name'],
                    'item_type' => $data['item_type'],
                    'tracking_type' => $data['tracking_type'],
                    'category_id' => $data['category_id'],
                    'unit_id' => $data['unit_id'],
                    'description' => $data['description'] ?? null,
                    'image_path' => $imagePath,
                    'minimum_stock' => $data['minimum_stock'],
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                if ($item->item_type === 'book') {
                    DB::table('book_details')
                        ->where('item_id', $item->id)
                        ->update([
                            'cover_path' => $imagePath,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                }

                if ($data['tracking_type'] === 'asset') {
                    $quantity = (int) $data['quantity'];

                    for ($sequence = 1; $sequence <= $quantity; $sequence++) {
                        $assetCode = $data['item_code'].$assetCodeSeparator.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

                        $asset = Asset::query()->create([
                            'item_id' => $item->id,
                            'asset_code' => $assetCode,
                            'barcode' => $assetCode,
                            'condition_status' => 'good',
                            'asset_status' => 'available',
                            'acquisition_date' => $data['acquisition_date'] ?? null,
                            'acquisition_source' => $data['acquisition_source'],
                            'acquisition_price' => $data['acquisition_price'],
                            'supplier_id' => $data['supplier_id'],
                            'current_location_id' => $data['location_id'],
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ]);

                        DB::table('stock_movements')->insert([
                            'movement_code' => 'MOV-'.str_replace('-', '', (string) Str::uuid()),
                            'item_id' => $item->id,
                            'asset_id' => $asset->id,
                            'movement_type' => 'receipt',
                            'quantity' => 1,
                            'to_location_id' => $data['location_id'],
                            'reference_type' => 'initial_stock',
                            'reference_id' => $item->id,
                            'movement_date' => now(),
                            'created_by' => $userId,
                            'notes' => 'Penerimaan unit aset awal.',
                            'created_at' => now(),
                        ]);
                    }
                } else {
                    DB::table('stock_balances')->insert([
                        'item_id' => $item->id,
                        'location_id' => $data['location_id'],
                        'quantity' => $data['quantity'],
                        'updated_at' => now(),
                    ]);

                    DB::table('stock_movements')->insert([
                        'movement_code' => 'MOV-'.str_replace('-', '', (string) Str::uuid()),
                        'item_id' => $item->id,
                        'asset_id' => null,
                        'movement_type' => 'receipt',
                        'quantity' => $data['quantity'],
                        'to_location_id' => $data['location_id'],
                        'reference_type' => 'initial_stock',
                        'reference_id' => $item->id,
                        'movement_date' => now(),
                        'created_by' => $userId,
                        'notes' => 'Penerimaan stok awal berbasis jumlah.',
                        'created_at' => now(),
                    ]);
                }

                return (int) $item->id;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($imagePath);

            return back()
                ->withInput()
                ->withErrors(['database' => $this->databaseMessage($exception)]);
        }

        return redirect()
            ->route('inventory.items.show', $itemId)
            ->with('success', 'Barang, foto, dan stok awal berhasil ditambahkan.');
    }

    public function show(Item $item): View
    {
        $item->load([
            'category:id,category_code,category_name',
            'unit:id,unit_code,unit_name',
            'bookDetail:item_id,completion_status,isbn_10,isbn_13,publication_year,grade_level,classification_code,call_number,cover_path',
            'creator:id,full_name',
        ]);

        $assets = Asset::query()
            ->with([
                'location:id,location_code,location_name',
                'supplier:id,supplier_code,supplier_name',
                'shelf:id,shelf_code,shelf_name',
            ])
            ->where('item_id', $item->id)
            ->orderBy('asset_code')
            ->paginate(15, ['*'], 'assets_page');

        $stockBalances = StockBalance::query()
            ->with('location:id,location_code,location_name')
            ->where('item_id', $item->id)
            ->orderByDesc('quantity')
            ->get();

        $statusSummary = Asset::query()
            ->where('item_id', $item->id)
            ->select('asset_status', DB::raw('COUNT(*) AS total'))
            ->groupBy('asset_status')
            ->pluck('total', 'asset_status');

        return view('inventory.items.show', [
            'item' => $item,
            'assets' => $assets,
            'stockBalances' => $stockBalances,
            'statusSummary' => $statusSummary,
            'itemTypes' => self::ITEM_TYPES,
            'trackingTypes' => self::TRACKING_TYPES,
            'acquisitionSources' => self::ACQUISITION_SOURCES,
        ]);
    }

    public function edit(Item $item): View
    {
        return view('inventory.items.edit', array_merge(
            $this->formOptions(),
            ['item' => $item]
        ));
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        $data = $request->validated();

        if ($data['status'] === 'inactive' && ! $this->canDeactivate($item)) {
            return back()
                ->withInput()
                ->withErrors([
                    'status' => 'Barang tidak dapat dihapus karena masih memiliki aset aktif atau stok yang belum kosong.',
                ]);
        }

        $item->loadMissing('bookDetail');
        $oldItemImagePath = $item->image_path;
        $oldBookCoverPath = $item->bookDetail?->cover_path;
        $newImagePath = $request->hasFile('item_image')
            ? $request->file('item_image')->store('item-images', 'public')
            : null;

        try {
            DB::transaction(function () use ($data, $item, $request, $newImagePath): void {
                $values = [
                    'item_name' => $data['item_name'],
                    'category_id' => $data['category_id'],
                    'unit_id' => $data['unit_id'],
                    'description' => $data['description'] ?? null,
                    'minimum_stock' => $data['minimum_stock'],
                    'status' => $data['status'],
                    'updated_by' => $request->user()->id,
                ];

                if ($newImagePath !== null) {
                    $values['image_path'] = $newImagePath;
                }

                $item->update($values);

                if ($newImagePath !== null && $item->item_type === 'book') {
                    DB::table('book_details')
                        ->where('item_id', $item->id)
                        ->update([
                            'cover_path' => $newImagePath,
                            'updated_by' => $request->user()->id,
                            'updated_at' => now(),
                        ]);
                }
            }, 3);
        } catch (Throwable $exception) {
            if ($newImagePath !== null) {
                Storage::disk('public')->delete($newImagePath);
            }

            return back()
                ->withInput()
                ->withErrors(['database' => $this->databaseMessage($exception)]);
        }

        if ($newImagePath !== null) {
            $oldPaths = array_unique(array_filter([$oldItemImagePath, $oldBookCoverPath]));

            foreach ($oldPaths as $oldPath) {
                if ($oldPath !== $newImagePath) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
        }

        if ($item->status === 'inactive') {
            return redirect()
                ->route('inventory.deleted-items.index')
                ->with('success', 'Data barang berhasil diperbarui dan tetap berada di Daftar Hapus.');
        }

        return redirect()
            ->route('inventory.items.show', $item)
            ->with('success', 'Data barang dan foto berhasil diperbarui.');
    }

    public function toggleStatus(Item $item, Request $request): RedirectResponse
    {
        if ($item->status === 'inactive') {
            return redirect()
                ->route('inventory.deleted-items.index')
                ->with('error', 'Barang tersebut sudah berada di Daftar Hapus.');
        }

        if (! $this->canDeactivate($item)) {
            return back()->with(
                'error',
                'Barang tidak dapat dihapus karena masih memiliki aset aktif atau stok yang belum kosong.'
            );
        }

        $item->update([
            'status' => 'inactive',
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('inventory.items.index')
            ->with('success', 'Barang berhasil dihapus dari daftar aktif dan dipindahkan ke Daftar Hapus.');
    }

    public function restore(Item $item, Request $request): RedirectResponse
    {
        if ($item->status === 'active') {
            return redirect()
                ->route('inventory.items.index')
                ->with('error', 'Barang tersebut sudah aktif.');
        }

        $item->update([
            'status' => 'active',
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('inventory.deleted-items.index')
            ->with('success', 'Barang berhasil dipulihkan ke Daftar Barang.');
    }

    private function filteredItemsQuery(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $itemType = (string) $request->query('item_type');
        $trackingType = (string) $request->query('tracking_type');

        return Item::query()
            ->with([
                'category:id,category_code,category_name',
                'unit:id,unit_code,unit_name',
                'bookDetail:item_id,completion_status,cover_path',
            ])
            ->select('items.*')
            ->selectSub(function ($query): void {
                $query
                    ->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.item_id', 'items.id')
                    ->whereNotIn('assets.asset_status', ['disposed']);
            }, 'assets_count')
            ->selectSub(function ($query): void {
                $query
                    ->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.item_id', 'items.id')
                    ->where('assets.asset_status', 'available');
            }, 'available_assets_count')
            ->selectSub(function ($query): void {
                $query
                    ->from('stock_balances')
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('stock_balances.item_id', 'items.id');
            }, 'quantity_stock')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('item_code', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists($itemType, self::ITEM_TYPES), fn ($query) => $query->where('item_type', $itemType))
            ->when(array_key_exists($trackingType, self::TRACKING_TYPES), fn ($query) => $query->where('tracking_type', $trackingType));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::query()
                ->where('status', 'active')
                ->orderBy('category_name')
                ->get(['id', 'category_code', 'category_name', 'scope']),
            'units' => Unit::query()
                ->where('status', 'active')
                ->orderBy('unit_name')
                ->get(['id', 'unit_code', 'unit_name']),
            'suppliers' => Supplier::query()
                ->where('status', 'active')
                ->orderBy('supplier_name')
                ->get(['id', 'supplier_code', 'supplier_name']),
            'locations' => Location::query()
                ->where('status', 'active')
                ->orderBy('location_name')
                ->get(['id', 'location_code', 'location_name', 'location_type']),
            'itemTypes' => self::ITEM_TYPES,
            'trackingTypes' => self::TRACKING_TYPES,
            'acquisitionSources' => self::ACQUISITION_SOURCES,
        ];
    }

    private function assetCodeSeparator(): string
    {
        $separator = (string) DB::table('system_settings')
            ->where('setting_key', 'inventory.asset_code_separator')
            ->value('setting_value');

        return in_array($separator, ['-', '/', '.', '_'], true) ? $separator : '-';
    }

    private function canDeactivate(Item $item): bool
    {
        if ($item->tracking_type === 'asset') {
            return ! $item->assets()
                ->whereNotIn('asset_status', ['disposed', 'lost'])
                ->exists();
        }

        return (float) $item->stockBalances()->sum('quantity') <= 0;
    }

    private function databaseMessage(Throwable $exception): string
    {
        $message = (string) ($exception->getPrevious()?->getMessage() ?? $exception->getMessage());
        $message = preg_replace('/^SQLSTATE\[[^\]]+\]:.*?:\s*\d+\s*/', '', $message) ?: $message;

        if (str_contains(strtolower($message), 'duplicate entry')) {
            return 'Kode barang atau kode aset sudah digunakan. Periksa kembali kode yang dimasukkan.';
        }

        return 'Database menolak proses: '.mb_substr($message, 0, 300);
    }
}
