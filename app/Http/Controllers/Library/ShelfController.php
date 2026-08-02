<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\StoreShelfRequest;
use App\Http\Requests\Library\UpdateShelfRequest;
use App\Models\LibraryShelf;
use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShelfController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $locationId = $request->filled('location_id') ? (int) $request->query('location_id') : null;
        $status = (string) $request->query('status');

        $shelves = LibraryShelf::query()
            ->with('location:id,parent_id,location_code,location_name,location_type')
            ->select('library_shelves.*')
            ->selectSub(function ($query): void {
                $query->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.current_shelf_id', 'library_shelves.id')
                    ->whereNotIn('assets.asset_status', ['disposed', 'lost']);
            }, 'occupied_count')
            ->selectSub(function ($query): void {
                $query->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.current_shelf_id', 'library_shelves.id')
                    ->where('assets.asset_status', 'available');
            }, 'available_count')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('shelf_code', 'like', "%{$search}%")
                        ->orWhere('shelf_name', 'like', "%{$search}%")
                        ->orWhere('classification_range', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->orderBy('shelf_code')
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'total' => LibraryShelf::query()->count(),
            'active' => LibraryShelf::query()->where('status', 'active')->count(),
            'occupied' => LibraryShelf::query()
                ->join('assets', 'assets.current_shelf_id', '=', 'library_shelves.id')
                ->whereNotIn('assets.asset_status', ['disposed', 'lost'])
                ->count('assets.id'),
            'capacity' => (int) LibraryShelf::query()->where('status', 'active')->sum('capacity'),
        ];

        return view('library.shelves.index', [
            'shelves' => $shelves,
            'summary' => $summary,
            'locations' => $this->locationOptions(),
        ]);
    }

    public function create(): View
    {
        return view('library.shelves.create', [
            'locations' => $this->locationOptions(),
        ]);
    }

    public function store(StoreShelfRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        LibraryShelf::query()->create($data);

        return redirect()
            ->route('library.shelves.index')
            ->with('success', 'Rak perpustakaan berhasil ditambahkan.');
    }

    public function edit(LibraryShelf $shelf): View
    {
        return view('library.shelves.edit', [
            'shelf' => $shelf,
            'locations' => $this->locationOptions($shelf->location_id),
            'occupiedCount' => $shelf->assets()
                ->whereNotIn('asset_status', ['disposed', 'lost'])
                ->count(),
        ]);
    }

    public function update(UpdateShelfRequest $request, LibraryShelf $shelf): RedirectResponse
    {
        $data = $request->validated();
        $occupiedCount = $shelf->assets()
            ->whereNotIn('asset_status', ['disposed', 'lost'])
            ->count();

        if ($data['capacity'] !== null && $data['capacity'] < $occupiedCount) {
            return back()
                ->withInput()
                ->withErrors([
                    'capacity' => "Kapasitas tidak boleh kurang dari {$occupiedCount} eksemplar yang sudah menempati rak.",
                ]);
        }

        if ($data['status'] === 'inactive' && $occupiedCount > 0) {
            return back()
                ->withInput()
                ->withErrors([
                    'status' => 'Rak tidak dapat dinonaktifkan karena masih ditempati eksemplar buku.',
                ]);
        }

        $shelf->update($data);

        return redirect()
            ->route('library.shelves.index')
            ->with('success', 'Rak perpustakaan berhasil diperbarui.');
    }

    public function toggleStatus(LibraryShelf $shelf): RedirectResponse
    {
        $newStatus = $shelf->status === 'active' ? 'inactive' : 'active';

        if ($newStatus === 'inactive' && $shelf->assets()->whereNotIn('asset_status', ['disposed', 'lost'])->exists()) {
            return back()->with('error', 'Rak tidak dapat dinonaktifkan karena masih ditempati eksemplar buku.');
        }

        $shelf->update(['status' => $newStatus]);

        return back()->with(
            'success',
            $newStatus === 'active'
                ? 'Rak perpustakaan berhasil diaktifkan.'
                : 'Rak perpustakaan berhasil dinonaktifkan.'
        );
    }

    /**
     * @return Collection<int, Location>
     */
    private function locationOptions(?int $includeLocationId = null): Collection
    {
        return Location::query()
            ->where(function ($query) use ($includeLocationId): void {
                $query->where('status', 'active');

                if ($includeLocationId !== null) {
                    $query->orWhere('id', $includeLocationId);
                }
            })
            ->orderByRaw("FIELD(location_type, 'room', 'library', 'cabinet', 'floor', 'building', 'warehouse', 'other')")
            ->orderBy('location_name')
            ->get(['id', 'location_code', 'location_name', 'location_type', 'status']);
    }
}
