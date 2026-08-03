@extends('layouts.app')

@section('title', 'Peminjaman Buku')
@section('page-title', 'Peminjaman Buku')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card">
            <span>Transaksi hari ini</span>
            <strong>{{ number_format($summary['today']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Transaksi aktif</span>
            <strong>{{ number_format($summary['active']) }}</strong>
        </article>
        <article class="stat-card stat-warning">
            <span>Transaksi terlambat</span>
            <strong>{{ number_format($summary['overdue']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Eksemplar dipinjam</span>
            <strong>{{ number_format($summary['borrowed_copies']) }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Sirkulasi perpustakaan</p>
                <h2>Daftar Peminjaman</h2>
            </div>
            <a href="{{ route('library.loans.create') }}" class="button-primary button-link">Buat peminjaman</a>
        </div>

        <form method="GET" action="{{ route('library.loans.index') }}" class="filter-bar filter-bar-loans">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Kode transaksi, kode anggota, atau nama anggota"
                >
            </div>
            <div class="filter-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua status</option>
                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                    <option value="overdue" @selected(request('status') === 'overdue')>Terlambat</option>
                    <option value="completed" @selected(request('status') === 'completed')>Selesai</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Dibatalkan</option>
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
                <a href="{{ route('library.loans.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Kode transaksi</th>
                        <th>Anggota</th>
                        <th>Tanggal pinjam</th>
                        <th>Jatuh tempo</th>
                        <th>Eksemplar</th>
                        <th>Petugas</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($loans as $loan)
                        @php
                            $statusClass = match ($loan->status) {
                                'active' => 'badge-success',
                                'overdue' => 'badge-warning',
                                'completed' => 'badge-neutral',
                                default => 'badge-muted',
                            };
                        @endphp
                        <tr><td class="table-number">{{ (is_object($loans) && method_exists($loans, 'firstItem') && $loans->firstItem() !== null ? $loans->firstItem() : 1) + $loop->index }}</td>
                            <td><strong>{{ $loan->loan_code }}</strong></td>
                            <td>
                                <div class="table-primary">{{ $loan->member?->member_name }}</div>
                                <div class="table-secondary">{{ $loan->member?->member_code }}</div>
                            </td>
                            <td>{{ $loan->loan_date?->translatedFormat('d F Y H:i') }}</td>
                            <td>
                                <div class="table-primary">
                                    {{ $loan->nearest_due_date ? \Illuminate\Support\Carbon::parse($loan->nearest_due_date)->translatedFormat('d F Y') : '-' }}
                                </div>
                                @if ((int) $loan->overdue_items_count > 0)
                                    <div class="table-secondary text-danger">{{ number_format((int) $loan->overdue_items_count) }} eksemplar terlambat</div>
                                @else
                                    <div class="table-secondary">Batas standar: {{ $loan->default_due_date?->format('d-m-Y') }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="table-primary">{{ number_format((int) $loan->borrowed_items_count) }} masih dipinjam</div>
                                <div class="table-secondary">{{ number_format((int) $loan->items_count) }} total</div>
                            </td>
                            <td>{{ $loan->processor?->full_name ?? '-' }}</td>
                            <td><span class="badge {{ $statusClass }}">{{ $loan->statusLabel() }}</span></td>
                            <td>
                                <a href="{{ route('library.loans.show', $loan) }}" class="action-link">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="empty-state">Belum ada transaksi peminjaman yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($loans->hasPages())
            <div class="pagination-bar">
                <span>Menampilkan {{ $loans->firstItem() }} sampai {{ $loans->lastItem() }} dari {{ $loans->total() }} transaksi</span>
                <div class="pagination-actions">
                    @if ($loans->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $loans->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif
                    <span class="page-indicator">Halaman {{ $loans->currentPage() }} dari {{ $loans->lastPage() }}</span>
                    @if ($loans->hasMorePages())
                        <a href="{{ $loans->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
