@extends('layouts.app')

@section('title', 'Laporan Denda')
@section('page-title', 'Laporan Denda')

@section('content')
    @include('reports._tabs')

    <div class="report-stat-grid report-stat-grid-four">
        <article class="stat-card"><span>Tagihan</span><strong>{{ number_format($summary['bills']) }}</strong></article>
        <article class="stat-card"><span>Total denda</span><strong>Rp{{ number_format($summary['total_fine'], 0, ',', '.') }}</strong></article>
        <article class="stat-card"><span>Sudah dibayar</span><strong>Rp{{ number_format($summary['total_paid'], 0, ',', '.') }}</strong></article>
        <article class="stat-card stat-warning"><span>Sisa tagihan</span><strong>Rp{{ number_format($summary['outstanding'], 0, ',', '.') }}</strong></article>
    </div>

    <section class="panel report-panel">
        <div class="panel-header panel-header-wrap">
            <div><p class="eyebrow">Keuangan perpustakaan</p><h2>Denda final dan pembayaran</h2></div>
            <div class="report-actions no-print"><button type="button" class="button-secondary" onclick="window.print()">Cetak</button><a href="{{ route('reports.fines.csv', request()->query()) }}" class="button-primary button-link">Unduh CSV</a></div>
        </div>

        <form method="GET" action="{{ route('reports.fines') }}" class="filter-bar filter-bar-report transaction-report-filter no-print">
            <div class="filter-field filter-search"><label for="search">Pencarian</label><input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Transaksi, anggota, judul, barcode, atau kode aset"></div>
            <div class="filter-field"><label for="payment_status">Status pembayaran</label><select id="payment_status" name="payment_status"><option value="">Semua status</option><option value="unpaid" @selected(request('payment_status') === 'unpaid')>Belum dibayar</option><option value="partial" @selected(request('payment_status') === 'partial')>Sebagian</option><option value="paid" @selected(request('payment_status') === 'paid')>Lunas</option></select></div>
            <div class="filter-field"><label for="member_type">Jenis anggota</label><select id="member_type" name="member_type"><option value="">Semua jenis</option>@foreach (\App\Models\Member::typeOptions() as $typeValue => $typeLabel)<option value="{{ $typeValue }}" @selected(request('member_type') === $typeValue)>{{ $typeLabel }}</option>@endforeach</select></div>
            <div class="filter-field"><label for="date_from">Kembali mulai</label><input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}"></div>
            <div class="filter-field"><label for="date_to">Kembali sampai</label><input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}"></div>
            <div class="filter-actions"><button type="submit" class="button-primary">Terapkan</button><a href="{{ route('reports.fines') }}" class="button-secondary">Reset</a></div>
        </form>

        <div class="report-print-meta">Dicetak pada {{ now()->translatedFormat('d F Y H:i') }} oleh {{ auth()->user()->full_name }}</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th class="table-number-heading">No.</th><th>Transaksi</th><th>Anggota</th><th>Buku</th><th>Tanggal kembali</th><th>Denda final</th><th>Dibayar</th><th>Sisa</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($fines as $fine)
                        @php
                            $fineAmount = (float) $fine->fine_amount;
                            $paidAmount = (float) ($fine->paid_amount ?? 0);
                            $remaining = max($fineAmount - $paidAmount, 0);
                            $paymentLabel = $remaining <= 0 ? 'Lunas' : ($paidAmount > 0 ? 'Sebagian' : 'Belum dibayar');
                        @endphp
                        <tr><td class="table-number">{{ (is_object($fines) && method_exists($fines, 'firstItem') && $fines->firstItem() !== null ? $fines->firstItem() : 1) + $loop->index }}</td>
                            <td><div class="table-primary">{{ $fine->loan?->loan_code }}</div><div class="table-secondary">{{ $fine->asset?->asset_code }}</div></td>
                            <td><div class="table-primary">{{ $fine->loan?->member?->member_name }}</div><div class="table-secondary">{{ $fine->loan?->member?->member_code }}</div></td>
                            <td><div class="table-primary">{{ $fine->asset?->item?->item_name }}</div><div class="table-secondary">{{ $fine->asset?->barcode }}</div></td>
                            <td>{{ $fine->returned_at?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                            <td>Rp{{ number_format($fineAmount, 0, ',', '.') }}</td>
                            <td>Rp{{ number_format($paidAmount, 0, ',', '.') }}</td>
                            <td><strong>Rp{{ number_format($remaining, 0, ',', '.') }}</strong></td>
                            <td><span class="badge {{ $remaining <= 0 ? 'badge-success' : ($paidAmount > 0 ? 'badge-warning' : 'badge-danger') }}">{{ $paymentLabel }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Tidak ada data denda yang sesuai dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('reports._pagination', ['paginator' => $fines, 'label' => 'tagihan'])
    </section>
@endsection
