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
        $status = (string) $request->query('status');

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
            ->when(in_array($status, ['active', 'inactive'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
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
        $newStatus = $supplier->status === 'active' ? 'inactive' : 'active';

        $supplier->update(['status' => $newStatus]);

        $message = $newStatus === 'active'
            ? 'Supplier berhasil diaktifkan.'
            : 'Supplier berhasil dinonaktifkan. Data riwayat aset tetap tersimpan.';

        return back()->with('success', $message);
    }
}
