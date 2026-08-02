<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreUnitRequest;
use App\Http\Requests\Master\UpdateUnitRequest;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');

        $units = Unit::query()
            ->select('units.*')
            ->selectSub(function ($query): void {
                $query
                    ->from('items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('items.unit_id', 'units.id');
            }, 'items_count')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('unit_code', 'like', "%{$search}%")
                        ->orWhere('unit_name', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->orderBy('unit_name')
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'total' => Unit::query()->count(),
            'active' => Unit::query()->where('status', 'active')->count(),
            'inactive' => Unit::query()->where('status', 'inactive')->count(),
            'used' => Unit::query()
                ->whereExists(function ($query): void {
                    $query
                        ->selectRaw('1')
                        ->from('items')
                        ->whereColumn('items.unit_id', 'units.id');
                })
                ->count(),
        ];

        return view('master.units.index', compact('units', 'summary'));
    }

    public function create(): View
    {
        return view('master.units.create');
    }

    public function store(StoreUnitRequest $request): RedirectResponse
    {
        Unit::query()->create($request->validated());

        return redirect()
            ->route('units.index')
            ->with('success', 'Satuan berhasil ditambahkan.');
    }

    public function edit(Unit $unit): View
    {
        return view('master.units.edit', compact('unit'));
    }

    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['status'] === 'inactive' && $this->hasActiveItems($unit)) {
            return back()
                ->withInput()
                ->withErrors([
                    'status' => 'Satuan masih digunakan oleh barang aktif sehingga belum dapat dinonaktifkan.',
                ]);
        }

        $unit->update($validated);

        return redirect()
            ->route('units.index')
            ->with('success', 'Satuan berhasil diperbarui.');
    }

    public function toggleStatus(Unit $unit): RedirectResponse
    {
        $newStatus = $unit->status === 'active' ? 'inactive' : 'active';

        if ($newStatus === 'inactive' && $this->hasActiveItems($unit)) {
            return back()->with('error', 'Satuan tidak dapat dinonaktifkan karena masih digunakan oleh barang aktif.');
        }

        $unit->update(['status' => $newStatus]);

        $message = $newStatus === 'active'
            ? 'Satuan berhasil diaktifkan.'
            : 'Satuan berhasil dinonaktifkan.';

        return back()->with('success', $message);
    }

    private function hasActiveItems(Unit $unit): bool
    {
        return DB::table('items')
            ->where('unit_id', $unit->id)
            ->where('status', 'active')
            ->exists();
    }
}
