@extends('layouts.app')

@section('title', 'Pemeliharaan Aset')
@section('page-title', 'Pemeliharaan dan Perbaikan Aset')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card">
            <span>Total pemeliharaan</span>
            <strong>{{ number_format($summary['total']) }}</strong>
        </article>
        <article class="stat-card {{ $summary['open'] > 0 ? 'stat-warning' : '' }}">
            <span>Belum selesai</span>
            <strong>{{ number_format($summary['open']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Sudah selesai</span>
            <strong>{{ number_format($summary['completed']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Total biaya selesai</span>
            <strong>Rp{{ number_format($summary['cost'], 0, ',', '.') }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Riwayat perawatan inventaris</p>
                <h2>Daftar Pemeliharaan Aset</h2>
                <p class="panel-description">Catat keluhan, proses perbaikan, biaya, vendor, dan kondisi akhir setiap aset individual.</p>
            </div>
            <a href="{{ route('inventory.maintenance-records.create') }}" class="button-primary button-link">Buat laporan</a>
        </div>

        <form method="GET" action="{{ route('inventory.maintenance-records.index') }}" class="filter-bar">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Kode, aset, barang, vendor, atau masalah">
            </div>

            <div class="filter-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label for="date_from">Dari tanggal</label>
                <input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}">
            </div>

            <div class="filter-field">
                <label for="date_to">Sampai tanggal</label>
                <input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}">
            </div>

            <div class="filter-actions">
                <button type="submit" class="button-primary">Terapkan</button>
                <a href="{{ route('inventory.maintenance-records.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode dan tanggal</th>
                        <th>Aset</th>
                        <th>Masalah</th>
                        <th>Petugas atau vendor</th>
                        <th>Biaya</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>
                                <strong>{{ $record->maintenance_code }}</strong>
                                <div class="table-secondary">{{ $record->reported_at?->format('d/m/Y H:i') }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $record->asset?->item?->item_name ?? 'Barang tidak tersedia' }}</div>
                                <div class="table-secondary">{{ $record->asset?->asset_code }} · {{ $record->asset?->location?->location_name ?? 'Tanpa lokasi' }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ str($record->issue_description)->limit(90) }}</div>
                                <div class="table-secondary">Kondisi: {{ ucfirst((string) $record->asset?->condition_status) }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $record->handler?->full_name ?? 'Belum ditangani' }}</div>
                                <div class="table-secondary">{{ $record->vendor_name ?: 'Tanpa vendor' }}</div>
                            </td>
                            <td>Rp{{ number_format((float) $record->cost, 0, ',', '.') }}</td>
                            <td><span class="badge {{ $record->statusBadgeClass() }}">{{ $record->statusLabel() }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('inventory.maintenance-records.show', $record) }}" class="action-link">Detail</a>
                                    @if (in_array($record->status, ['reported', 'in_progress'], true))
                                        <a href="{{ route('inventory.maintenance-records.edit', $record) }}" class="action-link">Edit</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada data pemeliharaan yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($records->hasPages())
            <div class="pagination-bar">
                <span>Menampilkan {{ $records->firstItem() }} sampai {{ $records->lastItem() }} dari {{ $records->total() }} data</span>
                <div class="pagination-actions">
                    @if ($records->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $records->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif
                    <span class="page-indicator">Halaman {{ $records->currentPage() }} dari {{ $records->lastPage() }}</span>
                    @if ($records->hasMorePages())
                        <a href="{{ $records->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
