@extends('layouts.app')

@section('title', 'Rak Perpustakaan')
@section('page-title', 'Master Rak Perpustakaan')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card">
            <span>Total rak</span>
            <strong>{{ number_format($summary['total']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Rak aktif</span>
            <strong>{{ number_format($summary['active']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Eksemplar ditempatkan</span>
            <strong>{{ number_format($summary['occupied']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Total kapasitas tercatat</span>
            <strong>{{ number_format($summary['capacity']) }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Master perpustakaan</p>
                <h2>Daftar Rak</h2>
                <p class="panel-description">Atur identitas rak, lokasi ruangan, rentang klasifikasi, dan kapasitas buku.</p>
            </div>
            <a href="{{ route('library.shelves.create') }}" class="button-primary button-link">Tambah rak</a>
        </div>

        <form method="GET" action="{{ route('library.shelves.index') }}" class="filter-bar filter-bar-shelves">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Kode, nama, atau klasifikasi rak"
                >
            </div>

            <div class="filter-field">
                <label for="location_id">Lokasi</label>
                <select id="location_id" name="location_id">
                    <option value="">Semua lokasi</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>
                            {{ $location->location_code }} - {{ $location->location_name }}
                        </option>
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
                <a href="{{ route('library.shelves.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama rak</th>
                        <th>Lokasi</th>
                        <th>Klasifikasi</th>
                        <th>Kapasitas</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shelves as $shelf)
                        @php
                            $occupied = (int) $shelf->occupied_count;
                            $capacity = $shelf->capacity !== null ? (int) $shelf->capacity : null;
                            $isFull = $capacity !== null && $occupied >= $capacity;
                            $percentage = $capacity !== null && $capacity > 0
                                ? min(100, (int) round(($occupied / $capacity) * 100))
                                : null;
                        @endphp
                        <tr>
                            <td><strong>{{ $shelf->shelf_code }}</strong></td>
                            <td>
                                <div class="table-primary">{{ $shelf->shelf_name }}</div>
                                <div class="table-secondary">{{ $shelf->description ?: 'Tidak ada deskripsi.' }}</div>
                            </td>
                            <td>
                                @if ($shelf->location)
                                    <div class="table-primary">{{ $shelf->location->location_name }}</div>
                                    <div class="table-secondary">{{ $shelf->location->location_code }}</div>
                                @else
                                    <span class="table-secondary">Belum ditentukan</span>
                                @endif
                            </td>
                            <td>{{ $shelf->classification_range ?: '-' }}</td>
                            <td>
                                <div class="table-primary">
                                    {{ number_format($occupied) }} / {{ $capacity !== null ? number_format($capacity) : 'Tidak dibatasi' }}
                                </div>
                                <div class="table-secondary">
                                    {{ number_format((int) $shelf->available_count) }} buku tersedia
                                </div>
                                @if ($percentage !== null)
                                    <div class="capacity-track" aria-label="Kapasitas {{ $percentage }} persen">
                                        <span style="width: {{ $percentage }}%"></span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($isFull)
                                    <span class="badge badge-warning">Penuh</span>
                                @else
                                    <span class="badge {{ $shelf->status === 'active' ? 'badge-success' : 'badge-muted' }}">
                                        {{ $shelf->status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('library.shelves.edit', $shelf) }}" class="action-link">Edit</a>
                                    <form method="POST" action="{{ route('library.shelves.toggle-status', $shelf) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="action-button">
                                            {{ $shelf->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada rak yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($shelves->hasPages())
            <div class="pagination-bar">
                <span>
                    Menampilkan {{ $shelves->firstItem() }} sampai {{ $shelves->lastItem() }}
                    dari {{ $shelves->total() }} rak
                </span>
                <div class="pagination-actions">
                    @if ($shelves->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $shelves->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif

                    <span class="page-indicator">Halaman {{ $shelves->currentPage() }} dari {{ $shelves->lastPage() }}</span>

                    @if ($shelves->hasMorePages())
                        <a href="{{ $shelves->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
