@extends('layouts.public')

@section('title', 'Katalog Buku')

@section('content')
<section class="portal-page-hero">
    <div class="portal-container">
        <span class="portal-kicker">Koleksi perpustakaan</span>
        <h1>Temukan buku untuk dipelajari</h1>
        <p>Login sebagai siswa untuk menambahkan beberapa buku ke satu pengajuan peminjaman.</p>
    </div>
</section>

<section class="portal-section portal-section-tight">
    <div class="portal-container">
        <form method="GET" class="portal-filter">
            <input name="search" type="search" value="{{ request('search') }}" placeholder="Judul, penulis, kode, atau ISBN">
            <select name="category">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) request('category') === $category->id)>{{ $category->category_name }}</option>
                @endforeach
            </select>
            <select name="grade_level">
                <option value="">Semua kelas</option>
                @foreach ($gradeLevels as $value => $label)
                    <option value="{{ $value }}" @selected(request('grade_level') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="portal-button portal-button-primary" type="submit">Cari</button>
            <a href="{{ route('public.catalog') }}" class="portal-button portal-button-soft">Reset</a>
        </form>

        <div class="portal-book-grid portal-book-grid-large">
            @forelse ($books as $book)
                <article class="portal-book-card numbered-list-item" data-list-number="{{ (is_object($books) && method_exists($books, 'firstItem') && $books->firstItem() !== null ? $books->firstItem() : 1) + $loop->index }}">
                    <div class="portal-book-cover">
                        @if ($book->cover_path)
                            <img src="{{ route('media.thumbnail', ['path' => $book->cover_path, 'size' => 480]) }}" alt="Cover {{ $book->item_name }}" loading="lazy" decoding="async" fetchpriority="low" data-image-retry>
                        @else
                            <span>{{ mb_strtoupper(mb_substr($book->item_name, 0, 2)) }}</span>
                        @endif
                        <em class="{{ $book->available_copies > 0 ? '' : 'is-empty' }}">
                            {{ $book->available_copies > 0 ? $book->available_copies.' tersedia' : 'Sedang dipinjam' }}
                        </em>
                    </div>
                    <div class="portal-book-body">
                        <small>{{ $book->author_names ?: 'Penulis belum dicantumkan' }}</small>
                        <h3>{{ $book->item_name }}</h3>
                        <p>{{ $book->publisher_name ?: '-' }}{{ $book->publication_year ? ' · '.$book->publication_year : '' }}</p>
                        <p><strong>{{ \App\Models\BookDetail::GRADE_LEVELS[$book->grade_level ?? 'umum'] ?? 'Umum / Semua Kelas' }}</strong></p>
                        <div class="portal-book-meta">
                            <span>{{ $book->call_number ?: 'Tanpa nomor panggil' }}</span>
                            <span>{{ $book->isbn_13 ?: ($book->isbn_10 ?: 'Tanpa ISBN') }}</span>
                        </div>
                    </div>
                </article>
            @empty
                <div class="portal-empty">Buku tidak ditemukan.</div>
            @endforelse
        </div>

        <div class="portal-pagination">
            @if ($books->onFirstPage())<span>Sebelumnya</span>@else<a href="{{ $books->previousPageUrl() }}">Sebelumnya</a>@endif
            <strong>Halaman {{ $books->currentPage() }} dari {{ $books->lastPage() }}</strong>
            @if ($books->hasMorePages())<a href="{{ $books->nextPageUrl() }}">Berikutnya</a>@else<span>Berikutnya</span>@endif
        </div>
    </div>
</section>
@endsection
