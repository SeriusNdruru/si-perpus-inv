@extends('layouts.member')

@section('title', 'Aktivitas Perpustakaan Saya')
@section('page-title', 'Aktivitas Perpustakaan Saya')

@section('content')
<div class="member-page-heading">
    <div><span class="member-kicker">Rekam aktivitas</span><h2>Kunjungan dan buku yang pernah dipinjam</h2><p>Data di bawah berasal dari pencatatan Admin Perpustakaan dan transaksi peminjaman Anda.</p></div>
</div>

<div class="member-stat-grid">
    <article><span>Kunjungan membaca</span><strong>{{ number_format($statistics['visits']) }}</strong></article>
    <article><span>Transaksi peminjaman</span><strong>{{ number_format($statistics['loan_transactions']) }}</strong></article>
    <article><span>Total buku dipinjam</span><strong>{{ number_format($statistics['borrowed_books']) }}</strong></article>
    <article><span>Sedang dipinjam</span><strong>{{ number_format($statistics['active_books']) }}</strong></article>
</div>

<section class="member-panel">
    <div class="member-panel-heading">
        <div><small>Kunjungan membaca</small><h2>Riwayat datang ke perpustakaan</h2></div>
        @if ($hasMoreVisits)
            <a href="{{ route('member.history.visits') }}">Lihat riwayat</a>
        @endif
    </div>
    <div class="member-table-wrap">
        <table class="member-table">
            <thead><tr><th class="table-number-heading">No.</th><th>Tanggal</th><th>Waktu</th><th>Kegiatan</th><th>Catatan</th></tr></thead>
            <tbody>
                @forelse ($visits as $visit)
                    <tr>
                        <td class="table-number">{{ $loop->iteration }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($visit->visit_date)->format('d/m/Y') }}</td>
                        <td>{{ substr((string) $visit->visit_time, 0, 5) }} WIB</td>
                        <td>{{ $visit->activity }}</td>
                        <td>{{ $visit->notes ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Belum ada kunjungan yang dicatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="member-panel">
    <div class="member-panel-heading">
        <div><small>Riwayat buku</small><h2>Buku yang pernah dipinjam</h2></div>
        @if ($hasMoreBorrowedBooks)
            <a href="{{ route('member.history.books') }}">Lihat riwayat</a>
        @endif
    </div>
    <div class="member-table-wrap">
        <table class="member-table">
            <thead><tr><th class="table-number-heading">No.</th><th>Buku</th><th>Kode buku</th><th>Tanggal pinjam</th><th>Jatuh tempo</th><th>Tanggal kembali</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($borrowedBooks as $book)
                    @php
                        $statusLabel = match ($book->return_status) {
                            'borrowed' => 'Masih dipinjam',
                            'returned' => 'Dikembalikan',
                            'damaged' => 'Rusak',
                            'lost' => 'Hilang',
                            default => ucfirst($book->return_status),
                        };
                    @endphp
                    <tr>
                        <td class="table-number">{{ $loop->iteration }}</td>
                        <td><strong>{{ $book->item_name }}</strong><div>{{ $book->loan_code }}</div></td>
                        <td>{{ $book->asset_code }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($book->borrowed_at)->format('d/m/Y H:i') }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($book->due_date)->format('d/m/Y') }}</td>
                        <td>{{ $book->returned_at ? \Illuminate\Support\Carbon::parse($book->returned_at)->format('d/m/Y H:i') : '-' }}</td>
                        <td><span class="member-status member-status-{{ $book->return_status }}">{{ $statusLabel }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7">Belum ada buku yang pernah dipinjam.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
