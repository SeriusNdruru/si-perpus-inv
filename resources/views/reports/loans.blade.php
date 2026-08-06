@extends('layouts.app')

@section('title', 'Riwayat Peminjaman')
@section('page-title', 'Riwayat Peminjaman')

@section('content')
    @include('reports._tabs')

    <div class="report-stat-grid report-stat-grid-five">
        <article class="stat-card"><span>Transaksi</span><strong>{{ number_format($summary['transactions']) }}</strong></article>
        <article class="stat-card"><span>Eksemplar dipinjam</span><strong>{{ number_format($summary['copies']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Belum kembali</span><strong>{{ number_format($summary['outstanding']) }}</strong></article>
        <article class="stat-card"><span>Sudah diproses</span><strong>{{ number_format($summary['returned']) }}</strong></article>
        <article class="stat-card"><span>Total denda</span><strong>Rp{{ number_format($summary['fines'], 0, ',', '.') }}</strong></article>
    </div>

    <section class="panel report-panel">
        <div class="panel-header panel-header-wrap">
            <div><p class="eyebrow">Sirkulasi berdasarkan periode</p><h2>Riwayat transaksi peminjaman</h2></div>
            <div class="report-actions no-print"><button type="button" class="button-secondary" onclick="window.print()">Cetak</button><a href="{{ route('reports.loans.excel', request()->query()) }}" class="button-primary button-link">Unduh Excel</a></div>
        </div>

        <form method="GET" action="{{ route('reports.loans') }}" class="filter-bar filter-bar-report transaction-report-filter transaction-report-filter-with-mode no-print">
            <div class="filter-field"><label for="report_mode">Tampilan laporan</label><select id="report_mode" name="report_mode"><option value="history" selected>Riwayat Peminjaman</option><option value="ranking">Peringkat Peminjaman</option></select></div>
            <div class="filter-field filter-search"><label for="search">Pencarian</label><input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Kode transaksi, kode anggota, atau nama anggota"></div>
            <div class="filter-field"><label for="status">Status</label><select id="status" name="status"><option value="">Semua status</option><option value="active" @selected(request('status') === 'active')>Aktif</option><option value="overdue" @selected(request('status') === 'overdue')>Terlambat</option><option value="completed" @selected(request('status') === 'completed')>Selesai</option><option value="cancelled" @selected(request('status') === 'cancelled')>Dibatalkan</option></select></div>
            <div class="filter-field"><label for="member_type">Jenis anggota</label><select id="member_type" name="member_type"><option value="">Semua jenis</option>@foreach (\App\Models\Member::typeOptions() as $typeValue => $typeLabel)<option value="{{ $typeValue }}" @selected(request('member_type') === $typeValue)>{{ $typeLabel }}</option>@endforeach</select></div>
            <div class="filter-field"><label for="date_from">Dari tanggal</label><input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}"></div>
            <div class="filter-field"><label for="date_to">Sampai tanggal</label><input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}"></div>
            <div class="filter-actions"><button type="submit" class="button-primary">Terapkan</button><a href="{{ route('reports.loans') }}" class="button-secondary">Reset</a></div>
        </form>

        <div class="report-print-meta">Dicetak pada {{ now()->translatedFormat('d F Y H:i') }} oleh {{ auth()->user()->full_name }}</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th class="table-number-heading">No.</th><th>Transaksi</th><th>Anggota</th><th>Tanggal</th><th>Status</th><th>Eksemplar</th><th>Belum kembali</th><th>Sudah diproses</th><th>Total denda</th><th>Petugas</th></tr></thead>
                <tbody>
                    @forelse ($loans as $loan)
                        <tr><td class="table-number">{{ (is_object($loans) && method_exists($loans, 'firstItem') && $loans->firstItem() !== null ? $loans->firstItem() : 1) + $loop->index }}</td>
                            <td><strong>{{ $loan->loan_code }}</strong></td>
                            <td><div class="table-primary">{{ $loan->member?->member_name }}</div><div class="table-secondary">{{ $loan->member?->member_code }} · {{ $loan->member?->typeLabel() }}</div></td>
                            <td><div class="table-primary">{{ $loan->loan_date?->translatedFormat('d M Y H:i') }}</div><div class="table-secondary">Jatuh tempo {{ $loan->default_due_date?->translatedFormat('d M Y') }}</div></td>
                            <td><span class="badge {{ $loan->status === 'overdue' ? 'badge-danger' : ($loan->status === 'active' ? 'badge-warning' : 'badge-success') }}">{{ $loan->statusLabel() }}</span></td>
                            <td>{{ number_format((int) $loan->copy_count) }}</td><td>{{ number_format((int) $loan->outstanding_count) }}</td><td>{{ number_format((int) $loan->returned_count) }}</td>
                            <td>Rp{{ number_format((float) ($loan->fine_total ?? 0), 0, ',', '.') }}</td><td>{{ $loan->processor?->full_name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="empty-state">Tidak ada transaksi peminjaman yang sesuai dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('reports._pagination', ['paginator' => $loans, 'label' => 'transaksi'])
    </section>
@endsection
