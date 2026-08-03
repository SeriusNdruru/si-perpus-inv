@extends('layouts.app')

@section('title', 'Denda Perpustakaan')
@section('page-title', 'Denda Perpustakaan')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card stat-warning">
            <span>Total denda final</span>
            <strong>Rp{{ number_format($summary['total_fines'], 0, ',', '.') }}</strong>
        </article>
        <article class="stat-card">
            <span>Total pembayaran</span>
            <strong>Rp{{ number_format($summary['total_paid'], 0, ',', '.') }}</strong>
        </article>
        <article class="stat-card stat-warning">
            <span>Sisa tagihan</span>
            <strong>Rp{{ number_format($summary['outstanding'], 0, ',', '.') }}</strong>
            <small>{{ number_format($summary['outstanding_items']) }} eksemplar belum lunas</small>
        </article>
        <article class="stat-card">
            <span>Dibayar hari ini</span>
            <strong>Rp{{ number_format($summary['paid_today'], 0, ',', '.') }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Keuangan sirkulasi</p>
                <h2>Tagihan Denda Anggota</h2>
            </div>
            <div class="panel-header-actions">
                <a href="{{ route('library.returns.index') }}" class="button-secondary">Pengembalian</a>
                <a href="{{ route('library.loans.index') }}" class="button-secondary">Peminjaman</a>
            </div>
        </div>

        <form method="GET" action="{{ route('library.fines.index') }}" class="filter-bar filter-bar-fines">
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
                <label for="status">Status pembayaran</label>
                <select id="status" name="status">
                    <option value="">Semua</option>
                    <option value="unpaid" @selected(request('status') === 'unpaid')>Belum dibayar</option>
                    <option value="partial" @selected(request('status') === 'partial')>Dibayar sebagian</option>
                    <option value="paid" @selected(request('status') === 'paid')>Lunas</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="button-primary">Terapkan</button>
                <a href="{{ route('library.fines.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Transaksi</th>
                        <th>Anggota</th>
                        <th>Buku</th>
                        <th>Tanggal kembali</th>
                        <th>Denda final</th>
                        <th>Sudah dibayar</th>
                        <th>Sisa tagihan</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($loanItems as $loanItem)
                        @php
                            $paidAmount = (float) ($loanItem->paid_amount ?? 0);
                            $fineAmount = (float) $loanItem->fine_amount;
                            $remainingAmount = max($fineAmount - $paidAmount, 0);
                            $paymentStatus = $remainingAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');
                            $asset = $loanItem->asset;
                        @endphp
                        <tr><td class="table-number">{{ (is_object($loanItems) && method_exists($loanItems, 'firstItem') && $loanItems->firstItem() !== null ? $loanItems->firstItem() : 1) + $loop->index }}</td>
                            <td>
                                <div class="table-primary">{{ $loanItem->loan?->loan_code }}</div>
                                <div class="table-secondary">{{ $loanItem->loan?->loan_date?->translatedFormat('d F Y') }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $loanItem->loan?->member?->member_name }}</div>
                                <div class="table-secondary">{{ $loanItem->loan?->member?->member_code }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $asset?->item?->item_name }}</div>
                                <div class="table-secondary">{{ $asset?->asset_code }} · {{ $asset?->barcode }}</div>
                            </td>
                            <td>{{ $loanItem->returned_at?->translatedFormat('d F Y H:i') }}</td>
                            <td><strong>Rp{{ number_format($fineAmount, 0, ',', '.') }}</strong></td>
                            <td>Rp{{ number_format($paidAmount, 0, ',', '.') }}</td>
                            <td class="{{ $remainingAmount > 0 ? 'text-danger' : '' }}">
                                <strong>Rp{{ number_format($remainingAmount, 0, ',', '.') }}</strong>
                            </td>
                            <td>
                                @if ($paymentStatus === 'paid')
                                    <span class="badge badge-success">Lunas</span>
                                @elseif ($paymentStatus === 'partial')
                                    <span class="badge badge-warning">Sebagian</span>
                                @else
                                    <span class="badge badge-danger">Belum dibayar</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('library.fines.show', $loanItem) }}" class="action-link">
                                    {{ $remainingAmount > 0 ? 'Kelola' : 'Detail' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="empty-state">Tidak ada tagihan denda sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($loanItems->hasPages())
            <div class="pagination-bar">
                <span>Menampilkan {{ $loanItems->firstItem() }} sampai {{ $loanItems->lastItem() }} dari {{ $loanItems->total() }} tagihan</span>
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
