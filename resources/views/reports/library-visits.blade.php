@extends('layouts.app')

@section('title', 'Laporan Kunjungan Siswa')
@section('page-title', 'Laporan Kunjungan Siswa')

@section('content')
@include('reports._tabs')

<div class="report-stat-grid report-stat-grid-two">
    <article class="stat-card"><span>Total kunjungan</span><strong>{{ number_format($summary['visits']) }}</strong></article>
    <article class="stat-card"><span>Siswa berbeda</span><strong>{{ number_format($summary['students']) }}</strong></article>
</div>

<section class="panel report-panel">
    <div class="panel-header panel-header-wrap">
        <div><p class="eyebrow">Aktivitas membaca</p><h2>Record kunjungan siswa</h2></div>
        <div class="report-actions no-print">
            <a href="{{ route('reports.library-visits.excel', request()->query()) }}" class="button-primary button-link">Unduh Excel</a>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.library-visits') }}" class="filter-bar filter-bar-report no-print">
        <div class="filter-field filter-search"><label for="search">Pencarian</label><input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama, kode anggota, atau NIS/NISN"></div>
        <div class="filter-field"><label for="class">Kelas</label><select id="class" name="class"><option value="">Semua kelas</option>@foreach ($classes as $class)<option value="{{ $class }}" @selected(($filters['class'] ?? '') === $class)>{{ $class }}</option>@endforeach</select></div>
        <div class="filter-field"><label for="date_from">Dari tanggal</label><input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
        <div class="filter-field"><label for="date_to">Sampai tanggal</label><input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
        <div class="filter-actions"><button type="submit" class="button-primary">Terapkan</button><a href="{{ route('reports.library-visits') }}" class="button-secondary">Reset</a></div>
    </form>

    <div class="report-print-meta">Dicetak pada {{ now()->translatedFormat('d F Y H:i') }} oleh {{ auth()->user()->full_name }}</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th class="table-number-heading">No.</th><th>Tanggal dan waktu</th><th>Siswa</th><th>NIS/NISN</th><th>Kelas</th><th>Kegiatan</th><th>Petugas</th><th>Catatan</th></tr></thead>
            <tbody>
                @forelse ($visits as $visit)
                    <tr>
                        <td class="table-number">{{ ($visits->firstItem() ?? 1) + $loop->index }}</td>
                        <td><div class="table-primary">{{ \Illuminate\Support\Carbon::parse($visit->visit_date)->translatedFormat('d F Y') }}</div><div class="table-secondary">{{ substr((string) $visit->visit_time, 0, 5) }} WIB</div></td>
                        <td><div class="table-primary">{{ $visit->member_name }}</div><div class="table-secondary">{{ $visit->member_code }}</div></td>
                        <td>{{ $visit->identity_number ?: '-' }}</td>
                        <td>{{ $visit->department ?: '-' }}</td>
                        <td>{{ $visit->activity }}</td>
                        <td>{{ $visit->recorder_name ?: '-' }}</td>
                        <td>{{ $visit->notes ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">Tidak ada data kunjungan yang sesuai dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('reports._pagination', ['paginator' => $visits, 'label' => 'kunjungan'])
</section>
@endsection
