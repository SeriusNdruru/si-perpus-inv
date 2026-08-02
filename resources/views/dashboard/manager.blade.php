@extends('layouts.app')

@section('title', 'Dashboard Pimpinan')
@section('page-title', 'Dashboard Pimpinan')

@section('content')
    <div class="role-banner">
        <div>
            <p class="eyebrow">Akses baca</p>
            <h2>Ringkasan inventaris dan perpustakaan</h2>
            <p>Akun pimpinan melihat statistik dan laporan tanpa mengubah data operasional.</p>
        </div>
        <span class="role-pill">PIMPINAN</span>
    </div>

    <div class="stat-grid">
        <article class="stat-card"><span>Barang aktif</span><strong>{{ number_format($statistics['items']) }}</strong></article>
        <article class="stat-card"><span>Total aset</span><strong>{{ number_format($statistics['assets']) }}</strong></article>
        <article class="stat-card"><span>Judul buku</span><strong>{{ number_format($statistics['book_titles']) }}</strong></article>
        <article class="stat-card"><span>Buku tersedia</span><strong>{{ number_format($statistics['available_books']) }}</strong></article>
        <article class="stat-card"><span>Anggota aktif</span><strong>{{ number_format($statistics['active_members']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Peminjaman terlambat</span><strong>{{ number_format($statistics['overdue_loans']) }}</strong></article>
    </div>

    <div class="panel placeholder-panel">
        <div class="placeholder-icon">RP</div>
        <h2>Laporan terintegrasi</h2>
        <p>Gunakan menu Laporan untuk melihat ringkasan inventaris dan perpustakaan.</p>
        <a href="{{ route('reports.index') }}" class="button-primary button-link">Buka laporan</a>
    </div>
@endsection
