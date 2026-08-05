@extends('layouts.app')

@section('title', 'Laporan Koleksi Buku')
@section('page-title', 'Laporan Koleksi Buku')

@section('content')
    @include('reports._tabs')

    <div class="report-stat-grid">
        <article class="stat-card"><span>Judul buku</span><strong>{{ number_format($summary['titles']) }}</strong></article>
        <article class="stat-card"><span>Total eksemplar</span><strong>{{ number_format($summary['copies']) }}</strong></article>
        <article class="stat-card"><span>Tersedia</span><strong>{{ number_format($summary['available']) }}</strong></article>
        <article class="stat-card"><span>Dipinjam</span><strong>{{ number_format($summary['borrowed']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Belum diproses</span><strong>{{ number_format($summary['unprocessed']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Belum memiliki rak</span><strong>{{ number_format($summary['without_shelf']) }}</strong></article>
    </div>

    <section class="panel report-panel">
        <div class="panel-header panel-header-wrap">
            <div><p class="eyebrow">Katalog dan sirkulasi</p><h2>Kondisi koleksi per judul</h2></div>
            <div class="report-actions no-print">
                <button type="button" class="button-secondary" onclick="window.print()">Cetak</button>
                <a href="{{ route('reports.collection.excel', request()->query()) }}" class="button-primary button-link">Unduh Excel</a>
            </div>
        </div>

        <form method="GET" action="{{ route('reports.collection') }}" class="filter-bar filter-bar-report collection-report-filter no-print">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Judul, kode, ISBN, penulis, atau nomor panggil">
            </div>
            <div class="filter-field">
                <label for="completion_status">Status katalog</label>
                <select id="completion_status" name="completion_status">
                    <option value="">Semua status</option>
                    <option value="incomplete" @selected(request('completion_status') === 'incomplete')>Belum lengkap</option>
                    <option value="complete" @selected(request('completion_status') === 'complete')>Lengkap</option>
                    <option value="verified" @selected(request('completion_status') === 'verified')>Terverifikasi</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="grade_level">Kategori kelas</label>
                <select id="grade_level" name="grade_level">
                    <option value="">Semua kelas</option>
                    @foreach (\App\Models\BookDetail::GRADE_LEVELS as $value => $label)
                        <option value="{{ $value }}" @selected(request('grade_level') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="category_id">Kategori</label>
                <select id="category_id" name="category_id">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->category_code }} · {{ $category->category_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="shelf_id">Rak</label>
                <select id="shelf_id" name="shelf_id">
                    <option value="">Semua rak</option>
                    @foreach ($shelves as $shelf)
                        <option value="{{ $shelf->id }}" @selected((string) request('shelf_id') === (string) $shelf->id)>{{ $shelf->shelf_code }} · {{ $shelf->shelf_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="availability">Kondisi eksemplar</label>
                <select id="availability" name="availability">
                    <option value="">Semua kondisi</option>
                    <option value="available" @selected(request('availability') === 'available')>Memiliki eksemplar tersedia</option>
                    <option value="borrowed" @selected(request('availability') === 'borrowed')>Memiliki eksemplar dipinjam</option>
                    <option value="unprocessed" @selected(request('availability') === 'unprocessed')>Belum diproses</option>
                    <option value="without_shelf" @selected(request('availability') === 'without_shelf')>Belum memiliki rak</option>
                </select>
            </div>
            <div class="filter-actions"><button type="submit" class="button-primary">Terapkan</button><a href="{{ route('reports.collection') }}" class="button-secondary">Reset</a></div>
        </form>

        <div class="report-print-meta">Dicetak pada {{ now()->translatedFormat('d F Y H:i') }} oleh {{ auth()->user()->full_name }}</div>

        <div class="table-wrap">
            <table>
                <thead><tr><th class="table-number-heading">No.</th><th>Buku</th><th>Bibliografi</th><th>Kategori</th><th>Katalog</th><th>Total</th><th>Tersedia</th><th>Dipinjam</th><th>Belum diproses</th><th>Tanpa rak</th><th>Reservasi</th></tr></thead>
                <tbody>
                    @forelse ($books as $book)
                        <tr><td class="table-number">{{ (is_object($books) && method_exists($books, 'firstItem') && $books->firstItem() !== null ? $books->firstItem() : 1) + $loop->index }}</td>
                            <td><div class="table-primary">{{ $book->item_name }}</div><div class="table-secondary">{{ $book->item_code }}</div></td>
                            <td>
                                <div class="table-primary">{{ $book->authors->pluck('author_name')->join(', ') ?: 'Penulis belum diisi' }}</div>
                                <div class="table-secondary">ISBN {{ $book->bookDetail?->isbn_13 ?: ($book->bookDetail?->isbn_10 ?: '-') }} · {{ $book->bookDetail?->publication_year ?: '-' }}</div>
                            </td>
                            <td>{{ $book->category?->category_name ?? '-' }}<div class="table-secondary">{{ $book->bookDetail?->grade_level_label ?? 'Umum / Semua Kelas' }} · {{ $book->bookDetail?->call_number ?: '-' }}</div></td>
                            <td><span class="badge {{ in_array($book->bookDetail?->completion_status, ['complete', 'verified'], true) ? 'badge-success' : 'badge-warning' }}">{{ match ($book->bookDetail?->completion_status) { 'verified' => 'Terverifikasi', 'complete' => 'Lengkap', default => 'Belum lengkap' } }}</span></td>
                            <td>{{ number_format((int) $book->total_copies) }}</td>
                            <td>{{ number_format((int) $book->available_copies) }}</td>
                            <td>{{ number_format((int) $book->borrowed_copies) }}</td>
                            <td>{{ number_format((int) $book->unprocessed_copies) }}</td>
                            <td>{{ number_format((int) $book->copies_without_shelf) }}</td>
                            <td>{{ number_format((int) $book->active_reservations) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="empty-state">Tidak ada koleksi buku yang sesuai dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('reports._pagination', ['paginator' => $books, 'label' => 'judul'])
    </section>
@endsection
