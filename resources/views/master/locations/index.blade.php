@extends('layouts.app')

@section('title', 'Lokasi')
@section('page-title', 'Master Lokasi')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card">
            <span>Total lokasi</span>
            <strong>{{ number_format($summary['total']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Lokasi aktif</span>
            <strong>{{ number_format($summary['active']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Ruang dan gudang</span>
            <strong>{{ number_format($summary['rooms']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Sudah digunakan</span>
            <strong>{{ number_format($summary['used']) }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Data bersama</p>
                <h2>Daftar Lokasi</h2>
            </div>
            <div class="panel-header-actions">

                <a href="{{ route('locations.deleted.index') }}" class="button-secondary">Daftar Hapus</a>

                <a href="{{ route('locations.create') }}" class="button-primary button-link">Tambah lokasi</a>

            </div>
        </div>

        <form method="GET" action="{{ route('locations.index') }}" class="filter-bar filter-bar-compact">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Kode, nama, atau deskripsi lokasi"
                >
            </div>

            <div class="filter-field">
                <label for="type">Jenis</label>
                <select id="type" name="type">
                    <option value="">Semua jenis</option>
                    @foreach ($typeLabels as $typeValue => $typeLabel)
                        <option value="{{ $typeValue }}" @selected(request('type') === $typeValue)>{{ $typeLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="button-primary">Terapkan</button>
                <a href="{{ route('locations.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Kode</th>
                        <th>Lokasi</th>
                        <th>Jenis</th>
                        <th>Lokasi induk</th>
                        <th>Penggunaan</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($locations as $location)
                        <tr><td class="table-number">{{ (is_object($locations) && method_exists($locations, 'firstItem') && $locations->firstItem() !== null ? $locations->firstItem() : 1) + $loop->index }}</td>
                            <td><strong>{{ $location->location_code }}</strong></td>
                            <td>
                                <div class="table-primary">{{ $location->location_name }}</div>
                                <div class="table-secondary">
                                    {{ $location->description ?: 'Tidak ada deskripsi.' }}
                                </div>
                            </td>
                            <td>{{ $typeLabels[$location->location_type] ?? ucfirst($location->location_type) }}</td>
                            <td>
                                @if ($location->parent)
                                    <div class="table-primary">{{ $location->parent->location_name }}</div>
                                    <div class="table-secondary">{{ $location->parent->location_code }}</div>
                                @else
                                    <span class="table-secondary">Lokasi utama</span>
                                @endif
                            </td>
                            <td>
                                <div class="table-primary">{{ number_format((int) $location->assets_count) }} aset</div>
                                <div class="table-secondary">
                                    {{ number_format((int) $location->shelves_count) }} rak,
                                    {{ number_format((int) $location->children_count) }} turunan
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $location->status === 'active' ? 'badge-success' : 'badge-muted' }}">
                                    {{ $location->status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('locations.edit', $location) }}" class="action-link">Edit</a>
                                    <form method="POST" action="{{ route('locations.toggle-status', $location) }}" onsubmit="return confirm('Hapus lokasi ini dari daftar aktif dan pindahkan ke Daftar Hapus?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="action-button">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Belum ada lokasi yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($locations->hasPages())
            <div class="pagination-bar">
                <span>
                    Menampilkan {{ $locations->firstItem() }} sampai {{ $locations->lastItem() }}
                    dari {{ $locations->total() }} lokasi
                </span>
                <div class="pagination-actions">
                    @if ($locations->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $locations->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif

                    <span class="page-indicator">Halaman {{ $locations->currentPage() }} dari {{ $locations->lastPage() }}</span>

                    @if ($locations->hasMorePages())
                        <a href="{{ $locations->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
