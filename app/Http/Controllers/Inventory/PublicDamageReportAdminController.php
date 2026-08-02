<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\UpdatePublicDamageReportRequest;
use App\Models\PublicDamageReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicDamageReportAdminController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');

        $reports = PublicDamageReport::query()
            ->with([
                'item:id,item_code,item_name',
                'asset:id,asset_code',
                'location:id,location_name',
                'handler:id,full_name',
            ])
            ->when($search !== '', fn ($query) => $query->where(function ($subQuery) use ($search): void {
                $subQuery
                    ->where('report_code', 'like', "%{$search}%")
                    ->orWhere('reporter_name', 'like', "%{$search}%")
                    ->orWhere('issue_description', 'like', "%{$search}%")
                    ->orWhereHas('item', fn ($itemQuery) => $itemQuery->where('item_name', 'like', "%{$search}%"))
                    ->orWhereHas('asset', fn ($assetQuery) => $assetQuery->where('asset_code', 'like', "%{$search}%"));
            }))
            ->when(
                in_array($status, ['submitted', 'reviewed', 'in_progress', 'resolved', 'rejected'], true),
                fn ($query) => $query->where('status', $status)
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('inventory.public-damage-reports.index', compact('reports'));
    }

    public function show(PublicDamageReport $publicDamageReport): View
    {
        $publicDamageReport->load([
            'item:id,item_code,item_name,item_type',
            'asset:id,asset_code,condition_status,asset_status',
            'location:id,location_name',
            'handler:id,full_name',
        ]);

        return view('inventory.public-damage-reports.show', compact('publicDamageReport'));
    }

    public function update(
        UpdatePublicDamageReportRequest $request,
        PublicDamageReport $publicDamageReport
    ): RedirectResponse {
        $data = $request->validated();

        $publicDamageReport->update([
            'status' => $data['status'],
            'admin_notes' => $data['admin_notes'] ?? null,
            'handled_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status laporan kerusakan berhasil diperbarui.');
    }
}
