@extends('layouts.app')

@section('title', 'Kunjungan Siswa')
@section('page-title', 'Kunjungan Siswa')

@section('content')
<div class="report-stat-grid report-stat-grid-four">
    <article class="stat-card"><span>Hari ini</span><strong>{{ number_format($summary['today']) }}</strong></article>
    <article class="stat-card"><span>Bulan ini</span><strong>{{ number_format($summary['month']) }}</strong></article>
    <article class="stat-card"><span>Sesuai filter</span><strong>{{ number_format($summary['filtered']) }}</strong></article>
    <article class="stat-card"><span>Siswa berbeda</span><strong>{{ number_format($summary['students']) }}</strong></article>
</div>

<section class="panel">
    <div class="panel-header panel-header-wrap">
        <div><p class="eyebrow">Pencatatan membaca</p><h2>Riwayat kunjungan perpustakaan</h2></div>
        <div class="panel-header-actions">
            <a href="{{ route('reports.library-visits') }}" class="button-secondary button-link">Buka laporan</a>
            <a href="{{ route('library.visits.create') }}" class="button-primary button-link">Catat kunjungan</a>
        </div>
    </div>

    <form method="GET" action="{{ route('library.visits.index') }}" class="filter-bar filter-bar-report">
        <div class="filter-field filter-search"><label for="search">Pencarian</label><input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama, kode anggota, atau NIS/NISN"></div>
        <div class="filter-field"><label for="class">Kelas</label><select id="class" name="class"><option value="">Semua kelas</option>@foreach ($classes as $class)<option value="{{ $class }}" @selected(($filters['class'] ?? '') === $class)>{{ $class }}</option>@endforeach</select></div>
        <div class="filter-field"><label for="date_from">Dari tanggal</label><input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
        <div class="filter-field"><label for="date_to">Sampai tanggal</label><input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
        <div class="filter-actions"><button type="submit" class="button-primary">Terapkan</button><a href="{{ route('library.visits.index') }}" class="button-secondary">Reset</a></div>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th class="table-number-heading">No.</th><th>Tanggal dan waktu</th><th>Siswa</th><th>NIS/NISN</th><th>Kelas</th><th>Kegiatan</th><th>Petugas</th><th>Catatan</th><th class="table-actions-heading">Aksi</th></tr></thead>
            <tbody>
                @forelse ($visits as $visit)
                    <tr>
                        <td class="table-number">{{ ($visits->firstItem() ?? 1) + $loop->index }}</td>
                        <td><div class="table-primary">{{ $visit->visit_date?->translatedFormat('d F Y') }}</div><div class="table-secondary">{{ substr((string) $visit->visit_time, 0, 5) }} WIB</div></td>
                        <td><div class="table-primary">{{ $visit->member?->member_name }}</div><div class="table-secondary">{{ $visit->member?->member_code }}</div></td>
                        <td>{{ $visit->member?->identity_number ?: '-' }}</td>
                        <td>{{ $visit->member?->department ?: '-' }}</td>
                        <td>{{ $visit->activity }}</td>
                        <td>{{ $visit->recorder?->full_name ?: '-' }}</td>
                        <td>{{ $visit->notes ?: '-' }}</td>
                        <td>
                            <div class="table-actions-inline">
                                <a href="{{ route('library.visits.edit', $visit) }}" class="action-link">Edit</a>
                                <form method="POST" action="{{ route('library.visits.destroy', $visit) }}" onsubmit="return confirm('Hapus catatan kunjungan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="action-button text-danger">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">Belum ada catatan kunjungan yang sesuai.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($visits->hasPages())
        <div class="pagination-bar">
            <span>Menampilkan {{ $visits->firstItem() }} sampai {{ $visits->lastItem() }} dari {{ $visits->total() }} kunjungan</span>
            <div class="pagination-actions">
                @if ($visits->onFirstPage())<span class="button-secondary is-disabled">Sebelumnya</span>@else<a href="{{ $visits->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>@endif
                <span class="page-indicator">Halaman {{ $visits->currentPage() }} dari {{ $visits->lastPage() }}</span>
                @if ($visits->hasMorePages())<a href="{{ $visits->nextPageUrl() }}" class="button-secondary">Berikutnya</a>@else<span class="button-secondary is-disabled">Berikutnya</span>@endif
            </div>
        </div>
    @endif
</section>
@endsection
