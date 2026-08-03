@extends('layouts.app')

@section('title', 'Penempatan Buku')
@section('page-title', 'Penempatan Eksemplar ke Rak')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card"><span>Total eksemplar aktif</span><strong>{{ number_format($summary['total']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Belum memiliki rak</span><strong>{{ number_format($summary['without_shelf']) }}</strong></article>
        <article class="stat-card"><span>Sudah ditempatkan</span><strong>{{ number_format($summary['assigned']) }}</strong></article>
        <article class="stat-card"><span>Siap dipinjam</span><strong>{{ number_format($summary['ready']) }}</strong></article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Perpustakaan</p>
                <h2>Daftar Eksemplar Buku</h2>
                <p class="panel-description">Tentukan rak untuk setiap unit fisik buku. Buku dengan katalog lengkap otomatis menjadi tersedia setelah ditempatkan.</p>
            </div>
            <a href="{{ route('library.shelves.index') }}" class="button-secondary button-link">Kelola master rak</a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger form-errors inline-form-errors">
                <strong>Penempatan belum dapat diproses.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="GET" action="{{ route('library.shelf-assignments.index') }}" class="filter-bar filter-bar-shelf-assignments">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Kode aset, barcode, judul, ISBN, atau penulis">
            </div>

            <div class="filter-field">
                <label for="assignment">Penempatan</label>
                <select id="assignment" name="assignment">
                    <option value="">Semua penempatan</option>
                    <option value="without_shelf" @selected(request('assignment') === 'without_shelf')>Belum memiliki rak</option>
                    <option value="assigned" @selected(request('assignment') === 'assigned')>Sudah memiliki rak</option>
                </select>
            </div>

            <div class="filter-field">
                <label for="shelf_id_filter">Rak</label>
                <select id="shelf_id_filter" name="shelf_id">
                    <option value="">Semua rak</option>
                    @foreach ($filterShelves as $shelf)
                        <option value="{{ $shelf->id }}" @selected((string) request('shelf_id') === (string) $shelf->id)>
                            {{ $shelf->shelf_code }} - {{ $shelf->shelf_name }}{{ $shelf->status === 'inactive' ? ' (nonaktif)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label for="asset_status">Status eksemplar</label>
                <select id="asset_status" name="asset_status">
                    <option value="">Semua status</option>
                    @foreach ($assetStatuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('asset_status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label for="catalog_status">Status katalog</label>
                <select id="catalog_status" name="catalog_status">
                    <option value="">Semua katalog</option>
                    @foreach ($catalogStatuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('catalog_status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="button-primary">Terapkan</button>
                <a href="{{ route('library.shelf-assignments.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <form method="POST" action="{{ route('library.shelf-assignments.bulk-update') }}" id="bulk-assignment-form" class="bulk-assignment-bar">
            @csrf
            <div>
                <label for="bulk_shelf_id">Rak tujuan untuk pilihan</label>
                <select id="bulk_shelf_id" name="shelf_id" required>
                    <option value="">Pilih rak aktif</option>
                    @foreach ($shelves as $shelf)
                        @php
                            $occupied = (int) $shelf->occupied_count;
                            $remaining = $shelf->capacity !== null ? max(0, (int) $shelf->capacity - $occupied) : null;
                            $isFull = $remaining !== null && $remaining <= 0;
                        @endphp
                        <option value="{{ $shelf->id }}" @selected((string) old('shelf_id') === (string) $shelf->id) @disabled($isFull)>
                            {{ $shelf->shelf_code }} - {{ $shelf->shelf_name }} {{ $remaining !== null ? '(sisa '.$remaining.')' : '(tanpa batas)' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="bulk-note-field">
                <label for="bulk_notes">Catatan, opsional</label>
                <input id="bulk_notes" name="notes" type="text" maxlength="255" value="{{ old('notes') }}" placeholder="Contoh: Penempatan koleksi baru Juli 2026">
            </div>
            <button type="submit" class="button-primary" id="bulk-submit" disabled>Tempatkan <span id="selected-count">0</span> pilihan</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th class="checkbox-column"><input type="checkbox" id="select-all-assets" aria-label="Pilih semua eksemplar yang dapat diproses"></th>
                        <th>Eksemplar dan buku</th>
                        <th>Katalog</th>
                        <th>Rak saat ini</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assets as $asset)
                        @php
                            $catalogStatus = $asset->item?->bookDetail?->completion_status ?? 'incomplete';
                            $canAssign = in_array($asset->asset_status, ['unprocessed', 'available'], true);
                            $authors = $asset->item?->authors?->pluck('author_name')->join(', ') ?? '';
                        @endphp
                        <tr><td class="table-number">{{ (is_object($assets) && method_exists($assets, 'firstItem') && $assets->firstItem() !== null ? $assets->firstItem() : 1) + $loop->index }}</td>
                            <td class="checkbox-column">
                                <input type="checkbox" name="asset_ids[]" value="{{ $asset->id }}" form="bulk-assignment-form" class="js-asset-checkbox" @checked(in_array($asset->id, old('asset_ids', []))) @disabled(! $canAssign) aria-label="Pilih {{ $asset->asset_code }}">
                            </td>
                            <td>
                                <div class="table-primary">{{ $asset->asset_code }}</div>
                                <div class="table-secondary">{{ $asset->item?->item_name ?? '-' }}</div>
                                <div class="table-tertiary">{{ $asset->barcode }}{{ $authors !== '' ? ' · '.$authors : '' }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $catalogStatus === 'incomplete' ? 'badge-warning' : 'badge-success' }}">{{ $catalogStatuses[$catalogStatus] ?? $catalogStatus }}</span>
                                <div class="table-secondary">{{ $asset->item?->bookDetail?->call_number ?: 'Nomor panggil belum diisi' }}</div>
                            </td>
                            <td>
                                @if ($asset->shelf)
                                    <div class="table-primary">{{ $asset->shelf->shelf_code }}</div>
                                    <div class="table-secondary">{{ $asset->shelf->shelf_name }}</div>
                                    <div class="table-tertiary">{{ $asset->shelf->location?->location_name ?? 'Lokasi rak belum diatur' }}</div>
                                @else
                                    <span class="badge badge-warning">Belum diatur</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $asset->asset_status === 'available' ? 'badge-success' : ($asset->asset_status === 'unprocessed' ? 'badge-warning' : 'badge-neutral') }}">{{ $assetStatuses[$asset->asset_status] ?? $asset->asset_status }}</span>
                                <div class="table-secondary">Kondisi: {{ str($asset->condition_status)->replace('_', ' ')->title() }}</div>
                            </td>
                            <td>
                                @if ($canAssign)
                                    <a href="{{ route('library.shelf-assignments.edit', $asset) }}" class="action-link">{{ $asset->current_shelf_id ? 'Ubah rak' : 'Atur rak' }}</a>
                                @else
                                    <span class="table-secondary">Rak terkunci saat {{ strtolower($assetStatuses[$asset->asset_status] ?? $asset->asset_status) }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">Belum ada eksemplar buku yang sesuai dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($assets->hasPages())
            <div class="pagination-bar">
                <span>Menampilkan {{ $assets->firstItem() }} sampai {{ $assets->lastItem() }} dari {{ $assets->total() }} eksemplar</span>
                <div class="pagination-actions">
                    @if ($assets->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $assets->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif
                    <span class="page-indicator">Halaman {{ $assets->currentPage() }} dari {{ $assets->lastPage() }}</span>
                    @if ($assets->hasMorePages())
                        <a href="{{ $assets->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>

    <script src="{{ asset('js/shelf-assignment.js') }}" defer></script>
@endsection
