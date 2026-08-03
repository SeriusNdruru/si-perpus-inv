@extends('layouts.member')

@section('title', 'Katalog Buku')
@section('page-title', 'Katalog Buku')

@section('content')
<div class="member-page-heading">
    <div><span class="member-kicker">Pilih beberapa judul</span><h2>Ajukan buku yang tersedia</h2><p>Tambahkan buku ke keranjang, lalu kirim satu pengajuan untuk seluruh pilihan.</p></div>
    <a href="{{ route('member.books.cart') }}" class="member-button member-button-primary">Keranjang ({{ $cart->count() }})</a>
</div>

<form method="GET" class="member-filter">
    <input name="search" type="search" value="{{ request('search') }}" placeholder="Judul, penulis, ISBN, atau kode">
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
    <button type="submit" class="member-button member-button-primary">Cari</button>
    <a href="{{ route('member.books.index') }}" class="member-button member-button-soft">Reset</a>
</form>

<div class="member-book-grid member-book-grid-catalog">
    @forelse ($books as $book)
        <article class="numbered-list-item {{ $book->available_copies < 1 ? 'is-unavailable' : '' }}" data-list-number="{{ (is_object($books) && method_exists($books, 'firstItem') && $books->firstItem() !== null ? $books->firstItem() : 1) + $loop->index }}">
            <div class="member-book-cover">
                @if ($book->cover_path)<img src="{{ route('media.thumbnail', ['path' => $book->cover_path, 'size' => 480]) }}" alt="Cover {{ $book->item_name }}" loading="lazy" decoding="async" fetchpriority="low" data-image-retry>@else<span>{{ mb_strtoupper(mb_substr($book->item_name, 0, 2)) }}</span>@endif
                <em>{{ $book->available_copies }} tersedia</em>
            </div>
            <small>{{ $book->author_names ?: 'Penulis belum dicantumkan' }}</small>
            <h3>{{ $book->item_name }}</h3>
            <p>{{ $book->publisher_name ?: '-' }}{{ $book->publication_year ? ' · '.$book->publication_year : '' }}</p>
            <p><strong>{{ \App\Models\BookDetail::GRADE_LEVELS[$book->grade_level ?? 'umum'] ?? 'Umum / Semua Kelas' }}</strong></p>
            <div class="member-book-actions">
                @if ($cart->contains($book->id))
                    <form method="POST" action="{{ route('member.books.cart.remove', $book->id) }}">@csrf @method('DELETE')<button class="member-button member-button-soft" type="submit">Hapus dari keranjang</button></form>
                @elseif ($book->available_copies > 0)
                    <form method="POST" action="{{ route('member.books.cart.add', $book->id) }}">@csrf<button class="member-button member-button-primary" type="submit">Tambah ke pengajuan</button></form>
                @else
                    <span class="member-status member-status-muted">Belum tersedia</span>
                @endif
            </div>
        </article>
    @empty
        <div class="member-empty">Buku tidak ditemukan.</div>
    @endforelse
</div>

<div class="member-pagination">
    @if ($books->onFirstPage())<span>Sebelumnya</span>@else<a href="{{ $books->previousPageUrl() }}">Sebelumnya</a>@endif
    <strong>{{ $books->currentPage() }} / {{ $books->lastPage() }}</strong>
    @if ($books->hasMorePages())<a href="{{ $books->nextPageUrl() }}">Berikutnya</a>@else<span>Berikutnya</span>@endif
</div>
@endsection
