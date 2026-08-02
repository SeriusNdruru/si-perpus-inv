@extends('layouts.app')

@section('title', 'Dashboard Super Admin')
@section('page-title', 'Dashboard Super Admin')

@section('content')
    <div class="role-banner">
        <div>
            <p class="eyebrow">Kontrol sistem</p>
            <h2>Seluruh modul berada di bawah pengawasan Super Admin</h2>
            <p>Super Admin mengelola akun, hak akses, konfigurasi, serta dapat membuka area inventaris dan perpustakaan.</p>
        </div>
        <span class="role-pill">SUPER ADMIN</span>
    </div>

    <div class="stat-grid">
        <article class="stat-card"><span>Pengguna aktif</span><strong>{{ number_format($statistics['users']) }}</strong></article>
        <article class="stat-card"><span>Total barang</span><strong>{{ number_format($statistics['items']) }}</strong></article>
        <article class="stat-card"><span>Total aset</span><strong>{{ number_format($statistics['assets']) }}</strong></article>
        <article class="stat-card"><span>Judul buku</span><strong>{{ number_format($statistics['book_titles']) }}</strong></article>
        <article class="stat-card"><span>Anggota aktif</span><strong>{{ number_format($statistics['active_members']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Peminjaman terlambat</span><strong>{{ number_format($statistics['overdue_loans']) }}</strong></article>
    </div>

    <div class="quick-grid">
        <a class="quick-card" href="{{ route('dashboard.inventory') }}">
            <span>IN</span>
            <div>
                <strong>Area Admin Inventaris</strong>
                <small>Barang, aset, stok, kategori, satuan, supplier, dan lokasi.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('dashboard.library') }}">
            <span>LB</span>
            <div>
                <strong>Area Admin Perpustakaan</strong>
                <small>Katalog buku, rak, anggota, peminjaman, dan pengembalian.</small>
            </div>
        </a>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Pemisahan akun</p>
                <h2>Daftar akun administrator</h2>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Peran</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($administrators as $administrator)
                        <tr>
                            <td class="table-primary">{{ $administrator->full_name }}</td>
                            <td>{{ $administrator->username }}</td>
                            <td><span class="badge badge-neutral">{{ $administrator->role_name }}</span></td>
                            <td>
                                <span class="badge {{ $administrator->status === 'active' ? 'badge-success' : 'badge-muted' }}">
                                    {{ $administrator->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">Belum ada akun administrator.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
