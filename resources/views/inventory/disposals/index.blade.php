@extends('layouts.app')

@section('title', 'Penghapusan Aset')
@section('page-title', 'Penghapusan Aset Inventaris')

@section('content')
    <div class="detail-heading">
        <div>
            <p class="eyebrow">Tahap akhir siklus aset</p>
            <h2>Usulan dan pelaksanaan penghapusan</h2>
            <p class="panel-description">Admin Inventaris mengusulkan penghapusan. Super Admin memberikan persetujuan. Aset baru berubah menjadi disposed setelah pelaksanaan diselesaikan.</p>
        </div>
        <a href="{{ route('inventory.disposals.create') }}" class="button-primary button-link">Buat usulan</a>
    </div>

    <div class="stat-grid">
        <article class="stat-card"><span>Total usulan</span><strong>{{ number_format($summary['total']) }}</strong></article>
        <article class="stat-card {{ $summary['proposed'] > 0 ? 'stat-warning' : '' }}"><span>Menunggu persetujuan</span><strong>{{ number_format($summary['proposed']) }}</strong></article>
        <article class="stat-card {{ $summary['approved'] > 0 ? 'stat-warning' : '' }}"><span>Siap dilaksanakan</span><strong>{{ number_format($summary['approved']) }}</strong></article>
        <article class="stat-card"><span>Selesai dihapuskan</span><strong>{{ number_format($summary['completed']) }}</strong></article>
    </div>

    <section class="panel">
        <form method="GET" action="{{ route('inventory.disposals.index') }}" class="filter-bar filter-bar-items">
            <div class="form-field filter-search">
                <label for="search">Pencarian</label>
                <input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Kode penghapusan, aset, barang, atau alasan">
            </div>
            <div class="form-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="method">Metode</label>
                <select id="method" name="method">
                    <option value="">Semua metode</option>
                    @foreach ($methods as $value => $label)
                        <option value="{{ $value }}" @selected(request('method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="date_from">Dari tanggal</label>
                <input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}">
            </div>
            <div class="form-field">
                <label for="date_to">Sampai tanggal</label>
                <input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}">
            </div>
            <div class="filter-actions">
                <button type="submit" class="button-primary">Terapkan</button>
                <a href="{{ route('inventory.disposals.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode dan tanggal</th>
                        <th>Aset</th>
                        <th>Alasan</th>
                        <th>Pengusul dan penyetuju</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($disposals as $disposal)
                        <tr>
                            <td>
                                <strong>{{ $disposal->disposal_code }}</strong>
                                <div class="table-secondary">{{ $disposal->proposed_at?->format('d/m/Y H:i') }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $disposal->asset?->item?->item_name ?? 'Barang tidak tersedia' }}</div>
                                <div class="table-secondary">{{ $disposal->asset?->asset_code }} · {{ $disposal->asset?->location?->location_name ?? 'Tanpa lokasi' }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ str($disposal->reason)->limit(95) }}</div>
                                <div class="table-secondary">Kondisi: {{ ucfirst((string) $disposal->asset?->condition_status) }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $disposal->proposer?->full_name ?? 'Sistem' }}</div>
                                <div class="table-secondary">{{ $disposal->approver?->full_name ?? 'Belum disetujui' }}</div>
                            </td>
                            <td>{{ $disposal->methodLabel() }}</td>
                            <td><span class="badge {{ $disposal->statusBadgeClass() }}">{{ $disposal->statusLabel() }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('inventory.disposals.show', $disposal) }}" class="action-link">Detail</a>
                                    @if (in_array($disposal->status, ['proposed', 'rejected'], true))
                                        <a href="{{ route('inventory.disposals.edit', $disposal) }}" class="action-link">Edit</a>
                                    @endif
                                    @if ($disposal->status === 'approved')
                                        <a href="{{ route('inventory.disposals.complete-form', $disposal) }}" class="action-link">Laksanakan</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">Belum ada data penghapusan yang sesuai dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($disposals->hasPages())
            <div class="pagination-bar">
                <span>Menampilkan {{ $disposals->firstItem() }} sampai {{ $disposals->lastItem() }} dari {{ $disposals->total() }} data</span>
                <div class="pagination-actions">
                    @if ($disposals->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $disposals->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif
                    <span class="page-indicator">Halaman {{ $disposals->currentPage() }} dari {{ $disposals->lastPage() }}</span>
                    @if ($disposals->hasMorePages())
                        <a href="{{ $disposals->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
