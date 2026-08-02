<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Category;
use App\Models\FinePayment;
use App\Models\Item;
use App\Models\LibraryShelf;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\StockBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $canInventory = $user->hasAnyRole(['SUPER_ADMIN', 'INVENTORY_ADMIN', 'MANAGER']);
        $canLibrary = $user->hasAnyRole(['SUPER_ADMIN', 'LIBRARY_ADMIN', 'LIBRARY_OFFICER', 'MANAGER']);

        $summary = [
            'items' => $canInventory ? Item::query()->count() : null,
            'assets' => $canInventory ? Asset::query()->count() : null,
            'books' => $canLibrary ? Item::query()->where('item_type', 'book')->count() : null,
            'active_loans' => $canLibrary
                ? LoanItem::query()->where('return_status', 'borrowed')->count()
                : null,
            'outstanding_fines' => $canLibrary ? $this->outstandingFineTotal() : null,
            'active_reservations' => $canLibrary
                ? Reservation::query()->whereIn('status', ['waiting', 'ready'])->count()
                : null,
        ];

        return view('reports.index', compact('canInventory', 'canLibrary', 'summary'));
    }

    public function inventory(Request $request): View
    {
        $filters = $this->inventoryFilters($request);
        $baseQuery = $this->inventoryBaseQuery($filters);
        $itemIds = (clone $baseQuery)->select('items.id');

        $summary = [
            'total_items' => (clone $baseQuery)->count(),
            'active_items' => (clone $baseQuery)->where('items.status', 'active')->count(),
            'total_assets' => Asset::query()->whereIn('item_id', clone $itemIds)->count(),
            'quantity_stock' => (float) StockBalance::query()->whereIn('item_id', clone $itemIds)->sum('quantity'),
            'acquisition_value' => (float) Asset::query()->whereIn('item_id', clone $itemIds)->sum('acquisition_price'),
            'damaged_or_lost' => Asset::query()
                ->whereIn('item_id', clone $itemIds)
                ->whereIn('condition_status', ['damaged', 'lost'])
                ->count(),
        ];

        $items = (clone $baseQuery)
            ->with(['category:id,category_code,category_name', 'unit:id,unit_code,unit_name'])
            ->withCount([
                'assets as total_assets',
                'assets as available_assets' => fn (Builder $query) => $query->where('asset_status', 'available'),
                'assets as borrowed_assets' => fn (Builder $query) => $query->where('asset_status', 'borrowed'),
                'assets as damaged_assets' => fn (Builder $query) => $query->where('condition_status', 'damaged'),
                'assets as lost_assets' => fn (Builder $query) => $query->where('condition_status', 'lost'),
            ])
            ->withSum('stockBalances as quantity_stock', 'quantity')
            ->withSum('assets as acquisition_value', 'acquisition_price')
            ->orderBy('items.item_name')
            ->paginate(20)
            ->withQueryString();

        $categories = Category::query()->orderBy('category_name')->get(['id', 'category_code', 'category_name']);

        return view('reports.inventory', compact('items', 'summary', 'categories', 'filters'));
    }

    public function inventoryCsv(Request $request): StreamedResponse
    {
        $filters = $this->inventoryFilters($request);
        $query = $this->inventoryBaseQuery($filters)
            ->with(['category:id,category_code,category_name', 'unit:id,unit_code,unit_name'])
            ->withCount([
                'assets as total_assets',
                'assets as available_assets' => fn (Builder $builder) => $builder->where('asset_status', 'available'),
                'assets as borrowed_assets' => fn (Builder $builder) => $builder->where('asset_status', 'borrowed'),
                'assets as damaged_assets' => fn (Builder $builder) => $builder->where('condition_status', 'damaged'),
                'assets as lost_assets' => fn (Builder $builder) => $builder->where('condition_status', 'lost'),
            ])
            ->withSum('stockBalances as quantity_stock', 'quantity')
            ->withSum('assets as acquisition_value', 'acquisition_price')
            ->orderBy('items.item_name');

        return $this->csvDownload(
            'laporan-inventaris-'.now()->format('Ymd-His').'.csv',
            ['Kode', 'Nama barang', 'Jenis', 'Pencatatan', 'Kategori', 'Satuan', 'Status', 'Total aset', 'Tersedia', 'Dipinjam', 'Rusak', 'Hilang', 'Stok jumlah', 'Nilai perolehan'],
            $query->lazy(500),
            fn (Item $item): array => [
                $item->item_code,
                $item->item_name,
                $this->itemTypeLabel($item->item_type),
                $item->tracking_type === 'asset' ? 'Per aset' : 'Jumlah stok',
                $item->category?->category_name,
                $item->unit?->unit_code,
                $item->status === 'active' ? 'Aktif' : 'Tidak aktif',
                (int) $item->total_assets,
                (int) $item->available_assets,
                (int) $item->borrowed_assets,
                (int) $item->damaged_assets,
                (int) $item->lost_assets,
                (float) ($item->quantity_stock ?? 0),
                (float) ($item->acquisition_value ?? 0),
            ]
        );
    }

    public function collection(Request $request): View
    {
        $filters = $this->collectionFilters($request);
        $baseQuery = $this->collectionBaseQuery($filters);
        $itemIds = (clone $baseQuery)->select('items.id');

        $summary = [
            'titles' => (clone $baseQuery)->count(),
            'copies' => Asset::query()->whereIn('item_id', clone $itemIds)->count(),
            'available' => Asset::query()->whereIn('item_id', clone $itemIds)->where('asset_status', 'available')->count(),
            'borrowed' => Asset::query()->whereIn('item_id', clone $itemIds)->where('asset_status', 'borrowed')->count(),
            'unprocessed' => Asset::query()->whereIn('item_id', clone $itemIds)->where('asset_status', 'unprocessed')->count(),
            'without_shelf' => Asset::query()->whereIn('item_id', clone $itemIds)->whereNull('current_shelf_id')->count(),
        ];

        $books = (clone $baseQuery)
            ->with([
                'category:id,category_code,category_name',
                'bookDetail:item_id,isbn_10,isbn_13,publisher_id,publication_year,classification_code,call_number,completion_status',
                'bookDetail.publisher:id,publisher_name',
                'authors:id,author_name',
            ])
            ->withCount([
                'assets as total_copies',
                'assets as available_copies' => fn (Builder $query) => $query->where('asset_status', 'available'),
                'assets as borrowed_copies' => fn (Builder $query) => $query->where('asset_status', 'borrowed'),
                'assets as unprocessed_copies' => fn (Builder $query) => $query->where('asset_status', 'unprocessed'),
                'assets as copies_without_shelf' => fn (Builder $query) => $query->whereNull('current_shelf_id'),
                'reservations as active_reservations' => fn (Builder $query) => $query->whereIn('status', ['waiting', 'ready']),
            ])
            ->orderBy('items.item_name')
            ->paginate(20)
            ->withQueryString();

        $categories = Category::query()
            ->whereIn('scope', ['library', 'both'])
            ->orWhereHas('items', fn (Builder $query) => $query->where('item_type', 'book'))
            ->orderBy('category_name')
            ->get(['id', 'category_code', 'category_name']);
        $shelves = LibraryShelf::query()->orderBy('shelf_code')->get(['id', 'shelf_code', 'shelf_name']);

        return view('reports.collection', compact('books', 'summary', 'categories', 'shelves', 'filters'));
    }

    public function collectionCsv(Request $request): StreamedResponse
    {
        $filters = $this->collectionFilters($request);
        $query = $this->collectionBaseQuery($filters)
            ->with([
                'category:id,category_code,category_name',
                'bookDetail:item_id,isbn_10,isbn_13,publisher_id,publication_year,classification_code,call_number,completion_status',
                'bookDetail.publisher:id,publisher_name',
                'authors:id,author_name',
            ])
            ->withCount([
                'assets as total_copies',
                'assets as available_copies' => fn (Builder $builder) => $builder->where('asset_status', 'available'),
                'assets as borrowed_copies' => fn (Builder $builder) => $builder->where('asset_status', 'borrowed'),
                'assets as unprocessed_copies' => fn (Builder $builder) => $builder->where('asset_status', 'unprocessed'),
                'assets as copies_without_shelf' => fn (Builder $builder) => $builder->whereNull('current_shelf_id'),
                'reservations as active_reservations' => fn (Builder $builder) => $builder->whereIn('status', ['waiting', 'ready']),
            ])
            ->orderBy('items.item_name');

        return $this->csvDownload(
            'laporan-koleksi-buku-'.now()->format('Ymd-His').'.csv',
            ['Kode', 'Judul', 'ISBN', 'Penulis', 'Penerbit', 'Tahun', 'Kategori', 'Klasifikasi', 'Nomor panggil', 'Status katalog', 'Total eksemplar', 'Tersedia', 'Dipinjam', 'Belum diproses', 'Tanpa rak', 'Reservasi aktif'],
            $query->lazy(500),
            fn (Item $book): array => [
                $book->item_code,
                $book->item_name,
                $book->bookDetail?->isbn_13 ?: $book->bookDetail?->isbn_10,
                $book->authors->pluck('author_name')->join(', '),
                $book->bookDetail?->publisher?->publisher_name,
                $book->bookDetail?->publication_year,
                $book->category?->category_name,
                $book->bookDetail?->classification_code,
                $book->bookDetail?->call_number,
                $this->catalogStatusLabel($book->bookDetail?->completion_status),
                (int) $book->total_copies,
                (int) $book->available_copies,
                (int) $book->borrowed_copies,
                (int) $book->unprocessed_copies,
                (int) $book->copies_without_shelf,
                (int) $book->active_reservations,
            ]
        );
    }

    public function loans(Request $request): View
    {
        $filters = $this->transactionFilters($request, ['active', 'completed', 'overdue', 'cancelled']);
        $baseQuery = $this->loanBaseQuery($filters);
        $loanIds = (clone $baseQuery)->select('loans.id');

        $summary = [
            'transactions' => (clone $baseQuery)->count(),
            'copies' => LoanItem::query()->whereIn('loan_id', clone $loanIds)->count(),
            'outstanding' => LoanItem::query()->whereIn('loan_id', clone $loanIds)->where('return_status', 'borrowed')->count(),
            'returned' => LoanItem::query()->whereIn('loan_id', clone $loanIds)->whereIn('return_status', ['returned', 'damaged', 'lost'])->count(),
            'fines' => (float) LoanItem::query()->whereIn('loan_id', clone $loanIds)->sum('fine_amount'),
        ];

        $loans = (clone $baseQuery)
            ->with(['member:id,member_code,member_name,member_type', 'processor:id,full_name'])
            ->withCount([
                'items as copy_count',
                'items as outstanding_count' => fn (Builder $query) => $query->where('return_status', 'borrowed'),
                'items as returned_count' => fn (Builder $query) => $query->whereIn('return_status', ['returned', 'damaged', 'lost']),
            ])
            ->withSum('items as fine_total', 'fine_amount')
            ->orderByDesc('loan_date')
            ->paginate(20)
            ->withQueryString();

        return view('reports.loans', compact('loans', 'summary', 'filters'));
    }

    public function loansCsv(Request $request): StreamedResponse
    {
        $filters = $this->transactionFilters($request, ['active', 'completed', 'overdue', 'cancelled']);
        $query = $this->loanBaseQuery($filters)
            ->with(['member:id,member_code,member_name,member_type', 'processor:id,full_name'])
            ->withCount([
                'items as copy_count',
                'items as outstanding_count' => fn (Builder $builder) => $builder->where('return_status', 'borrowed'),
                'items as returned_count' => fn (Builder $builder) => $builder->whereIn('return_status', ['returned', 'damaged', 'lost']),
            ])
            ->withSum('items as fine_total', 'fine_amount')
            ->orderByDesc('loan_date');

        return $this->csvDownload(
            'laporan-peminjaman-'.now()->format('Ymd-His').'.csv',
            ['Kode peminjaman', 'Tanggal pinjam', 'Jatuh tempo', 'Kode anggota', 'Nama anggota', 'Jenis anggota', 'Status', 'Jumlah eksemplar', 'Belum kembali', 'Sudah diproses', 'Total denda', 'Petugas'],
            $query->lazy(500),
            fn (Loan $loan): array => [
                $loan->loan_code,
                $loan->loan_date?->format('Y-m-d H:i:s'),
                $loan->default_due_date?->format('Y-m-d'),
                $loan->member?->member_code,
                $loan->member?->member_name,
                $loan->member?->typeLabel(),
                $loan->statusLabel(),
                (int) $loan->copy_count,
                (int) $loan->outstanding_count,
                (int) $loan->returned_count,
                (float) ($loan->fine_total ?? 0),
                $loan->processor?->full_name,
            ]
        );
    }

    public function fines(Request $request): View
    {
        $filters = $this->fineFilters($request);
        $baseQuery = $this->fineBaseQuery($filters);
        $loanItemIds = (clone $baseQuery)->select('loan_items.id');

        $totalFine = (float) (clone $baseQuery)->sum('loan_items.fine_amount');
        $totalPaid = (float) FinePayment::query()->whereIn('loan_item_id', clone $loanItemIds)->sum('amount');

        $summary = [
            'bills' => (clone $baseQuery)->count(),
            'total_fine' => $totalFine,
            'total_paid' => $totalPaid,
            'outstanding' => max($totalFine - $totalPaid, 0),
        ];

        $fines = (clone $baseQuery)
            ->with([
                'loan:id,loan_code,member_id,loan_date',
                'loan.member:id,member_code,member_name,member_type',
                'asset:id,item_id,asset_code,barcode',
                'asset.item:id,item_code,item_name',
                'finePayments:id,loan_item_id,amount,payment_date,payment_method',
            ])
            ->withSum('finePayments as paid_amount', 'amount')
            ->orderByDesc('returned_at')
            ->paginate(20)
            ->withQueryString();

        return view('reports.fines', compact('fines', 'summary', 'filters'));
    }

    public function finesCsv(Request $request): StreamedResponse
    {
        $filters = $this->fineFilters($request);
        $query = $this->fineBaseQuery($filters)
            ->with([
                'loan:id,loan_code,member_id,loan_date',
                'loan.member:id,member_code,member_name,member_type',
                'asset:id,item_id,asset_code,barcode',
                'asset.item:id,item_code,item_name',
            ])
            ->withSum('finePayments as paid_amount', 'amount')
            ->orderByDesc('returned_at');

        return $this->csvDownload(
            'laporan-denda-'.now()->format('Ymd-His').'.csv',
            ['Kode peminjaman', 'Kode anggota', 'Nama anggota', 'Judul buku', 'Kode aset', 'Tanggal kembali', 'Denda final', 'Sudah dibayar', 'Sisa tagihan', 'Status pembayaran'],
            $query->lazy(500),
            function (LoanItem $loanItem): array {
                $fine = (float) $loanItem->fine_amount;
                $paid = (float) ($loanItem->paid_amount ?? 0);

                return [
                    $loanItem->loan?->loan_code,
                    $loanItem->loan?->member?->member_code,
                    $loanItem->loan?->member?->member_name,
                    $loanItem->asset?->item?->item_name,
                    $loanItem->asset?->asset_code,
                    $loanItem->returned_at?->format('Y-m-d H:i:s'),
                    $fine,
                    $paid,
                    max($fine - $paid, 0),
                    $this->fineStatusLabel($fine, $paid),
                ];
            }
        );
    }

    public function members(Request $request): View
    {
        $filters = $this->memberFilters($request);
        $baseQuery = $this->memberBaseQuery($filters);
        $memberIds = (clone $baseQuery)->select('members.id');

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('members.status', 'active')->count(),
            'expired' => (clone $baseQuery)->where('members.status', 'expired')->count(),
            'active_loans' => Loan::query()
                ->whereIn('member_id', clone $memberIds)
                ->whereHas('items', fn (Builder $query) => $query->where('return_status', 'borrowed'))
                ->count(),
            'active_reservations' => Reservation::query()
                ->whereIn('member_id', clone $memberIds)
                ->whereIn('status', ['waiting', 'ready'])
                ->count(),
        ];

        $members = (clone $baseQuery)
            ->withCount([
                'loans as total_loans',
                'loans as active_loans' => fn (Builder $query) => $query->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('return_status', 'borrowed')),
                'reservations as active_reservations' => fn (Builder $query) => $query->whereIn('status', ['waiting', 'ready']),
            ])
            ->selectSub(function ($query): void {
                $query->from('loan_items')
                    ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                    ->selectRaw('COALESCE(SUM(loan_items.fine_amount), 0)')
                    ->whereColumn('loans.member_id', 'members.id');
            }, 'total_fines')
            ->selectSub(function ($query): void {
                $query->from('fine_payments')
                    ->join('loan_items', 'loan_items.id', '=', 'fine_payments.loan_item_id')
                    ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                    ->selectRaw('COALESCE(SUM(fine_payments.amount), 0)')
                    ->whereColumn('loans.member_id', 'members.id');
            }, 'paid_fines')
            ->orderBy('members.member_name')
            ->paginate(20)
            ->withQueryString();

        return view('reports.members', compact('members', 'summary', 'filters'));
    }

    public function membersCsv(Request $request): StreamedResponse
    {
        $filters = $this->memberFilters($request);
        $query = $this->memberBaseQuery($filters)
            ->withCount([
                'loans as total_loans',
                'loans as active_loans' => fn (Builder $builder) => $builder->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('return_status', 'borrowed')),
                'reservations as active_reservations' => fn (Builder $builder) => $builder->whereIn('status', ['waiting', 'ready']),
            ])
            ->selectSub(function ($builder): void {
                $builder->from('loan_items')
                    ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                    ->selectRaw('COALESCE(SUM(loan_items.fine_amount), 0)')
                    ->whereColumn('loans.member_id', 'members.id');
            }, 'total_fines')
            ->selectSub(function ($builder): void {
                $builder->from('fine_payments')
                    ->join('loan_items', 'loan_items.id', '=', 'fine_payments.loan_item_id')
                    ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                    ->selectRaw('COALESCE(SUM(fine_payments.amount), 0)')
                    ->whereColumn('loans.member_id', 'members.id');
            }, 'paid_fines')
            ->orderBy('members.member_name');

        return $this->csvDownload(
            'laporan-anggota-'.now()->format('Ymd-His').'.csv',
            ['Kode anggota', 'Nama', 'Jenis', 'Nomor identitas', 'Kelas', 'Tanggal bergabung', 'Masa berlaku', 'Status', 'Total transaksi', 'Pinjaman aktif', 'Reservasi aktif', 'Total denda', 'Sudah dibayar', 'Sisa denda'],
            $query->lazy(500),
            fn (Member $member): array => [
                $member->member_code,
                $member->member_name,
                $member->typeLabel(),
                $member->identity_number,
                $member->department,
                $member->join_date?->format('Y-m-d'),
                $member->expiry_date?->format('Y-m-d'),
                $member->statusLabel(),
                (int) $member->total_loans,
                (int) $member->active_loans,
                (int) $member->active_reservations,
                (float) ($member->total_fines ?? 0),
                (float) ($member->paid_fines ?? 0),
                max((float) ($member->total_fines ?? 0) - (float) ($member->paid_fines ?? 0), 0),
            ]
        );
    }

    public function reservations(Request $request): View
    {
        $filters = $this->transactionFilters($request, ['waiting', 'ready', 'completed', 'cancelled', 'expired']);
        $baseQuery = $this->reservationBaseQuery($filters);

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'waiting' => (clone $baseQuery)->where('reservations.status', 'waiting')->count(),
            'ready' => (clone $baseQuery)->where('reservations.status', 'ready')->count(),
            'completed' => (clone $baseQuery)->where('reservations.status', 'completed')->count(),
            'expired' => (clone $baseQuery)->where('reservations.status', 'expired')->count(),
        ];

        $reservations = (clone $baseQuery)
            ->with([
                'member:id,member_code,member_name,member_type',
                'item:id,item_code,item_name',
                'item.bookDetail:item_id,isbn_10,isbn_13,call_number',
                'processor:id,full_name',
            ])
            ->orderByDesc('reservation_date')
            ->paginate(20)
            ->withQueryString();

        return view('reports.reservations', compact('reservations', 'summary', 'filters'));
    }

    public function reservationsCsv(Request $request): StreamedResponse
    {
        $filters = $this->transactionFilters($request, ['waiting', 'ready', 'completed', 'cancelled', 'expired']);
        $query = $this->reservationBaseQuery($filters)
            ->with([
                'member:id,member_code,member_name,member_type',
                'item:id,item_code,item_name',
                'item.bookDetail:item_id,isbn_10,isbn_13,call_number',
                'processor:id,full_name',
            ])
            ->orderByDesc('reservation_date');

        return $this->csvDownload(
            'laporan-reservasi-'.now()->format('Ymd-His').'.csv',
            ['Kode reservasi', 'Tanggal reservasi', 'Kode anggota', 'Nama anggota', 'Judul buku', 'ISBN', 'Nomor antrean', 'Batas pengambilan', 'Status', 'Petugas'],
            $query->lazy(500),
            fn (Reservation $reservation): array => [
                $reservation->reservation_code,
                $reservation->reservation_date?->format('Y-m-d H:i:s'),
                $reservation->member?->member_code,
                $reservation->member?->member_name,
                $reservation->item?->item_name,
                $reservation->item?->bookDetail?->isbn_13 ?: $reservation->item?->bookDetail?->isbn_10,
                $reservation->queue_number,
                $reservation->expires_at?->format('Y-m-d H:i:s'),
                $reservation->statusLabel(),
                $reservation->processor?->full_name,
            ]
        );
    }

    private function inventoryFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'item_type' => ['nullable', 'in:book,equipment,electronic,furniture,consumable,other'],
            'tracking_type' => ['nullable', 'in:asset,quantity'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }

    private function inventoryBaseQuery(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Item::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('items.item_code', 'like', "%{$search}%")
                        ->orWhere('items.item_name', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('category_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['item_type'] ?? null, fn (Builder $query, string $type) => $query->where('items.item_type', $type))
            ->when($filters['tracking_type'] ?? null, fn (Builder $query, string $tracking) => $query->where('items.tracking_type', $tracking))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int|string $categoryId) => $query->where('items.category_id', $categoryId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('items.status', $status));
    }

    private function collectionFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'completion_status' => ['nullable', 'in:incomplete,complete,verified'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'shelf_id' => ['nullable', 'integer', 'exists:library_shelves,id'],
            'availability' => ['nullable', 'in:available,borrowed,unprocessed,without_shelf'],
        ]);
    }

    private function collectionBaseQuery(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Item::query()
            ->where('items.item_type', 'book')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('items.item_code', 'like', "%{$search}%")
                        ->orWhere('items.item_name', 'like', "%{$search}%")
                        ->orWhereHas('bookDetail', function (Builder $bookQuery) use ($search): void {
                            $bookQuery->where('isbn_10', 'like', "%{$search}%")
                                ->orWhere('isbn_13', 'like', "%{$search}%")
                                ->orWhere('call_number', 'like', "%{$search}%");
                        })
                        ->orWhereHas('authors', fn (Builder $authorQuery) => $authorQuery->where('author_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['completion_status'] ?? null, fn (Builder $query, string $status) => $query->whereHas('bookDetail', fn (Builder $bookQuery) => $bookQuery->where('completion_status', $status)))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int|string $categoryId) => $query->where('items.category_id', $categoryId))
            ->when($filters['shelf_id'] ?? null, fn (Builder $query, int|string $shelfId) => $query->whereHas('assets', fn (Builder $assetQuery) => $assetQuery->where('current_shelf_id', $shelfId)))
            ->when($filters['availability'] ?? null, function (Builder $query, string $availability): void {
                $query->whereHas('assets', function (Builder $assetQuery) use ($availability): void {
                    match ($availability) {
                        'available' => $assetQuery->where('asset_status', 'available'),
                        'borrowed' => $assetQuery->where('asset_status', 'borrowed'),
                        'unprocessed' => $assetQuery->where('asset_status', 'unprocessed'),
                        'without_shelf' => $assetQuery->whereNull('current_shelf_id'),
                        default => null,
                    };
                });
            });
    }

    private function transactionFilters(Request $request, array $statuses): array
    {
        $dateToRules = ['nullable', 'date'];

        if ($request->filled('date_from')) {
            $dateToRules[] = 'after_or_equal:date_from';
        }

        return $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:'.implode(',', $statuses)],
            'date_from' => ['nullable', 'date'],
            'date_to' => $dateToRules,
            'member_type' => ['nullable', 'in:student,teacher,staff,public'],
        ]);
    }

    private function loanBaseQuery(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Loan::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('loans.loan_code', 'like', "%{$search}%")
                        ->orWhereHas('member', function (Builder $memberQuery) use ($search): void {
                            $memberQuery->where('member_code', 'like', "%{$search}%")
                                ->orWhere('member_name', 'like', "%{$search}%")
                                ->orWhere('identity_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('loans.status', $status))
            ->when($filters['member_type'] ?? null, fn (Builder $query, string $type) => $query->whereHas('member', fn (Builder $memberQuery) => $memberQuery->where('member_type', $type)))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('loans.loan_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('loans.loan_date', '<=', $date));
    }

    private function fineFilters(Request $request): array
    {
        $dateToRules = ['nullable', 'date'];

        if ($request->filled('date_from')) {
            $dateToRules[] = 'after_or_equal:date_from';
        }

        return $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'payment_status' => ['nullable', 'in:unpaid,partial,paid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => $dateToRules,
            'member_type' => ['nullable', 'in:student,teacher,staff,public'],
        ]);
    }

    private function fineBaseQuery(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $paidSubquery = '(SELECT COALESCE(SUM(fp.amount), 0) FROM fine_payments fp WHERE fp.loan_item_id = loan_items.id)';

        return LoanItem::query()
            ->where('loan_items.fine_amount', '>', 0)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->whereHas('loan', function (Builder $loanQuery) use ($search): void {
                        $loanQuery->where('loan_code', 'like', "%{$search}%")
                            ->orWhereHas('member', function (Builder $memberQuery) use ($search): void {
                                $memberQuery->where('member_code', 'like', "%{$search}%")
                                    ->orWhere('member_name', 'like', "%{$search}%");
                            });
                    })->orWhereHas('asset', function (Builder $assetQuery) use ($search): void {
                        $assetQuery->where('asset_code', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%")
                            ->orWhereHas('item', fn (Builder $itemQuery) => $itemQuery->where('item_name', 'like', "%{$search}%"));
                    });
                });
            })
            ->when($filters['member_type'] ?? null, fn (Builder $query, string $type) => $query->whereHas('loan.member', fn (Builder $memberQuery) => $memberQuery->where('member_type', $type)))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('loan_items.returned_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('loan_items.returned_at', '<=', $date))
            ->when($filters['payment_status'] ?? null, function (Builder $query, string $status) use ($paidSubquery): void {
                match ($status) {
                    'unpaid' => $query->whereRaw("{$paidSubquery} <= 0"),
                    'partial' => $query->whereRaw("{$paidSubquery} > 0 AND {$paidSubquery} < loan_items.fine_amount"),
                    'paid' => $query->whereRaw("{$paidSubquery} >= loan_items.fine_amount"),
                    default => null,
                };
            });
    }

    private function memberFilters(Request $request): array
    {
        $dateToRules = ['nullable', 'date'];

        if ($request->filled('date_from')) {
            $dateToRules[] = 'after_or_equal:date_from';
        }

        return $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'member_type' => ['nullable', 'in:student,teacher,staff,public'],
            'status' => ['nullable', 'in:active,suspended,inactive,expired'],
            'date_from' => ['nullable', 'date'],
            'date_to' => $dateToRules,
        ]);
    }

    private function memberBaseQuery(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Member::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('members.member_code', 'like', "%{$search}%")
                        ->orWhere('members.member_name', 'like', "%{$search}%")
                        ->orWhere('members.identity_number', 'like', "%{$search}%")
                        ->orWhere('members.department', 'like', "%{$search}%")
                        ->orWhere('members.email', 'like', "%{$search}%");
                });
            })
            ->when($filters['member_type'] ?? null, fn (Builder $query, string $type) => $query->where('members.member_type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('members.status', $status))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('members.join_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('members.join_date', '<=', $date));
    }

    private function reservationBaseQuery(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Reservation::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('reservations.reservation_code', 'like', "%{$search}%")
                        ->orWhereHas('member', function (Builder $memberQuery) use ($search): void {
                            $memberQuery->where('member_code', 'like', "%{$search}%")
                                ->orWhere('member_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('item', function (Builder $itemQuery) use ($search): void {
                            $itemQuery->where('item_code', 'like', "%{$search}%")
                                ->orWhere('item_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('reservations.status', $status))
            ->when($filters['member_type'] ?? null, fn (Builder $query, string $type) => $query->whereHas('member', fn (Builder $memberQuery) => $memberQuery->where('member_type', $type)))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('reservations.reservation_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('reservations.reservation_date', '<=', $date));
    }

    private function outstandingFineTotal(): float
    {
        $fine = (float) LoanItem::query()->sum('fine_amount');
        $paid = (float) FinePayment::query()->sum('amount');

        return max($fine - $paid, 0);
    }

    private function csvDownload(string $filename, array $headers, iterable $rows, callable $mapper): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows, $mapper): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, ';', '"', '');

            foreach ($rows as $row) {
                $values = array_map(fn ($value) => $this->safeCsvCell($value), $mapper($row));
                fputcsv($handle, $values, ';', '"', '');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function safeCsvCell(mixed $value): string|int|float|null
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }

    private function itemTypeLabel(?string $type): string
    {
        return match ($type) {
            'book' => 'Buku',
            'equipment' => 'Peralatan',
            'electronic' => 'Elektronik',
            'furniture' => 'Furnitur',
            'consumable' => 'Barang habis pakai',
            'other' => 'Lainnya',
            default => '-',
        };
    }

    private function catalogStatusLabel(?string $status): string
    {
        return match ($status) {
            'incomplete' => 'Belum lengkap',
            'complete' => 'Lengkap',
            'verified' => 'Terverifikasi',
            default => 'Belum tersedia',
        };
    }

    private function fineStatusLabel(float $fine, float $paid): string
    {
        if ($fine <= 0 || $paid >= $fine) {
            return 'Lunas';
        }

        return $paid > 0 ? 'Dibayar sebagian' : 'Belum dibayar';
    }
}
