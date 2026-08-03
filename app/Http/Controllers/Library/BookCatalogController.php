<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\UpdateBookCatalogRequest;
use App\Models\Asset;
use App\Models\Author;
use App\Models\BookDetail;
use App\Models\Item;
use App\Models\Publisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class BookCatalogController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const COMPLETION_STATUSES = [
        'incomplete' => 'Belum lengkap',
        'complete' => 'Lengkap',
        'verified' => 'Terverifikasi',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $completionStatus = (string) $request->query('completion_status');
        $copyStatus = (string) $request->query('copy_status');
        $gradeLevel = (string) $request->query('grade_level');

        $books = Item::query()
            ->where('item_type', 'book')
            ->where('status', 'active')
            ->with([
                'category:id,category_code,category_name',
                'bookDetail.publisher:id,publisher_name',
                'authors:id,author_name',
            ])
            ->select('items.*')
            ->selectSub(function ($query): void {
                $query->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.item_id', 'items.id')
                    ->whereNotIn('assets.asset_status', ['disposed']);
            }, 'copies_count')
            ->selectSub(function ($query): void {
                $query->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.item_id', 'items.id')
                    ->where('assets.asset_status', 'unprocessed');
            }, 'unprocessed_copies_count')
            ->selectSub(function ($query): void {
                $query->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.item_id', 'items.id')
                    ->whereNull('assets.current_shelf_id')
                    ->whereNotIn('assets.asset_status', ['disposed', 'lost']);
            }, 'without_shelf_count')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('item_code', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhereHas('bookDetail', function ($bookQuery) use ($search): void {
                            $bookQuery
                                ->where('isbn_10', 'like', "%{$search}%")
                                ->orWhere('isbn_13', 'like', "%{$search}%")
                                ->orWhere('call_number', 'like', "%{$search}%");
                        })
                        ->orWhereHas('authors', fn ($authorQuery) => $authorQuery->where('author_name', 'like', "%{$search}%"));
                });
            })
            ->when(
                array_key_exists($completionStatus, self::COMPLETION_STATUSES),
                fn ($query) => $query->whereHas('bookDetail', fn ($detailQuery) => $detailQuery->where('completion_status', $completionStatus))
            )
            ->when(
                array_key_exists($gradeLevel, BookDetail::GRADE_LEVELS),
                fn ($query) => $query->whereHas('bookDetail', fn ($detailQuery) => $detailQuery->where('grade_level', $gradeLevel))
            )
            ->when($copyStatus === 'unprocessed', function ($query): void {
                $query->whereHas('assets', fn ($assetQuery) => $assetQuery->where('asset_status', 'unprocessed'));
            })
            ->when($copyStatus === 'without_shelf', function ($query): void {
                $query->whereHas('assets', function ($assetQuery): void {
                    $assetQuery
                        ->whereNull('current_shelf_id')
                        ->whereNotIn('asset_status', ['disposed', 'lost']);
                });
            })
            ->orderByDesc('items.created_at')
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'titles' => Item::query()
                ->where('item_type', 'book')
                ->where('status', 'active')
                ->count(),
            'incomplete' => BookDetail::query()
                ->where('completion_status', 'incomplete')
                ->whereHas('item', fn ($query) => $query->where('status', 'active'))
                ->count(),
            'unprocessed_copies' => Asset::query()
                ->where('asset_status', 'unprocessed')
                ->whereHas('item', fn ($query) => $query
                    ->where('item_type', 'book')
                    ->where('status', 'active'))
                ->count(),
            'without_shelf' => Asset::query()
                ->whereNull('current_shelf_id')
                ->whereNotIn('asset_status', ['disposed', 'lost'])
                ->whereHas('item', fn ($query) => $query
                    ->where('item_type', 'book')
                    ->where('status', 'active'))
                ->count(),
        ];

        return view('library.books.index', [
            'books' => $books,
            'summary' => $summary,
            'completionStatuses' => self::COMPLETION_STATUSES,
            'gradeLevels' => BookDetail::GRADE_LEVELS,
        ]);
    }

    public function show(Item $book): View
    {
        $this->ensureBook($book);

        $book->load([
            'category:id,category_code,category_name',
            'unit:id,unit_code,unit_name',
            'bookDetail.publisher:id,publisher_name,city',
            'bookDetail.updater:id,full_name',
            'authors:id,author_name',
        ]);

        $copies = Asset::query()
            ->with([
                'location:id,location_code,location_name',
                'shelf:id,shelf_code,shelf_name',
            ])
            ->where('item_id', $book->id)
            ->orderBy('asset_code')
            ->paginate(15, ['*'], 'copies_page');

        $copySummary = Asset::query()
            ->where('item_id', $book->id)
            ->select('asset_status', DB::raw('COUNT(*) AS total'))
            ->groupBy('asset_status')
            ->pluck('total', 'asset_status');

        return view('library.books.show', [
            'book' => $book,
            'copies' => $copies,
            'copySummary' => $copySummary,
            'completionStatuses' => self::COMPLETION_STATUSES,
        ]);
    }

    public function edit(Item $book): View
    {
        $this->ensureBook($book);

        $book->load([
            'category:id,category_code,category_name',
            'bookDetail.publisher:id,publisher_name',
            'authors:id,author_name',
        ]);

        return view('library.books.edit', [
            'book' => $book,
            'publishers' => Publisher::query()->orderBy('publisher_name')->get(['id', 'publisher_name', 'city']),
            'gradeLevels' => BookDetail::GRADE_LEVELS,
        ]);
    }

    public function update(UpdateBookCatalogRequest $request, Item $book): RedirectResponse
    {
        $this->ensureBook($book);
        $book->loadMissing('bookDetail');

        $data = $request->validated();
        $userId = (int) $request->user()->id;
        $oldCoverPath = $book->bookDetail?->cover_path;
        $oldItemImagePath = $book->image_path;
        $newCoverPath = null;
        $coverChanged = false;

        if ($request->hasFile('cover_image')) {
            $newCoverPath = $request->file('cover_image')->store('book-covers', 'public');
            $coverChanged = true;
        }

        try {
            DB::transaction(function () use (
                $book,
                $data,
                $userId,
                $coverChanged,
                $newCoverPath
            ): void {
                $publisherId = $data['publisher_id'] ?? null;

                if (! empty($data['new_publisher_name'])) {
                    $publisher = Publisher::query()->firstOrCreate([
                        'publisher_name' => $data['new_publisher_name'],
                    ]);
                    $publisherId = $publisher->id;
                }

                $authorIds = [];
                foreach ($data['authors'] ?? [] as $position => $authorName) {
                    $author = Author::query()->firstOrCreate(['author_name' => $authorName]);
                    $authorIds[] = (int) $author->id;

                    DB::table('book_authors')->updateOrInsert(
                        [
                            'item_id' => $book->id,
                            'author_id' => $author->id,
                            'author_role' => 'author',
                        ],
                        ['author_order' => $position + 1]
                    );
                }

                DB::table('book_authors')
                    ->where('item_id', $book->id)
                    ->where('author_role', 'author')
                    ->when(
                        $authorIds !== [],
                        fn ($query) => $query->whereNotIn('author_id', $authorIds),
                        fn ($query) => $query
                    )
                    ->delete();

                $completionStatus = $this->catalogIsComplete($data, $publisherId, $authorIds)
                    ? 'complete'
                    : 'incomplete';

                $bookDetail = BookDetail::query()->firstOrNew(['item_id' => $book->id]);

                if ($bookDetail->completion_status === 'verified' && $completionStatus === 'complete') {
                    $completionStatus = 'verified';
                }

                $values = [
                    'isbn_10' => $data['isbn_10'] ?? null,
                    'isbn_13' => $data['isbn_13'] ?? null,
                    'publisher_id' => $publisherId,
                    'publication_year' => $data['publication_year'] ?? null,
                    'grade_level' => $data['grade_level'],
                    'edition' => $data['edition'] ?? null,
                    'language' => $data['language'],
                    'page_count' => $data['page_count'] ?? null,
                    'classification_code' => $data['classification_code'] ?? null,
                    'call_number' => $data['call_number'] ?? null,
                    'catalog_notes' => $data['catalog_notes'] ?? null,
                    'completion_status' => $completionStatus,
                    'updated_by' => $userId,
                ];

                if ($coverChanged) {
                    $values['cover_path'] = $newCoverPath;
                }

                $bookDetail->fill($values)->save();

                if ($coverChanged && $newCoverPath !== null) {
                    $book->update([
                        'image_path' => $newCoverPath,
                        'updated_by' => $userId,
                    ]);
                }

                if ($completionStatus === 'incomplete') {
                    Asset::query()
                        ->where('item_id', $book->id)
                        ->where('asset_status', 'available')
                        ->update([
                            'asset_status' => 'unprocessed',
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                }
            }, 3);
        } catch (Throwable $exception) {
            if ($coverChanged && $newCoverPath !== null) {
                Storage::disk('public')->delete($newCoverPath);
            }

            return back()
                ->withInput()
                ->withErrors(['database' => $this->databaseMessage($exception)]);
        }

        if ($coverChanged && $newCoverPath !== null) {
            $oldPaths = array_unique(array_filter([$oldCoverPath, $oldItemImagePath]));

            foreach ($oldPaths as $oldPath) {
                if ($oldPath !== $newCoverPath) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
        }

        return redirect()
            ->route('library.books.show', $book)
            ->with('success', 'Data katalog dan cover buku berhasil diperbarui.');
    }

    private function ensureBook(Item $book): void
    {
        abort_unless(
            $book->item_type === 'book' && $book->status === 'active',
            404
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, int> $authorIds
     */
    private function catalogIsComplete(array $data, mixed $publisherId, array $authorIds): bool
    {
        return ! empty($data['isbn_10'] ?? $data['isbn_13'] ?? null)
            && ! empty($publisherId)
            && ! empty($data['publication_year'])
            && ! empty($data['grade_level'])
            && $authorIds !== []
            && ! empty($data['classification_code'])
            && ! empty($data['call_number']);
    }

    private function databaseMessage(Throwable $exception): string
    {
        $message = (string) ($exception->getPrevious()?->getMessage() ?? $exception->getMessage());
        $message = preg_replace('/^SQLSTATE\[[^\]]+\]:.*?:\s*\d+\s*/', '', $message) ?: $message;

        if (str_contains(strtolower($message), 'duplicate entry')) {
            return 'ISBN, penerbit, atau data penulis sudah digunakan dengan format yang tidak valid.';
        }

        return 'Database menolak proses: '.mb_substr($message, 0, 300);
    }
}
