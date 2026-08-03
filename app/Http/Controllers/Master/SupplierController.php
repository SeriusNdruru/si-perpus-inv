<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreSupplierRequest;
use App\Http\Requests\Master\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $suppliers = Supplier::query()
            ->select('suppliers.*')
            ->selectSub(function ($query): void {
                $query
                    ->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.supplier_id', 'suppliers.id');
            }, 'assets_count')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('supplier_code', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->where('suppliers.status', 'active')
            ->orderBy('supplier_name')
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'total' => Supplier::query()->count(),
            'active' => Supplier::query()->where('status', 'active')->count(),
            'inactive' => Supplier::query()->where('status', 'inactive')->count(),
            'used' => Supplier::query()
                ->whereExists(function ($query): void {
                    $query
                        ->selectRaw('1')
                        ->from('assets')
                        ->whereColumn('assets.supplier_id', 'suppliers.id');
                })
                ->count(),
        ];

        return view('master.suppliers.index', compact('suppliers', 'summary'));
    }

    public function deleted(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $suppliers = Supplier::query()
            ->select('suppliers.*')
            ->selectSub(fn ($query) => $query->from('assets')->selectRaw('COUNT(*)')->whereColumn('assets.supplier_id', 'suppliers.id'), 'assets_count')
            ->where('suppliers.status', 'inactive')
            ->when($search !== '', fn ($query) => $query->where(fn ($subQuery) => $subQuery->where('supplier_code', 'like', "%{$search}%")->orWhere('supplier_name', 'like', "%{$search}%")->orWhere('contact_person', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderByDesc('updated_at')->paginate(10)->withQueryString();
        return view('master.suppliers.deleted', compact('suppliers'));
    }

    public function create(): View
    {
        return view('master.suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::query()->create($request->validated());

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('master.suppliers.edit', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier berhasil diperbarui.');
    }

    public function toggleStatus(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['status' => 'inactive']);
        return redirect()->route('suppliers.index')->with('success', 'Supplier dipindahkan ke Daftar Hapus. Riwayat aset tetap tersimpan.');
    }

    public function restore(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['status' => 'active']);
        return back()->with('success', 'Supplier berhasil dipulihkan.');
    }
}
