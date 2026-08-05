@extends('layouts.member')

@section('title', 'Dashboard Anggota')
@section('page-title', 'Halo, '.$member->member_name)

@section('content')
<section class="member-welcome">
    <div>
        <span class="member-kicker">Keanggotaan {{ $member->statusLabel() }}</span>
        <h2>Kelola kegiatan perpustakaan dari satu dashboard.</h2>
        <p>Cari buku, kirim pengajuan, pantau tanggal kembali, dan periksa notifikasi tanpa harus datang ke meja petugas terlebih dahulu.</p>
        <a href="{{ route('member.books.index') }}" class="member-button member-button-primary">Cari buku</a>
        <a href="{{ route('member.activity.index') }}" class="member-button">Lihat aktivitas saya</a>
    </div>
    <div class="member-card-visual">
        <span>{{ $member->member_code }}</span>
        <strong>{{ $member->member_name }}</strong>
        <small>{{ $member->department ?: 'Siswa' }}</small>
    </div>
</section>

<div class="member-stat-grid">
    <article><span>Kunjungan membaca</span><strong>{{ $statistics['total_visits'] }}</strong></article>
    <article><span>Transaksi peminjaman</span><strong>{{ $statistics['total_loans'] }}</strong></article>
    <article><span>Buku sedang dipinjam</span><strong>{{ $statistics['active_books'] }}</strong></article>
    <article><span>Jatuh tempo besok</span><strong>{{ $statistics['due_tomorrow'] }}</strong></article>
    <article><span>Pengajuan aktif</span><strong>{{ $statistics['active_requests'] }}</strong></article>
    <article><span>Notifikasi baru</span><strong>{{ $statistics['unread_notifications'] }}</strong></article>
</div>

<div class="member-dashboard-grid">
    <section class="member-panel">
        <div class="member-panel-heading"><div><small>Pinjaman aktif</small><h2>Tanggal pengembalian terdekat</h2></div><a href="{{ route('member.history.loans') }}">Semua riwayat</a></div>
        <div class="member-loan-list">
            @forelse ($activeLoans as $loan)
                <article class="numbered-list-item" data-list-number="{{ $loop->iteration }}">
                    <div class="member-mini-cover">
                        @if ($loan->cover_path)<img src="{{ route('media.thumbnail', ['path' => $loan->cover_path, 'size' => 160]) }}" alt="" loading="lazy" decoding="async" fetchpriority="low" data-image-retry>@else<span>BK</span>@endif
                    </div>
                    <div><strong>{{ $loan->item_name }}</strong><small>{{ $loan->loan_code }}</small></div>
                    <time class="{{ \Illuminate\Support\Carbon::parse($loan->due_date)->isBefore(today()) ? 'is-overdue' : '' }}">{{ \Illuminate\Support\Carbon::parse($loan->due_date)->format('d M Y') }}</time>
                </article>
            @empty
                <div class="member-empty">Belum ada buku yang sedang dipinjam.</div>
            @endforelse
        </div>
    </section>

    <section class="member-panel">
        <div class="member-panel-heading"><div><small>Pembaruan</small><h2>Notifikasi terbaru</h2></div><a href="{{ route('member.notifications.index') }}">Lihat semua</a></div>
        <div class="member-notification-list">
            @forelse ($notifications as $notification)
                <article class="{{ $notification->is_read ? '' : 'is-unread' }}">
                    <span class="member-list-number">{{ $loop->iteration }}</span>
                    <div><strong>{{ $notification->title }}</strong><p>{{ $notification->message }}</p><small>{{ \Illuminate\Support\Carbon::parse($notification->created_at)->diffForHumans() }}</small></div>
                </article>
            @empty
                <div class="member-empty">Belum ada notifikasi.</div>
            @endforelse
        </div>
    </section>
</div>

<section class="member-panel">
    <div class="member-panel-heading"><div><small>Koleksi tersedia</small><h2>Rekomendasi untuk dipinjam</h2></div><a href="{{ route('member.books.index') }}">Buka katalog</a></div>
    <div class="member-book-grid">
        @forelse ($recommendedBooks as $book)
            <article class="numbered-list-item" data-list-number="{{ $loop->iteration }}">
                <div class="member-book-cover">
                    @if ($book->cover_path)<img src="{{ route('media.thumbnail', ['path' => $book->cover_path, 'size' => 480]) }}" alt="" loading="lazy" decoding="async" fetchpriority="low" data-image-retry>@else<span>{{ mb_strtoupper(mb_substr($book->item_name, 0, 2)) }}</span>@endif
                </div>
                <h3>{{ $book->item_name }}</h3>
                <p>{{ $book->publication_year ?: 'Tahun belum dicantumkan' }} · {{ \App\Models\BookDetail::GRADE_LEVELS[$book->grade_level ?? 'umum'] ?? 'Umum / Semua Kelas' }}</p>
            </article>
        @empty
            <div class="member-empty">Belum ada buku yang dapat direkomendasikan.</div>
        @endforelse
    </div>
</section>
@endsection
