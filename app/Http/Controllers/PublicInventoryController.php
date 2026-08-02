<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicSite\StoreDamageReportRequest;
use App\Models\PublicDamageReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicInventoryController extends Controller
{
    public function general(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $items = DB::table('items')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->where('items.status', 'active')
            ->when($search !== '', fn ($query) => $query->where(function ($subQuery) use ($search): void {
                $subQuery
                    ->where('items.item_name', 'like', "%{$search}%")
                    ->orWhere('items.item_code', 'like', "%{$search}%")
                    ->orWhere('categories.category_name', 'like', "%{$search}%");
            }))
            ->select([
                'items.id',
                'items.item_code',
                'items.item_name',
                'items.item_type',
                'items.tracking_type',
                'categories.category_name',
            ])
            ->selectSub(function ($query): void {
                $query->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.item_id', 'items.id')
                    ->where('asset_status', '<>', 'disposed');
            }, 'asset_count')
            ->selectSub(function ($query): void {
                $query->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.item_id', 'items.id')
                    ->whereIn('condition_status', ['damaged', 'lost']);
            }, 'problem_count')
            ->selectSub(function ($query): void {
                $query->from('stock_balances')
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('stock_balances.item_id', 'items.id');
            }, 'stock_quantity')
            ->orderBy('items.item_name')
            ->paginate(15)
            ->withQueryString();

        $statistics = [
            'items' => DB::table('items')->where('status', 'active')->count(),
            'assets' => DB::table('assets')->where('asset_status', '<>', 'disposed')->count(),
            'damaged' => DB::table('assets')->where('condition_status', 'damaged')->count(),
            'locations' => DB::table('locations')->where('status', 'active')->count(),
        ];

        return view('public.inventory.general', compact('items', 'statistics'));
    }

    public function audit(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $condition = (string) $request->query('condition');
        $locationId = (int) $request->query('location');

        $assets = DB::table('assets')
            ->join('items', 'items.id', '=', 'assets.item_id')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->leftJoin('locations', 'locations.id', '=', 'assets.current_location_id')
            ->leftJoin('library_shelves', 'library_shelves.id', '=', 'assets.current_shelf_id')
            ->where('assets.asset_status', '<>', 'disposed')
            ->when($search !== '', fn ($query) => $query->where(function ($subQuery) use ($search): void {
                $subQuery
                    ->where('assets.asset_code', 'like', "%{$search}%")
                    ->orWhere('items.item_code', 'like', "%{$search}%")
                    ->orWhere('items.item_name', 'like', "%{$search}%")
                    ->orWhere('locations.location_name', 'like', "%{$search}%");
            }))
            ->when(
                in_array($condition, ['good', 'fair', 'damaged', 'lost'], true),
                fn ($query) => $query->where('assets.condition_status', $condition)
            )
            ->when($locationId > 0, fn ($query) => $query->where('assets.current_location_id', $locationId))
            ->select([
                'assets.asset_code',
                'assets.condition_status',
                'assets.asset_status',
                'items.item_code',
                'items.item_name',
                'items.item_type',
                'categories.category_name',
                'locations.location_name',
                'library_shelves.shelf_code',
                'assets.updated_at',
            ])
            ->orderBy('items.item_name')
            ->orderBy('assets.asset_code')
            ->paginate(25)
            ->withQueryString();

        $stockBalances = DB::table('stock_balances')
            ->join('items', 'items.id', '=', 'stock_balances.item_id')
            ->join('locations', 'locations.id', '=', 'stock_balances.location_id')
            ->where('items.status', 'active')
            ->where('items.tracking_type', 'quantity')
            ->when($search !== '', fn ($query) => $query->where(function ($subQuery) use ($search): void {
                $subQuery
                    ->where('items.item_code', 'like', "%{$search}%")
                    ->orWhere('items.item_name', 'like', "%{$search}%")
                    ->orWhere('locations.location_name', 'like', "%{$search}%");
            }))
            ->when($locationId > 0, fn ($query) => $query->where('locations.id', $locationId))
            ->orderBy('items.item_name')
            ->get([
                'items.item_code',
                'items.item_name',
                'locations.location_name',
                'stock_balances.quantity',
            ]);

        $locations = DB::table('locations')
            ->where('status', 'active')
            ->orderBy('location_name')
            ->get(['id', 'location_name']);

        return view('public.inventory.audit', compact('assets', 'stockBalances', 'locations'));
    }

    public function createDamageReport(): View
    {
        $items = DB::table('items')
            ->where('status', 'active')
            ->orderBy('item_name')
            ->get(['id', 'item_code', 'item_name']);

        $assets = DB::table('assets')
            ->join('items', 'items.id', '=', 'assets.item_id')
            ->where('assets.asset_status', '<>', 'disposed')
            ->orderBy('items.item_name')
            ->orderBy('assets.asset_code')
            ->limit(1500)
            ->get([
                'assets.id',
                'assets.item_id',
                'assets.asset_code',
                'items.item_name',
            ]);

        $locations = DB::table('locations')
            ->where('status', 'active')
            ->orderBy('location_name')
            ->get(['id', 'location_name']);

        return view('public.inventory.report-damage', compact('items', 'assets', 'locations'));
    }

    public function storeDamageReport(StoreDamageReportRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('damage-reports', 'public')
            : null;

        if (! empty($data['asset_id'])) {
            $asset = DB::table('assets')->where('id', $data['asset_id'])->first();
            $data['item_id'] = $asset?->item_id ?: ($data['item_id'] ?? null);
            $data['location_id'] = $asset?->current_location_id ?: ($data['location_id'] ?? null);
        }

        $report = PublicDamageReport::query()->create([
            'report_code' => 'RUS-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4)),
            'item_id' => $data['item_id'] ?? null,
            'asset_id' => $data['asset_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'reporter_name' => $data['reporter_name'] ?? null,
            'reporter_contact' => $data['reporter_contact'] ?? null,
            'issue_description' => $data['issue_description'],
            'photo_path' => $photoPath,
            'status' => 'submitted',
        ]);

        return redirect()
            ->route('public.inventory.report-damage')
            ->with(
                'success',
                "Laporan berhasil dikirim. Simpan kode laporan {$report->report_code}."
            );
    }
}
