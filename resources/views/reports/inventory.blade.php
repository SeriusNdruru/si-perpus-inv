@extends('layouts.app')

@section('title', 'Laporan Inventaris')
@section('page-title', 'Laporan Inventaris')

@section('content')
    @include('reports._tabs')

    <div class="report-stat-grid">
        <article class="stat-card"><span>Jenis barang</span><strong>{{ number_format($summary['total_items']) }}</strong></article>
        <article class="stat-card"><span>Barang aktif</span><strong>{{ number_format($summary['active_items']) }}</strong></article>
        <article class="stat-card"><span>Total aset</span><strong>{{ number_format($summary['total_assets']) }}</strong></article>
        <article class="stat-card"><span>Saldo stok jumlah</span><strong>{{ number_format($summary['quantity_stock'], 2, ',', '.') }}</strong></article>
        <article class="stat-card stat-warning"><span>Rusak atau hilang</span><strong>{{ number_format($summary['damaged_or_lost']) }}</strong></article>
        <article class="stat-card"><span>Nilai perolehan aset</span><strong>Rp{{ number_format($summary['acquisition_value'], 0, ',', '.') }}</strong></article>
    </div>

    <section class="panel report-panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Data gabungan</p>
                <h2>Barang dan saldo inventaris</h2>
            </div>
            <div class="report-actions no-print">
                <button type="button" class="button-secondary" onclick="window.print()">Cetak</button>
                <a href="{{ route('reports.inventory.csv', request()->query()) }}" class="button-primary button-link">Unduh CSV</a>
            </div>
        </div>

        <form method="GET" action="{{ route('reports.inventory') }}" class="filter-bar filter-bar-report inventory-report-filter no-print">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Kode, nama barang, atau kategori">
            </div>
            <div class="filter-field">
                <label for="item_type">Jenis</label>
                <select id="item_type" name="item_type">
                    <option value="">Semua jenis</option>
                    @foreach (['book' => 'Buku', 'equipment' => 'Peralatan', 'electronic' => 'Elektronik', 'furniture' => 'Furnitur', 'consumable' => 'Habis pakai', 'other' => 'Lainnya'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('item_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="tracking_type">Pencatatan</label>
                <select id="tracking_type" name="tracking_type">
                    <option value="">Semua metode</option>
                    <option value="asset" @selected(request('tracking_type') === 'asset')>Per aset</option>
                    <option value="quantity" @selected(request('tracking_type') === 'quantity')>Jumlah stok</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="category_id">Kategori</label>
                <select id="category_id" name="category_id">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->category_code }} · {{ $category->category_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua status</option>
                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Tidak aktif</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="button-primary">Terapkan</button>
                <a href="{{ route('reports.inventory') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="report-print-meta">Dicetak pada {{ now()->translatedFormat('d F Y H:i') }} oleh {{ auth()->user()->full_name }}</div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Barang</th><th>Jenis</th><th>Kategori</th><th>Status</th><th>Total aset</th><th>Tersedia</th><th>Dipinjam</th><th>Rusak/Hilang</th><th>Stok jumlah</th><th>Nilai perolehan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td><div class="table-primary">{{ $item->item_name }}</div><div class="table-secondary">{{ $item->item_code }} · {{ $item->unit?->unit_code }}</div></td>
                            <td>{{ $item->item_type === 'book' ? 'Buku' : ucfirst($item->item_type) }}<div class="table-secondary">{{ $item->tracking_type === 'asset' ? 'Per aset' : 'Jumlah stok' }}</div></td>
                            <td>{{ $item->category?->category_name ?? '-' }}</td>
                            <td><span class="badge {{ $item->status === 'active' ? 'badge-success' : 'badge-muted' }}">{{ $item->status === 'active' ? 'Aktif' : 'Tidak aktif' }}</span></td>
                            <td>{{ number_format((int) $item->total_assets) }}</td>
                            <td>{{ number_format((int) $item->available_assets) }}</td>
                            <td>{{ number_format((int) $item->borrowed_assets) }}</td>
                            <td>{{ number_format((int) $item->damaged_assets + (int) $item->lost_assets) }}</td>
                            <td>{{ number_format((float) ($item->quantity_stock ?? 0), 2, ',', '.') }}</td>
                            <td>Rp{{ number_format((float) ($item->acquisition_value ?? 0), 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="empty-state">Tidak ada data inventaris yang sesuai dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('reports._pagination', ['paginator' => $items, 'label' => 'barang'])
    </section>
@endsection
