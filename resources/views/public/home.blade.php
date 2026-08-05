@extends('layouts.public')

@section('title', 'Home')

@section('content')
<section class="portal-hero">
    <div class="portal-container portal-hero-grid">
        <div class="portal-hero-copy">
            <span class="portal-kicker">Perpustakaan digital</span>
            <h1>{{ $systemBrand['portal.hero_title'] ?? 'Perpustakaan yang dekat dengan siswa' }}</h1>
            <p>{{ $systemBrand['portal.hero_subtitle'] ?? '' }}</p>
            <div class="portal-hero-actions">
                @auth
                    <a href="{{ route('dashboard') }}" class="portal-button portal-button-primary">Buka dashboard</a>
                @else
                    <a href="{{ route('student.login') }}" class="portal-button portal-button-primary">Login siswa</a>
                @endauth
            </div>
        </div>

        <div class="portal-hero-visual">
            <div class="portal-orbit portal-orbit-one"></div>
            <div class="portal-orbit portal-orbit-two"></div>
            <div class="portal-visual-card portal-visual-card-main">
                <span class="portal-mini-label">Koleksi tersedia</span>
                <strong>{{ number_format($statistics['available']) }}</strong>
                <small>eksemplar siap dipinjam</small>
            </div>
            <div class="portal-visual-card portal-visual-card-top">
                <span>📚</span>
                <strong>{{ number_format($statistics['titles']) }} judul</strong>
            </div>
            <div class="portal-visual-card portal-visual-card-bottom">
                <span>📍</span>
                <strong>{{ number_format($statistics['locations']) }} lokasi</strong>
            </div>
        </div>
    </div>
</section>

<section class="portal-stats">
    <div class="portal-container portal-stat-grid">
        <article><strong>{{ number_format($statistics['titles']) }}</strong><span>Judul buku</span></article>
        <article><strong>{{ number_format($statistics['available']) }}</strong><span>Buku tersedia</span></article>
        <article><strong>{{ number_format($statistics['members']) }}</strong><span>Anggota aktif</span></article>
        <article><strong>{{ number_format($statistics['locations']) }}</strong><span>Ruang dan lokasi</span></article>
    </div>
</section>

<section class="portal-section">
    <div class="portal-container">
        <div class="portal-section-heading">
            <div>
                <span class="portal-kicker">Pilihan terbaru</span>
                <h2>Buku yang bisa diajukan sekarang</h2>
                <p>Cover dan status ketersediaan berasal langsung dari data katalog perpustakaan.</p>
            </div>
            <a href="{{ route('public.catalog') }}" class="portal-text-link">Lihat semua katalog →</a>
        </div>

        <div class="portal-book-grid">
            @forelse ($featuredBooks as $book)
                <article class="portal-book-card numbered-list-item" data-list-number="{{ $loop->iteration }}">
                    <div class="portal-book-cover">
                        @if ($book->cover_path)
                            <img src="{{ route('media.thumbnail', ['path' => $book->cover_path, 'size' => 480]) }}" alt="Cover {{ $book->item_name }}" loading="lazy" decoding="async" fetchpriority="low" data-image-retry>
                        @else
                            <span>{{ mb_strtoupper(mb_substr($book->item_name, 0, 2)) }}</span>
                        @endif
                        <em>{{ $book->available_copies > 0 ? $book->available_copies.' tersedia' : 'Tidak tersedia' }}</em>
                    </div>
                    <div class="portal-book-body">
                        <small>{{ $book->author_names ?: 'Penulis belum dicantumkan' }}</small>
                        <h3>{{ $book->item_name }}</h3>
                        <p>{{ $book->publisher_name ?: 'Penerbit belum dicantumkan' }}{{ $book->publication_year ? ' · '.$book->publication_year : '' }}</p>
                        <p><strong>{{ \App\Models\BookDetail::GRADE_LEVELS[$book->grade_level ?? 'umum'] ?? 'Umum / Semua Kelas' }}</strong></p>
                    </div>
                </article>
            @empty
                <div class="portal-empty">Belum ada buku lengkap yang dapat ditampilkan.</div>
            @endforelse
        </div>
    </div>
</section>

<section class="portal-section portal-section-soft">
    <div class="portal-container portal-feature-grid">
        <article>
            <span>01</span>
            <h3>Cari dan ajukan</h3>
            <p>Siswa memilih beberapa judul sekaligus lalu mengirim satu pengajuan peminjaman.</p>
        </article>
        <article>
            <span>02</span>
            <h3>Disetujui petugas</h3>
            <p>Admin perpustakaan memeriksa ketersediaan, memesan eksemplar, dan menyiapkan buku.</p>
        </article>
        <article>
            <span>03</span>
            <h3>Ambil dan pantau</h3>
            <p>Setelah pengambilan dikonfirmasi, transaksi muncul di dashboard beserta tanggal kembali dan denda.</p>
        </article>
    </div>
</section>

@endsection
