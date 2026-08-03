@extends('layouts.app')

@section('title', 'Satuan')
@section('page-title', 'Master Satuan')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card">
            <span>Total satuan</span>
            <strong>{{ number_format($summary['total']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Satuan aktif</span>
            <strong>{{ number_format($summary['active']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Di Daftar Hapus</span>
            <strong>{{ number_format($summary['inactive']) }}</strong>
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
                <h2>Daftar Satuan</h2>
            </div>
            <div class="panel-header-actions">

                <a href="{{ route('units.deleted.index') }}" class="button-secondary">Daftar Hapus</a>

                <a href="{{ route('units.create') }}" class="button-primary button-link">Tambah satuan</a>

            </div>
        </div>

        <form method="GET" action="{{ route('units.index') }}" class="filter-bar filter-bar-compact">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Kode atau nama satuan"
                >
            </div>

            <div class="filter-actions">
                <button type="submit" class="button-primary">Terapkan</button>
                <a href="{{ route('units.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Kode</th>
                        <th>Nama satuan</th>
                        <th>Deskripsi</th>
                        <th>Jumlah barang</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($units as $unit)
                        <tr><td class="table-number">{{ (is_object($units) && method_exists($units, 'firstItem') && $units->firstItem() !== null ? $units->firstItem() : 1) + $loop->index }}</td>
                            <td><strong>{{ $unit->unit_code }}</strong></td>
                            <td><div class="table-primary">{{ $unit->unit_name }}</div></td>
                            <td>
                                <div class="table-secondary">
                                    {{ $unit->description ?: 'Tidak ada deskripsi.' }}
                                </div>
                            </td>
                            <td>{{ number_format((int) $unit->items_count) }}</td>
                            <td>
                                <span class="badge {{ $unit->status === 'active' ? 'badge-success' : 'badge-muted' }}">
                                    {{ $unit->status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('units.edit', $unit) }}" class="action-link">Edit</a>
                                    <form method="POST" action="{{ route('units.toggle-status', $unit) }}" onsubmit="return confirm('Hapus satuan ini dari daftar aktif dan pindahkan ke Daftar Hapus?');">
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
                            <td colspan="7" class="empty-state">Belum ada satuan yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($units->hasPages())
            <div class="pagination-bar">
                <span>
                    Menampilkan {{ $units->firstItem() }} sampai {{ $units->lastItem() }}
                    dari {{ $units->total() }} satuan
                </span>
                <div class="pagination-actions">
                    @if ($units->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $units->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif

                    <span class="page-indicator">Halaman {{ $units->currentPage() }} dari {{ $units->lastPage() }}</span>

                    @if ($units->hasMorePages())
                        <a href="{{ $units->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
