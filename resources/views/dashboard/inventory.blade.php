@extends('layouts.app')

@section('title', auth()->user()->hasRole('SUPER_ADMIN') ? 'Area Admin Inventaris' : 'Dashboard Inventaris')
@section('page-title', auth()->user()->hasRole('SUPER_ADMIN') ? 'Area Admin Inventaris' : 'Dashboard Admin Inventaris')

@section('content')
    <div class="role-banner">
        <div>
            @if (auth()->user()->hasRole('SUPER_ADMIN'))
                <p class="eyebrow">Mode area Super Admin</p>
                <h2>Pengelolaan inventaris</h2>
                <p>Super Admin sedang membuka area kerja Admin Inventaris. Akun, peran, dan hak akses tetap sebagai Super Admin.</p>
            @else
                <p class="eyebrow">Area kerja khusus</p>
                <h2>Pengelolaan inventaris</h2>
                <p>Akun ini mengelola barang, aset, stok, kategori, satuan, supplier, dan lokasi. Modul perpustakaan tidak dapat diubah oleh Admin Inventaris.</p>
            @endif
        </div>
        <span class="role-pill">{{ auth()->user()->hasRole('SUPER_ADMIN') ? 'SUPER ADMIN' : 'ADMIN INVENTARIS' }}</span>
    </div>

    <div class="stat-grid">
        <article class="stat-card"><span>Barang aktif</span><strong>{{ number_format($statistics['items']) }}</strong></article>
        <article class="stat-card"><span>Unit aset</span><strong>{{ number_format($statistics['assets']) }}</strong></article>
        <article class="stat-card"><span>Stok kuantitas</span><strong>{{ number_format($statistics['quantity_stock'], 2, ',', '.') }}</strong></article>
        <article class="stat-card"><span>Judul buku masuk</span><strong>{{ number_format($statistics['book_titles']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Aset rusak</span><strong>{{ number_format($statistics['damaged_assets']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Aset hilang</span><strong>{{ number_format($statistics['lost_assets']) }}</strong></article>
        <article class="stat-card {{ $statistics['pending_opnames'] > 0 ? 'stat-warning' : '' }}"><span>Stock opname berjalan</span><strong>{{ number_format($statistics['pending_opnames']) }}</strong></article>
        <article class="stat-card {{ $statistics['open_maintenance'] > 0 ? 'stat-warning' : '' }}"><span>Pemeliharaan aktif</span><strong>{{ number_format($statistics['open_maintenance']) }}</strong></article>
        <article class="stat-card {{ $statistics['pending_disposals'] > 0 ? 'stat-warning' : '' }}"><span>Penghapusan tertunda</span><strong>{{ number_format($statistics['pending_disposals']) }}</strong></article>
        <article class="stat-card {{ $statistics['public_damage_reports'] > 0 ? 'stat-warning' : '' }}"><span>Laporan kerusakan publik</span><strong>{{ number_format($statistics['public_damage_reports']) }}</strong></article>
    </div>

    <div class="quick-grid">
        <a class="quick-card" href="{{ route('inventory.items.create') }}">
            <span>+</span>
            <div>
                <strong>Tambah barang</strong>
                <small>Masukkan barang atau buku baru beserta stok awalnya.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('inventory.items.index') }}">
            <span>DB</span>
            <div>
                <strong>Daftar barang</strong>
                <small>Lihat, cari, edit, dan periksa detail inventaris.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('inventory.stock-opnames.index') }}">
            <span>SO</span>
            <div>
                <strong>Stock opname</strong>
                <small>Bandingkan data sistem dengan kondisi fisik pada setiap lokasi.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('inventory.maintenance-records.index') }}">
            <span>MT</span>
            <div>
                <strong>Pemeliharaan aset</strong>
                <small>Catat kerusakan, proses perbaikan, biaya, dan kondisi akhir aset.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('inventory.disposals.index') }}">
            <span>HP</span>
            <div>
                <strong>Penghapusan aset</strong>
                <small>Ajukan, setujui, dan laksanakan penghapusan aset secara tercatat.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('inventory.public-damage-reports.index') }}">
            <span>LP</span>
            <div>
                <strong>Laporan kerusakan publik</strong>
                <small>Periksa laporan barang atau buku rusak yang dikirim tanpa login.</small>
            </div>
        </a>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Data terbaru</p>
                <h2>Barang baru dimasukkan</h2>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Kode</th>
                        <th>Nama barang</th>
                        <th>Jenis</th>
                        <th>Kategori</th>
                        <th>Tanggal masuk</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($latestItems as $item)
                        <tr><td class="table-number">{{ (is_object($latestItems) && method_exists($latestItems, 'firstItem') && $latestItems->firstItem() !== null ? $latestItems->firstItem() : 1) + $loop->index }}</td>
                            <td>{{ $item->item_code }}</td>
                            <td class="table-primary">{{ $item->item_name }}</td>
                            <td><span class="badge badge-neutral">{{ $item->item_type }}</span></td>
                            <td>{{ $item->category_name ?? '-' }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($item->created_at)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Belum ada barang yang dimasukkan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
