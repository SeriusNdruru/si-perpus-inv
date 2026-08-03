@extends('layouts.app')

@section('title', 'Data Barang')
@section('page-title', 'Inventaris Barang')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card">
            <span>Total barang aktif</span>
            <strong>{{ number_format($summary['active']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Barang di Daftar Hapus</span>
            <strong>{{ number_format($summary['deleted']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Judul buku aktif</span>
            <strong>{{ number_format($summary['books']) }}</strong>
        </article>
        <article class="stat-card {{ $summary['unprocessed_books'] > 0 ? 'stat-warning' : '' }}">
            <span>Eksemplar belum diproses</span>
            <strong>{{ number_format($summary['unprocessed_books']) }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Inventaris terintegrasi</p>
                <h2>Daftar Barang</h2>
            </div>
            <div class="panel-header-actions">
                <a href="{{ route('inventory.deleted-items.index') }}" class="button-secondary">Daftar Hapus</a>
                <a href="{{ route('inventory.items.create') }}" class="button-primary button-link">Tambah barang</a>
            </div>
        </div>

        <form method="GET" action="{{ route('inventory.items.index') }}" class="filter-bar filter-bar-items">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Kode, nama, kontrak, jenis aset, atau SKPD"
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
                <a href="{{ route('inventory.items.index') }}" class="button-secondary">Reset</a>
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
                            <td>
                                <span class="badge {{ $item->status === 'active' ? 'badge-success' : 'badge-muted' }}">
                                    {{ $item->status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('inventory.items.show', $item) }}" class="action-link">Detail</a>
                                    <a href="{{ route('inventory.items.edit', $item) }}" class="action-link">Edit</a>
                                    <form
                                        method="POST"
                                        action="{{ route('inventory.items.toggle-status', $item) }}"
                                        onsubmit="return confirm('Hapus barang ini dari Daftar Barang dan pindahkan ke Daftar Hapus?');"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="action-button">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Belum ada barang aktif yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="pagination-bar">
                <span>
                    Menampilkan {{ $items->firstItem() }} sampai {{ $items->lastItem() }}
                    dari {{ $items->total() }} barang
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
