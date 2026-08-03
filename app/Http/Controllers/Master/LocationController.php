<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreLocationRequest;
use App\Http\Requests\Master\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const TYPE_LABELS = [
        'building' => 'Gedung',
        'floor' => 'Lantai',
        'room' => 'Ruangan',
        'warehouse' => 'Gudang',
        'cabinet' => 'Lemari',
        'other' => 'Lainnya',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $type = (string) $request->query('type');

        $locations = Location::query()
            ->with('parent:id,location_code,location_name')
            ->select('locations.*')
            ->selectSub(function ($query): void {
                $query
                    ->from('locations as children')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('children.parent_id', 'locations.id');
            }, 'children_count')
            ->selectSub(function ($query): void {
                $query
                    ->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.current_location_id', 'locations.id')
                    ->whereNotIn('assets.asset_status', ['disposed', 'lost']);
            }, 'assets_count')
            ->selectSub(function ($query): void {
                $query
                    ->from('library_shelves')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('library_shelves.location_id', 'locations.id');
            }, 'shelves_count')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('location_code', 'like', "%{$search}%")
                        ->orWhere('location_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists($type, self::TYPE_LABELS), function ($query) use ($type): void {
                $query->where('location_type', $type);
            })
            ->where('locations.status', 'active')
            ->orderBy('location_name')
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'total' => Location::query()->count(),
            'active' => Location::query()->where('status', 'active')->count(),
            'rooms' => Location::query()->whereIn('location_type', ['room', 'warehouse'])->count(),
            'used' => Location::query()
                ->where(function ($query): void {
                    $query
                        ->whereExists(function ($subQuery): void {
                            $subQuery
                                ->selectRaw('1')
                                ->from('assets')
                                ->whereColumn('assets.current_location_id', 'locations.id');
                        })
                        ->orWhereExists(function ($subQuery): void {
                            $subQuery
                                ->selectRaw('1')
                                ->from('library_shelves')
                                ->whereColumn('library_shelves.location_id', 'locations.id');
                        });
                })
                ->count(),
        ];

        return view('master.locations.index', [
            'locations' => $locations,
            'summary' => $summary,
            'typeLabels' => self::TYPE_LABELS,
        ]);
    }

    public function deleted(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $type = (string) $request->query('type');

        $locations = Location::query()
            ->with('parent:id,location_code,location_name')
            ->select('locations.*')
            ->selectSub(function ($query): void {
                $query
                    ->from('locations as children')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('children.parent_id', 'locations.id');
            }, 'children_count')
            ->selectSub(function ($query): void {
                $query
                    ->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.current_location_id', 'locations.id')
                    ->whereNotIn('assets.asset_status', ['disposed', 'lost']);
            }, 'assets_count')
            ->selectSub(function ($query): void {
                $query
                    ->from('library_shelves')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('library_shelves.location_id', 'locations.id');
            }, 'shelves_count')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('location_code', 'like', "%{$search}%")
                        ->orWhere('location_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists($type, self::TYPE_LABELS), function ($query) use ($type): void {
                $query->where('location_type', $type);
            })
            ->where('locations.status', 'inactive')
            ->orderBy('location_name')
            ->paginate(10)
            ->withQueryString();

        return view('master.locations.deleted', [
            'locations' => $locations,
            'typeLabels' => self::TYPE_LABELS,
        ]);
    }

    public function create(): View
    {
        return view('master.locations.create', [
            'parentLocations' => $this->parentOptions(),
            'typeLabels' => self::TYPE_LABELS,
        ]);
    }

    public function store(StoreLocationRequest $request): RedirectResponse
    {
        Location::query()->create($request->validated());

        return redirect()
            ->route('locations.index')
            ->with('success', 'Lokasi berhasil ditambahkan.');
    }

    public function edit(Location $location): View
    {
        return view('master.locations.edit', [
            'location' => $location,
            'parentLocations' => $this->parentOptions($location),
            'typeLabels' => self::TYPE_LABELS,
        ]);
    }

    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['parent_id'] !== null && in_array((int) $validated['parent_id'], $this->descendantIds($location), true)) {
            return back()
                ->withInput()
                ->withErrors([
                    'parent_id' => 'Lokasi turunan tidak dapat dipilih sebagai lokasi induk.',
                ]);
        }

        if ($validated['status'] === 'inactive') {
            $blockingMessage = $this->deactivationBlockMessage($location);

            if ($blockingMessage !== null) {
                return back()
                    ->withInput()
                    ->withErrors(['status' => $blockingMessage]);
            }
        }

        $location->update($validated);

        return redirect()
            ->route('locations.index')
            ->with('success', 'Lokasi berhasil diperbarui.');
    }

    public function toggleStatus(Location $location): RedirectResponse
    {
        $blockingMessage = $this->deactivationBlockMessage($location);
        if ($blockingMessage !== null) {
            return back()->with('error', str_replace('dinonaktifkan', 'dihapus', $blockingMessage));
        }
        $location->update(['status' => 'inactive']);
        return redirect()->route('locations.index')->with('success', 'Lokasi dipindahkan ke Daftar Hapus.');
    }

    public function restore(Location $location): RedirectResponse
    {
        if ($location->parent_id !== null && $location->parent()->where('status', '!=', 'active')->exists()) {
            return back()->with('error', 'Pulihkan lokasi induk terlebih dahulu.');
        }
        $location->update(['status' => 'active']);
        return back()->with('success', 'Lokasi berhasil dipulihkan.');
    }

    private function deactivationBlockMessage(Location $location): ?string
    {
        if ($location->children()->where('status', 'active')->exists()) {
            return 'Lokasi tidak dapat dinonaktifkan karena masih memiliki lokasi turunan yang aktif.';
        }

        if ($location->assets()->whereNotIn('asset_status', ['disposed', 'lost'])->exists()) {
            return 'Lokasi tidak dapat dinonaktifkan karena masih digunakan sebagai lokasi aset aktif.';
        }

        if ($location->shelves()->where('status', 'active')->exists()) {
            return 'Lokasi tidak dapat dinonaktifkan karena masih memiliki rak perpustakaan yang aktif.';
        }

        return null;
    }

    /**
     * @return Collection<int, Location>
     */
    private function parentOptions(?Location $excludedLocation = null): Collection
    {
        $excludedIds = $excludedLocation
            ? array_merge([$excludedLocation->id], $this->descendantIds($excludedLocation))
            : [];

        $locations = Location::query()
            ->where('status', 'active')
            ->when($excludedIds !== [], fn ($query) => $query->whereNotIn('id', $excludedIds))
            ->orderBy('location_name')
            ->get(['id', 'parent_id', 'location_code', 'location_name', 'location_type']);

        return $this->flattenLocations($locations);
    }

    /**
     * @param Collection<int, Location> $locations
     * @return Collection<int, Location>
     */
    private function flattenLocations(Collection $locations): Collection
    {
        $grouped = $locations->groupBy(fn (Location $location): string => (string) ($location->parent_id ?? 'root'));
        $result = new Collection();

        $appendChildren = function (?int $parentId, int $depth) use (&$appendChildren, $grouped, $result): void {
            $key = $parentId === null ? 'root' : (string) $parentId;

            foreach ($grouped->get($key, new Collection()) as $location) {
                $location->setAttribute('option_label', str_repeat('— ', $depth).$location->location_code.' - '.$location->location_name);
                $result->push($location);
                $appendChildren((int) $location->id, $depth + 1);
            }
        };

        $appendChildren(null, 0);

        // Data lama yang induknya tidak ikut dalam pilihan tetap ditampilkan di bagian akhir.
        foreach ($locations as $location) {
            if (! $result->contains('id', $location->id)) {
                $location->setAttribute('option_label', $location->location_code.' - '.$location->location_name);
                $result->push($location);
            }
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(Location $location): array
    {
        $descendantIds = [];
        $pendingIds = [$location->id];

        while ($pendingIds !== []) {
            $children = Location::query()
                ->whereIn('parent_id', $pendingIds)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($children === []) {
                break;
            }

            $newIds = array_values(array_diff($children, $descendantIds));

            if ($newIds === []) {
                break;
            }

            $descendantIds = array_values(array_unique(array_merge($descendantIds, $newIds)));
            $pendingIds = $newIds;
        }

        return $descendantIds;
    }
}
