@extends('layouts.app')

@section('title', 'Laporan Terpadu')
@section('page-title', 'Laporan Terpadu')

@section('content')
    @include('reports._tabs')

    <section class="role-banner report-intro">
        <div>
            <p class="eyebrow">Pusat informasi</p>
            <h2>Data siap diperiksa dan diekspor</h2>
            <p>Pilih laporan berdasarkan tanggung jawab akun. Filter yang diterapkan pada layar juga digunakan saat mengunduh CSV.</p>
        </div>
        <span class="role-pill">READ ONLY</span>
    </section>

    <div class="report-card-grid">
        @if ($canInventory)
            <a href="{{ route('reports.inventory') }}" class="report-link-card">
                <span class="report-card-code">INV</span>
                <div>
                    <strong>Laporan Inventaris</strong>
                    <p>Barang, aset, saldo stok, kondisi, dan nilai perolehan.</p>
                    <small>{{ number_format((int) $summary['items']) }} barang · {{ number_format((int) $summary['assets']) }} aset</small>
                </div>
            </a>
        @endif

        @if ($canLibrary)
            <a href="{{ route('reports.collection') }}" class="report-link-card">
                <span class="report-card-code">BKU</span>
                <div>
                    <strong>Laporan Koleksi Buku</strong>
                    <p>Kelengkapan katalog, status eksemplar, rak, dan antrean reservasi.</p>
                    <small>{{ number_format((int) $summary['books']) }} judul buku</small>
                </div>
            </a>

            <a href="{{ route('reports.loans') }}" class="report-link-card">
                <span class="report-card-code">PJM</span>
                <div>
                    <strong>Laporan Peminjaman</strong>
                    <p>Transaksi berdasarkan periode, status, dan jenis anggota.</p>
                    <small>{{ number_format((int) $summary['active_loans']) }} eksemplar belum kembali</small>
                </div>
            </a>

            <a href="{{ route('reports.fines') }}" class="report-link-card">
                <span class="report-card-code">DND</span>
                <div>
                    <strong>Laporan Denda</strong>
                    <p>Denda final, pembayaran, dan sisa tagihan anggota.</p>
                    <small>Sisa Rp{{ number_format((float) $summary['outstanding_fines'], 0, ',', '.') }}</small>
                </div>
            </a>

            <a href="{{ route('reports.members') }}" class="report-link-card">
                <span class="report-card-code">AGT</span>
                <div>
                    <strong>Laporan Anggota</strong>
                    <p>Status keanggotaan, aktivitas pinjaman, reservasi, dan denda.</p>
                    <small>Data anggota aktif dan historis</small>
                </div>
            </a>

            <a href="{{ route('reports.reservations') }}" class="report-link-card">
                <span class="report-card-code">RSV</span>
                <div>
                    <strong>Laporan Reservasi</strong>
                    <p>Antrean, buku siap diambil, selesai, dan kedaluwarsa.</p>
                    <small>{{ number_format((int) $summary['active_reservations']) }} reservasi aktif</small>
                </div>
            </a>
        @endif
    </div>
@endsection
