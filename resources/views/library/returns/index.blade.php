@extends('layouts.app')

@section('title', 'Pengembalian Buku')
@section('page-title', 'Pengembalian Buku')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card">
            <span>Masih dipinjam</span>
            <strong>{{ number_format($summary['borrowed']) }}</strong>
        </article>
        <article class="stat-card stat-warning">
            <span>Sudah terlambat</span>
            <strong>{{ number_format($summary['overdue']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Jatuh tempo hari ini</span>
            <strong>{{ number_format($summary['due_today']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Dikembalikan hari ini</span>
            <strong>{{ number_format($summary['returned_today']) }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Sirkulasi perpustakaan</p>
                <h2>Daftar Buku yang Belum Kembali</h2>
            </div>
            <div class="panel-header-actions">
                <span class="return-fine-summary">Estimasi denda berjalan: <strong>Rp{{ number_format($summary['estimated_fine'], 0, ',', '.') }}</strong></span>
                <a href="{{ route('library.loans.index') }}" class="button-secondary">Daftar peminjaman</a>
            </div>
        </div>

        <form method="GET" action="{{ route('library.returns.index') }}" class="filter-bar filter-bar-returns">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Kode transaksi, anggota, barcode, kode aset, atau judul buku"
                >
            </div>
            <div class="filter-field">
                <label for="timing">Status waktu</label>
                <select id="timing" name="timing">
                    <option value="">Semua</option>
                    <option value="overdue" @selected(request('timing') === 'overdue')>Terlambat</option>
                    <option value="due_today" @selected(request('timing') === 'due_today')>Jatuh tempo hari ini</option>
                    <option value="on_time" @selected(request('timing') === 'on_time')>Belum jatuh tempo</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="button-primary">Terapkan</button>
                <a href="{{ route('library.returns.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Transaksi</th>
                        <th>Anggota</th>
                        <th>Eksemplar</th>
                        <th>Judul buku</th>
                        <th>Jatuh tempo</th>
                        <th>Keterlambatan</th>
                        <th>Denda berjalan</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($loanItems as $loanItem)
                        @php
                            $daysLate = $loanItem->due_date->isBefore(today())
                                ? (int) $loanItem->due_date->diffInDays(today())
                                : 0;
                            $fineAmount = $daysLate * $finePerDay;
                            $asset = $loanItem->asset;
                        @endphp
                        <tr>
                            <td>
                                <div class="table-primary">{{ $loanItem->loan?->loan_code }}</div>
                                <div class="table-secondary">{{ $loanItem->loan?->loan_date?->translatedFormat('d F Y H:i') }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $loanItem->loan?->member?->member_name }}</div>
                                <div class="table-secondary">{{ $loanItem->loan?->member?->member_code }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $asset?->asset_code }}</div>
                                <div class="table-secondary">{{ $asset?->barcode }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $asset?->item?->item_name }}</div>
                                <div class="table-secondary">
                                    {{ $asset?->shelf?->shelf_code ?? 'Rak tidak tersedia' }}
                                    @if ($asset?->item?->bookDetail?->call_number)
                                        · {{ $asset->item->bookDetail->call_number }}
                                    @endif
                                </div>
                            </td>
                            <td>{{ $loanItem->due_date?->translatedFormat('d F Y') }}</td>
                            <td>
                                @if ($daysLate > 0)
                                    <span class="badge badge-warning">{{ number_format($daysLate) }} hari</span>
                                @elseif ($loanItem->due_date->isToday())
                                    <span class="badge badge-neutral">Hari ini</span>
                                @else
                                    <span class="badge badge-success">Tepat waktu</span>
                                @endif
                            </td>
                            <td>
                                <div class="table-primary">Rp{{ number_format($fineAmount, 0, ',', '.') }}</div>
                                <div class="table-secondary">Rp{{ number_format($finePerDay, 0, ',', '.') }}/hari</div>
                            </td>
                            <td>
                                <a href="{{ route('library.returns.edit', $loanItem) }}" class="action-link">Proses</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Tidak ada buku yang menunggu pengembalian sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($loanItems->hasPages())
            <div class="pagination-bar">
                <span>Menampilkan {{ $loanItems->firstItem() }} sampai {{ $loanItems->lastItem() }} dari {{ $loanItems->total() }} eksemplar</span>
                <div class="pagination-actions">
                    @if ($loanItems->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $loanItems->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif
                    <span class="page-indicator">Halaman {{ $loanItems->currentPage() }} dari {{ $loanItems->lastPage() }}</span>
                    @if ($loanItems->hasMorePages())
                        <a href="{{ $loanItems->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
