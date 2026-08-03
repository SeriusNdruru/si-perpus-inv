@extends('layouts.app')

@section('title', 'Daftar Hapus Barang')
@section('page-title', 'Daftar Hapus Barang')

@section('content')
    <div class="stat-grid">
        <article class="stat-card">
            <span>Total barang dihapus</span>
            <strong>{{ number_format($deletedCount) }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Arsip barang</p>
                <h2>Daftar Hapus</h2>
                <p class="panel-description">Barang yang dihapus tidak tampil lagi pada Daftar Barang, tetapi riwayat dan relasinya tetap aman.</p>
            </div>
            <a href="{{ route('inventory.items.index') }}" class="button-secondary">Kembali ke Daftar Barang</a>
        </div>

        <form method="GET" action="{{ route('inventory.deleted-items.index') }}" class="filter-bar filter-bar-items">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Kode atau nama barang"
                >
            </div>

            <div class="filter-field">
                <label for="item_type">Jenis</label>
                <select id="item_type" name="item_type">
                    <option value="">Semua jenis</option>
                    @foreach ($itemTypes as $value => $label)
                        <option value="{{ $value }}" @selected(request('item_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label for="tracking_type">Pencatatan</label>
                <select id="tracking_type" name="tracking_type">
                    <option value="">Semua pencatatan</option>
                    @foreach ($trackingTypes as $value => $label)
                        <option value="{{ $value }}" @selected(request('tracking_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="button-primary">Terapkan</button>
                <a href="{{ route('inventory.deleted-items.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Kode</th>
                        <th>Barang</th>
                        <th>Jenis</th>
                        <th>Kategori</th>
                        <th>Stok</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            @php($itemImagePath = $item->image_path ?: $item->bookDetail?->cover_path)
                            <td>
                                <div class="item-table-photo">
                                    @if ($itemImagePath)
                                        <img src="{{ asset('storage/'.$itemImagePath) }}" alt="Foto {{ $item->item_name }}">
                                    @else
                                        <span>{{ mb_strtoupper(mb_substr($item->item_name, 0, 2)) }}</span>
                                    @endif
                                </div>
                            </td>
                            <td><strong>{{ $item->item_code }}</strong></td>
                            <td>
                                <div class="table-primary">{{ $item->item_name }}</div>
                                <div class="table-secondary">
                                    {{ $trackingTypes[$item->tracking_type] ?? $item->tracking_type }}
                                    @if ($item->item_type === 'book')
                                        · Katalog {{ $item->bookDetail?->completion_status === 'incomplete' ? 'belum lengkap' : 'sudah dilengkapi' }}
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-neutral">{{ $itemTypes[$item->item_type] ?? $item->item_type }}</span>
                            </td>
                            <td>{{ $item->category?->category_name ?? '-' }}</td>
                            <td>
                                @if ($item->tracking_type === 'asset')
                                    <div class="table-primary">{{ number_format((int) $item->assets_count) }} {{ $item->unit?->unit_code }}</div>
                                    <div class="table-secondary">{{ number_format((int) $item->available_assets_count) }} tersedia</div>
                                @else
                                    <div class="table-primary">{{ number_format((float) $item->quantity_stock, 2, ',', '.') }} {{ $item->unit?->unit_code }}</div>
                                    <div class="table-secondary">Stok berbasis jumlah</div>
                                @endif
                            </td>
                            <td><span class="badge badge-muted">Dihapus</span></td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('inventory.items.show', $item) }}" class="action-link">Detail</a>
                                    <form
                                        method="POST"
                                        action="{{ route('inventory.deleted-items.restore', $item) }}"
                                        onsubmit="return confirm('Pulihkan barang ini ke Daftar Barang?');"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="action-button">Pulihkan</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Belum ada barang di Daftar Hapus.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="pagination-bar">
                <span>
                    Menampilkan {{ $items->firstItem() }} sampai {{ $items->lastItem() }}
                    dari {{ $items->total() }} barang dihapus
                </span>
                <div class="pagination-actions">
                    @if ($items->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $items->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif

                    <span class="page-indicator">Halaman {{ $items->currentPage() }} dari {{ $items->lastPage() }}</span>

                    @if ($items->hasMorePages())
                        <a href="{{ $items->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
