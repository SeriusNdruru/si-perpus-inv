@extends('layouts.public')

@section('title', 'Home')

@section('content')
<section class="portal-hero">
    <div class="portal-container portal-hero-grid">
        <div class="portal-hero-copy">
            <span class="portal-kicker">Perpustakaan digital dan inventaris terbuka</span>
            <h1>{{ $systemBrand['portal.hero_title'] ?? 'Perpustakaan yang dekat dengan siswa' }}</h1>
            <p>{{ $systemBrand['portal.hero_subtitle'] ?? '' }}</p>
            <div class="portal-hero-actions">
                @auth
                    <a href="{{ route('dashboard') }}" class="portal-button portal-button-primary">Buka dashboard</a>
                @else
                    <a href="{{ route('student.login') }}" class="portal-button portal-button-primary">Login siswa</a>
                    <a href="{{ route('register') }}" class="portal-button portal-button-outline">Buat akun siswa</a>
                @endauth
            </div>
            <div class="portal-hero-notes">
                <span>✓ Pengajuan banyak buku</span>
                <span>✓ Riwayat dan denda transparan</span>
                <span>✓ Pengingat H-1 pengembalian</span>
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
                <article class="portal-book-card">
                    <div class="portal-book-cover">
                        @if ($book->cover_path)
                            <img src="{{ asset('storage/'.$book->cover_path) }}" alt="Cover {{ $book->item_name }}">
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

<section class="portal-section">
    <div class="portal-container portal-split-card">
        <div>
            <span class="portal-kicker">Portal inventaris terpisah</span>
            <h2>Buka portal inventaris sekolah</h2>
            <p>Portal inventaris memiliki tampilan dan navigasi sendiri untuk laporan umum, audit aset, lokasi, serta pelaporan kerusakan.</p>
            <div class="portal-hero-actions">
                <a href="{{ route('public.inventory.general') }}" class="portal-button portal-button-primary">Buka portal inventaris</a>
                <a href="{{ route('public.inventory.audit') }}" class="portal-button portal-button-outline">Buka audit publik</a>
            </div>
        </div>
        <div class="portal-report-card">
            <span>Menemukan kerusakan?</span>
            <strong>Laporkan langsung tanpa login.</strong>
            <p>Pilih barang atau lokasi, tulis kondisi, dan lampirkan foto bila diperlukan.</p>
            <a href="{{ route('public.inventory.report-damage') }}">Lapor kerusakan →</a>
        </div>
    </div>
</section>
@endsection
