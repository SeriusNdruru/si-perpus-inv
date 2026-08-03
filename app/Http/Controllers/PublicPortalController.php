<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicSite\StoreContactMessageRequest;
use App\Models\BookDetail;
use App\Models\Item;
use App\Models\PublicContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicPortalController extends Controller
{
    public function home(): View
    {
        $featuredBooks = $this->bookQuery()
            ->orderByDesc('available_copies')
            ->orderByDesc('items.created_at')
            ->limit(8)
            ->get();

        $statistics = [
            'titles' => DB::table('items')
                ->where('item_type', 'book')
                ->where('status', 'active')
                ->count(),
            'available' => DB::table('assets')
                ->join('items', 'items.id', '=', 'assets.item_id')
                ->where('items.item_type', 'book')
                ->where('assets.asset_status', 'available')
                ->count(),
            'locations' => DB::table('locations')->where('status', 'active')->count(),
            'members' => DB::table('members')->where('status', 'active')->count(),
        ];

        return view('public.home', compact('featuredBooks', 'statistics'));
    }

    public function about(): View
    {
        return view('public.about');
    }

    public function contact(): View
    {
        return view('public.contact');
    }

    public function storeContact(StoreContactMessageRequest $request): RedirectResponse
    {
        PublicContactMessage::query()->create($request->safe()->except('website'));

        return back()->with(
            'success',
            'Pesan berhasil dikirim. Pengelola akan memeriksanya melalui dashboard perpustakaan.'
        );
    }

    public function catalog(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $category = (int) $request->query('category');
        $gradeLevel = (string) $request->query('grade_level');

        $books = $this->bookQuery()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('items.item_name', 'like', "%{$search}%")
                        ->orWhere('items.item_code', 'like', "%{$search}%")
                        ->orWhere('book_details.isbn_10', 'like', "%{$search}%")
                        ->orWhere('book_details.isbn_13', 'like', "%{$search}%")
                        ->orWhereExists(function ($authorQuery) use ($search): void {
                            $authorQuery
                                ->selectRaw('1')
                                ->from('book_authors')
                                ->join('authors', 'authors.id', '=', 'book_authors.author_id')
                                ->whereColumn('book_authors.item_id', 'items.id')
                                ->where('authors.author_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($category > 0, fn ($query) => $query->where('items.category_id', $category))
            ->when(array_key_exists($gradeLevel, BookDetail::GRADE_LEVELS), fn ($query) => $query->where('book_details.grade_level', $gradeLevel))
            ->orderBy('items.item_name')
            ->paginate(12)
            ->withQueryString();

        $categories = DB::table('categories')
            ->where('status', 'active')
            ->whereIn('scope', ['library', 'both'])
            ->orderBy('category_name')
            ->get(['id', 'category_name']);

        return view('public.catalog', [
            'books' => $books,
            'categories' => $categories,
            'gradeLevels' => BookDetail::GRADE_LEVELS,
        ]);
    }

    private function bookQuery()
    {
        return Item::query()
            ->join('book_details', 'book_details.item_id', '=', 'items.id')
            ->leftJoin('publishers', 'publishers.id', '=', 'book_details.publisher_id')
            ->where('items.item_type', 'book')
            ->where('items.status', 'active')
            ->whereIn('book_details.completion_status', ['complete', 'verified'])
            ->select([
                'items.id',
                'items.item_code',
                'items.item_name',
                'items.description',
                'items.category_id',
                'book_details.isbn_10',
                'book_details.isbn_13',
                'book_details.publication_year',
                'book_details.grade_level',
                'book_details.call_number',
                'book_details.cover_path',
                'publishers.publisher_name',
            ])
            ->selectSub(function ($query): void {
                $query->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.item_id', 'items.id')
                    ->where('assets.asset_status', 'available')
                    ->whereIn('assets.condition_status', ['good', 'fair'])
                    ->whereNotNull('assets.current_shelf_id');
            }, 'available_copies')
            ->selectSub(function ($query): void {
                $query->from('book_authors')
                    ->join('authors', 'authors.id', '=', 'book_authors.author_id')
                    ->selectRaw("GROUP_CONCAT(authors.author_name ORDER BY book_authors.author_order SEPARATOR ', ')")
                    ->whereColumn('book_authors.item_id', 'items.id');
            }, 'author_names');
    }
}
