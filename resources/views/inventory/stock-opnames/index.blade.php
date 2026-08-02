@extends('layouts.app')

@section('title', 'Stock Opname')
@section('page-title', 'Stock Opname Inventaris')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card">
            <span>Total stock opname</span>
            <strong>{{ number_format($summary['total']) }}</strong>
        </article>
        <article class="stat-card {{ $summary['pending'] > 0 ? 'stat-warning' : '' }}">
            <span>Belum selesai</span>
            <strong>{{ number_format($summary['pending']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Sudah selesai</span>
            <strong>{{ number_format($summary['completed']) }}</strong>
        </article>
        <article class="stat-card {{ $summary['issues'] > 0 ? 'stat-warning' : '' }}">
            <span>Temuan tersimpan</span>
            <strong>{{ number_format($summary['issues']) }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Pemeriksaan fisik inventaris</p>
                <h2>Daftar Stock Opname</h2>
                <p class="panel-description">Bandingkan data sistem dengan kondisi fisik pada satu lokasi, lalu terapkan selisih setelah pemeriksaan disetujui.</p>
            </div>
            <a href="{{ route('inventory.stock-opnames.create') }}" class="button-primary button-link">Buat stock opname</a>
        </div>

        <form method="GET" action="{{ route('inventory.stock-opnames.index') }}" class="filter-bar stock-opname-filter">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Kode opname atau lokasi">
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
                <label for="location_id">Lokasi</label>
                <select id="location_id" name="location_id">
                    <option value="">Semua lokasi</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected((int) request('location_id') === $location->id)>
                            {{ $location->location_code }} · {{ $location->location_name }}
                        </option>
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
                <a href="{{ route('inventory.stock-opnames.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Tanggal dan lokasi</th>
                        <th>Progres</th>
                        <th>Temuan</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockOpnames as $stockOpname)
                        @php
                            $statusClass = match ($stockOpname->status) {
                                'completed' => 'badge-success',
                                'draft', 'in_progress' => 'badge-warning',
                                default => 'badge-muted',
                            };
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $stockOpname->opname_code }}</strong>
                                <div class="table-secondary">Dibuat {{ $stockOpname->creator?->full_name ?? 'Sistem' }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $stockOpname->opname_date?->format('d/m/Y') }}</div>
                                <div class="table-secondary">
                                    {{ $stockOpname->location?->location_code }} · {{ $stockOpname->location?->location_name ?? 'Lokasi tidak tersedia' }}
                                </div>
                            </td>
                            <td>
                                <div class="table-primary">{{ number_format($stockOpname->checked_lines_count) }} / {{ number_format($stockOpname->total_lines_count) }} baris</div>
                                <div class="progress-track" aria-label="Progres pemeriksaan">
                                    @php
                                        $progress = $stockOpname->total_lines_count > 0
                                            ? min(100, round(($stockOpname->checked_lines_count / $stockOpname->total_lines_count) * 100))
                                            : 0;
                                    @endphp
                                    <span style="width: {{ $progress }}%"></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $stockOpname->issue_lines_count > 0 ? 'badge-warning' : 'badge-neutral' }}">
                                    {{ number_format($stockOpname->issue_lines_count) }} temuan
                                </span>
                            </td>
                            <td><span class="badge {{ $statusClass }}">{{ $stockOpname->statusLabel() }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('inventory.stock-opnames.show', $stockOpname) }}" class="action-link">Detail</a>
                                    @if (in_array($stockOpname->status, ['draft', 'in_progress'], true))
                                        <a href="{{ route('inventory.stock-opnames.edit', $stockOpname) }}" class="action-link">Periksa</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada stock opname yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($stockOpnames->hasPages())
            <div class="pagination-bar">
                <span>Menampilkan {{ $stockOpnames->firstItem() }} sampai {{ $stockOpnames->lastItem() }} dari {{ $stockOpnames->total() }} data</span>
                <div class="pagination-actions">
                    @if ($stockOpnames->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $stockOpnames->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif
                    <span class="page-indicator">Halaman {{ $stockOpnames->currentPage() }} dari {{ $stockOpnames->lastPage() }}</span>
                    @if ($stockOpnames->hasMorePages())
                        <a href="{{ $stockOpnames->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
