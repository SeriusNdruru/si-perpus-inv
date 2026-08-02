<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\Library\MemberAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MemberBookController extends Controller
{
    public function __construct(private readonly MemberAccountService $memberAccounts)
    {
    }

    public function index(Request $request): View
    {
        $member = $this->memberAccounts->memberFor($request->user());
        $search = trim((string) $request->query('search'));
        $category = (int) $request->query('category');
        $cart = collect($request->session()->get('member.loan_request_cart', []))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $books = Item::query()
            ->join('book_details', 'book_details.item_id', '=', 'items.id')
            ->leftJoin('publishers', 'publishers.id', '=', 'book_details.publisher_id')
            ->where('items.item_type', 'book')
            ->where('items.status', 'active')
            ->whereIn('book_details.completion_status', ['complete', 'verified'])
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
            ->select([
                'items.id',
                'items.item_code',
                'items.item_name',
                'items.description',
                'book_details.isbn_10',
                'book_details.isbn_13',
                'book_details.publication_year',
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
            }, 'author_names')
            ->orderBy('items.item_name')
            ->paginate(12)
            ->withQueryString();

        $categories = DB::table('categories')
            ->where('status', 'active')
            ->whereIn('scope', ['library', 'both'])
            ->orderBy('category_name')
            ->get(['id', 'category_name']);

        return view('member.books.index', compact('member', 'books', 'categories', 'cart'));
    }

    public function addToCart(Request $request, Item $book): RedirectResponse
    {
        $this->memberAccounts->memberFor($request->user());

        abort_unless(
            $book->item_type === 'book' && $book->status === 'active',
            404
        );

        $available = DB::table('assets')
            ->where('item_id', $book->id)
            ->where('asset_status', 'available')
            ->whereIn('condition_status', ['good', 'fair'])
            ->whereNotNull('current_shelf_id')
            ->exists();

        if (! $available) {
            return back()->with('error', 'Buku ini sedang tidak tersedia.');
        }

        $cart = collect($request->session()->get('member.loan_request_cart', []))
            ->map(static fn ($id): int => (int) $id)
            ->push($book->id)
            ->unique()
            ->values()
            ->all();

        $request->session()->put('member.loan_request_cart', $cart);

        return back()->with('success', 'Buku ditambahkan ke keranjang pengajuan.');
    }

    public function removeFromCart(Request $request, Item $book): RedirectResponse
    {
        $this->memberAccounts->memberFor($request->user());

        $cart = collect($request->session()->get('member.loan_request_cart', []))
            ->reject(static fn ($id): bool => (int) $id === (int) $book->id)
            ->values()
            ->all();

        $request->session()->put('member.loan_request_cart', $cart);

        return back()->with('success', 'Buku dihapus dari keranjang.');
    }

    public function cart(Request $request): View
    {
        $member = $this->memberAccounts->memberFor($request->user());
        $ids = collect($request->session()->get('member.loan_request_cart', []))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $books = Item::query()
            ->with(['bookDetail', 'authors'])
            ->whereIn('id', $ids)
            ->orderBy('item_name')
            ->get();

        return view('member.books.cart', compact('member', 'books'));
    }
}
