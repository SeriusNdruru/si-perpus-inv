@extends('layouts.app')

@section('title', 'Riwayat Peminjaman Siswa')
@section('page-title', 'Riwayat Peminjaman Siswa')

@section('content')
@include('reports._tabs')

<div class="report-stat-grid report-stat-grid-four">
    <article class="stat-card"><span>Siswa peminjam</span><strong>{{ number_format($summary['students']) }}</strong></article>
    <article class="stat-card"><span>Total peminjaman</span><strong>{{ number_format($summary['loans']) }}</strong></article>
    <article class="stat-card stat-warning"><span>Peminjaman terbanyak</span><strong>{{ number_format($summary['highest']) }} kali</strong></article>
    <article class="stat-card"><span>Rata-rata per siswa</span><strong>{{ number_format($summary['average'], 1, ',', '.') }} kali</strong></article>
</div>

<section class="panel report-panel">
    <div class="panel-header panel-header-wrap">
        <div>
            <p class="eyebrow">Ringkasan per siswa</p>
            <h2>Jumlah peminjaman setiap siswa</h2>
        </div>
        <div class="report-actions no-print">
            <a href="{{ route('reports.loan-records.pdf', request()->query()) }}" class="button-primary button-link">Unduh PDF</a>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.loan-records') }}" class="filter-bar filter-bar-report no-print">
        <div class="filter-field filter-search">
            <label for="search">Pencarian siswa</label>
            <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama, kode anggota, atau NIS/NISN">
        </div>
        <div class="filter-field">
            <label for="class">Kelas</label>
            <select id="class" name="class">
                <option value="">Semua kelas</option>
                @foreach ($classes as $class)
                    <option value="{{ $class }}" @selected(($filters['class'] ?? '') === $class)>{{ $class }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field">
            <label for="date_from">Dari tanggal</label>
            <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div class="filter-field">
            <label for="date_to">Sampai tanggal</label>
            <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div class="filter-actions">
            <button type="submit" class="button-primary">Terapkan</button>
            <a href="{{ route('reports.loan-records') }}" class="button-secondary">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th class="table-number-heading">No.</th>
                    <th>NIS/NISN</th>
                    <th>Nama siswa</th>
                    <th>Jumlah peminjaman</th>
                    <th class="table-actions-heading">Detail</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td class="table-number">{{ ($records->firstItem() ?? 1) + $loop->index }}</td>
                        <td>{{ $record->identity_number ?: '-' }}</td>
                        <td><div class="table-primary">{{ $record->member_name }}</div></td>
                        <td><strong>{{ number_format($record->loan_count) }} kali</strong></td>
                        <td>
                            <a href="{{ route('reports.loan-records.detail', ['member' => $record->id, 'date_from' => $filters['date_from'] ?? null, 'date_to' => $filters['date_to'] ?? null]) }}" class="action-link">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">Tidak ada riwayat peminjaman siswa yang sesuai dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('reports._pagination', ['paginator' => $records, 'label' => 'siswa peminjam'])
</section>
@endsection
