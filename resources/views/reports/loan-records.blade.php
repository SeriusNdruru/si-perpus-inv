@extends('layouts.app')

@section('title', 'Record Peminjaman Buku')
@section('page-title', 'Record Peminjaman Buku')

@section('content')
@include('reports._tabs')

<div class="report-stat-grid report-stat-grid-four">
    <article class="stat-card"><span>Record buku</span><strong>{{ number_format($summary['records']) }}</strong></article>
    <article class="stat-card"><span>Siswa berbeda</span><strong>{{ number_format($summary['students']) }}</strong></article>
    <article class="stat-card stat-warning"><span>Masih dipinjam</span><strong>{{ number_format($summary['active']) }}</strong></article>
    <article class="stat-card"><span>Sudah diproses</span><strong>{{ number_format($summary['returned']) }}</strong></article>
</div>

<section class="panel report-panel">
    <div class="panel-header panel-header-wrap">
        <div><p class="eyebrow">Riwayat per buku</p><h2>Buku yang dipinjam setiap siswa</h2></div>
        <div class="report-actions no-print">
            <a href="{{ route('reports.loan-records.pdf', request()->query()) }}" class="button-primary button-link">Unduh PDF</a>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.loan-records') }}" class="filter-bar filter-bar-report no-print">
        <div class="filter-field filter-search"><label for="search">Pencarian</label><input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Siswa, transaksi, judul, atau kode buku"></div>
        <div class="filter-field"><label for="class">Kelas</label><select id="class" name="class"><option value="">Semua kelas</option>@foreach ($classes as $class)<option value="{{ $class }}" @selected(($filters['class'] ?? '') === $class)>{{ $class }}</option>@endforeach</select></div>
        <div class="filter-field"><label for="status">Status</label><select id="status" name="status"><option value="">Semua status</option><option value="borrowed" @selected(($filters['status'] ?? '') === 'borrowed')>Masih dipinjam</option><option value="returned" @selected(($filters['status'] ?? '') === 'returned')>Dikembalikan</option><option value="damaged" @selected(($filters['status'] ?? '') === 'damaged')>Rusak</option><option value="lost" @selected(($filters['status'] ?? '') === 'lost')>Hilang</option></select></div>
        <div class="filter-field"><label for="date_from">Dari tanggal</label><input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
        <div class="filter-field"><label for="date_to">Sampai tanggal</label><input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
        <div class="filter-actions"><button type="submit" class="button-primary">Terapkan</button><a href="{{ route('reports.loan-records') }}" class="button-secondary">Reset</a></div>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th class="table-number-heading">No.</th><th>Transaksi</th><th>Siswa</th><th>Kelas</th><th>Buku</th><th>Tanggal pinjam</th><th>Jatuh tempo</th><th>Tanggal kembali</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $statusLabel = match ($record->return_status) {
                            'borrowed' => 'Masih dipinjam',
                            'returned' => 'Dikembalikan',
                            'damaged' => 'Rusak',
                            'lost' => 'Hilang',
                            default => ucfirst($record->return_status),
                        };
                    @endphp
                    <tr>
                        <td class="table-number">{{ ($records->firstItem() ?? 1) + $loop->index }}</td>
                        <td><strong>{{ $record->loan_code }}</strong></td>
                        <td><div class="table-primary">{{ $record->member_name }}</div><div class="table-secondary">{{ $record->member_code }} · {{ $record->identity_number ?: 'NIS/NISN belum diisi' }}</div></td>
                        <td>{{ $record->department ?: '-' }}</td>
                        <td><div class="table-primary">{{ $record->item_name }}</div><div class="table-secondary">{{ $record->asset_code }}</div></td>
                        <td>{{ \Illuminate\Support\Carbon::parse($record->borrowed_at)->format('d/m/Y H:i') }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($record->due_date)->format('d/m/Y') }}</td>
                        <td>{{ $record->returned_at ? \Illuminate\Support\Carbon::parse($record->returned_at)->format('d/m/Y H:i') : '-' }}</td>
                        <td><span class="badge {{ $record->return_status === 'borrowed' ? 'badge-warning' : 'badge-success' }}">{{ $statusLabel }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">Tidak ada record peminjaman yang sesuai dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('reports._pagination', ['paginator' => $records, 'label' => 'record buku'])
</section>
@endsection
