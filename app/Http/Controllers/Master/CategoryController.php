<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreCategoryRequest;
use App\Http\Requests\Master\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $scope = (string) $request->query('scope');
        $status = (string) $request->query('status');

        $categories = Category::query()
            ->with('parent:id,category_name')
            ->withCount('children')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('category_code', 'like', "%{$search}%")
                        ->orWhere('category_name', 'like', "%{$search}%");
                });
            })
            ->when(in_array($scope, ['inventory', 'library', 'both'], true), function ($query) use ($scope): void {
                $query->where('scope', $scope);
            })
            ->when(in_array($status, ['active', 'inactive'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->orderBy('category_name')
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'total' => Category::query()->count(),
            'active' => Category::query()->where('status', 'active')->count(),
            'inventory' => Category::query()->whereIn('scope', ['inventory', 'both'])->count(),
            'library' => Category::query()->whereIn('scope', ['library', 'both'])->count(),
        ];

        return view('master.categories.index', compact('categories', 'summary'));
    }

    public function create(): View
    {
        return view('master.categories.create', [
            'parentCategories' => $this->parentOptions(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create($request->validated());

        return redirect()
            ->route('categories.index')
            ->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category): View
    {
        return view('master.categories.edit', [
            'category' => $category,
            'parentCategories' => $this->parentOptions($category),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['parent_id'] !== null && in_array((int) $validated['parent_id'], $this->descendantIds($category), true)) {
            return back()
                ->withInput()
                ->withErrors([
                    'parent_id' => 'Kategori turunan tidak dapat dipilih sebagai kategori induk.',
                ]);
        }

        $category->update($validated);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function toggleStatus(Category $category): RedirectResponse
    {
        $newStatus = $category->status === 'active' ? 'inactive' : 'active';

        if ($newStatus === 'inactive' && $category->children()->where('status', 'active')->exists()) {
            return back()->with('error', 'Kategori tidak dapat dinonaktifkan karena masih memiliki kategori turunan yang aktif.');
        }

        $category->update(['status' => $newStatus]);

        $message = $newStatus === 'active'
            ? 'Kategori berhasil diaktifkan.'
            : 'Kategori berhasil dinonaktifkan.';

        return back()->with('success', $message);
    }

    private function parentOptions(?Category $excludedCategory = null)
    {
        $excludedIds = $excludedCategory
            ? array_merge([$excludedCategory->id], $this->descendantIds($excludedCategory))
            : [];

        return Category::query()
            ->where('status', 'active')
            ->when($excludedIds !== [], fn ($query) => $query->whereNotIn('id', $excludedIds))
            ->orderBy('category_name')
            ->get(['id', 'category_code', 'category_name']);
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(Category $category): array
    {
        $descendantIds = [];
        $pendingIds = [$category->id];

        while ($pendingIds !== []) {
            $children = Category::query()
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
