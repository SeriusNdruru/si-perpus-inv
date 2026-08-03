@extends('layouts.member')

@section('title', 'Keranjang Pengajuan')
@section('page-title', 'Keranjang Pengajuan')

@section('content')
<div class="member-page-heading">
    <div><span class="member-kicker">Satu pengajuan, banyak buku</span><h2>Periksa pilihan buku</h2><p>Petugas akan menentukan eksemplar fisik yang tersedia setelah pengajuan dikirim.</p></div>
    <a href="{{ route('member.books.index') }}" class="member-button member-button-soft">Tambah buku lain</a>
</div>

<section class="member-panel">
    <div class="member-cart-list">
        @forelse ($books as $book)
            <article class="numbered-list-item" data-list-number="{{ $loop->iteration }}">
                <div class="member-mini-cover">@if ($book->bookDetail?->cover_path)<img src="{{ route('media.thumbnail', ['path' => $book->bookDetail->cover_path, 'size' => 160]) }}" alt="" loading="lazy" decoding="async" fetchpriority="low" data-image-retry>@else<span>BK</span>@endif</div>
                <div><strong>{{ $book->item_name }}</strong><small>{{ $book->authors->pluck('author_name')->join(', ') ?: 'Penulis belum dicantumkan' }}</small></div>
                <form method="POST" action="{{ route('member.books.cart.remove', $book) }}">@csrf @method('DELETE')<button type="submit">Hapus</button></form>
            </article>
        @empty
            <div class="member-empty">Keranjang masih kosong.</div>
        @endforelse
    </div>

    @if ($books->isNotEmpty())
        <form method="POST" action="{{ route('member.loan-requests.store') }}" class="member-request-form">
            @csrf
            <label><span>Catatan untuk petugas</span><textarea name="member_notes" rows="4" maxlength="2000" placeholder="Contoh: Akan diambil setelah jam pelajaran selesai.">{{ old('member_notes') }}</textarea></label>
            <div class="member-form-note">Jumlah buku yang dapat diproses mengikuti batas maksimal pinjaman aktif pada Pengaturan Sistem.</div>
            <button type="submit" class="member-button member-button-primary">Kirim pengajuan {{ $books->count() }} buku</button>
        </form>
    @endif
</section>
@endsection
