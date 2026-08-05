@extends('layouts.app')

@section('title', 'Siswa Sering Berkunjung')
@section('page-title', 'Siswa Sering Berkunjung')

@section('content')
@include('reports._tabs')

<section class="panel report-panel">
    <div class="panel-header panel-header-wrap">
        <div><p class="eyebrow">Peringkat kunjungan</p><h2>Siswa yang sering ke perpustakaan</h2></div>
        <div class="report-actions no-print">
            <a href="{{ route('reports.frequent-visitors.pdf', request()->query()) }}" class="button-primary button-link">Unduh PDF</a>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.frequent-visitors') }}" class="filter-bar filter-bar-report no-print">
        <div class="filter-field filter-search"><label for="search">Pencarian</label><input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama, kode anggota, atau NIS/NISN"></div>
        <div class="filter-field"><label for="class">Kelas</label><select id="class" name="class"><option value="">Semua kelas</option>@foreach ($classes as $class)<option value="{{ $class }}" @selected(($filters['class'] ?? '') === $class)>{{ $class }}</option>@endforeach</select></div>
        <div class="filter-field"><label for="date_from">Dari tanggal</label><input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
        <div class="filter-field"><label for="date_to">Sampai tanggal</label><input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
        <div class="filter-actions"><button type="submit" class="button-primary">Terapkan</button><a href="{{ route('reports.frequent-visitors') }}" class="button-secondary">Reset</a></div>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Peringkat</th><th>Siswa</th><th>NIS/NISN</th><th>Kelas</th><th>Jumlah kunjungan</th><th>Kunjungan terakhir</th></tr></thead>
            <tbody>
                @forelse ($ranking as $student)
                    <tr>
                        <td><strong>{{ ($ranking->firstItem() ?? 1) + $loop->index }}</strong></td>
                        <td><div class="table-primary">{{ $student->member_name }}</div><div class="table-secondary">{{ $student->member_code }}</div></td>
                        <td>{{ $student->identity_number ?: '-' }}</td>
                        <td>{{ $student->department ?: '-' }}</td>
                        <td><strong>{{ number_format((int) $student->visit_count) }} kali</strong></td>
                        <td>{{ $student->last_visit ? \Illuminate\Support\Carbon::parse($student->last_visit)->translatedFormat('d F Y') : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">Belum ada data kunjungan yang sesuai dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('reports._pagination', ['paginator' => $ranking, 'label' => 'siswa'])
</section>
@endsection
