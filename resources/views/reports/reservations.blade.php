@extends('layouts.app')

@section('title', 'Laporan Reservasi')
@section('page-title', 'Laporan Reservasi')

@section('content')
    @include('reports._tabs')

    <div class="report-stat-grid report-stat-grid-five">
        <article class="stat-card"><span>Total reservasi</span><strong>{{ number_format($summary['total']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Menunggu</span><strong>{{ number_format($summary['waiting']) }}</strong></article>
        <article class="stat-card"><span>Siap diambil</span><strong>{{ number_format($summary['ready']) }}</strong></article>
        <article class="stat-card"><span>Selesai</span><strong>{{ number_format($summary['completed']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Kedaluwarsa</span><strong>{{ number_format($summary['expired']) }}</strong></article>
    </div>

    <section class="panel report-panel">
        <div class="panel-header panel-header-wrap">
            <div><p class="eyebrow">Antrean koleksi</p><h2>Riwayat reservasi buku</h2></div>
            <div class="report-actions no-print"><button type="button" class="button-secondary" onclick="window.print()">Cetak</button><a href="{{ route('reports.reservations.csv', request()->query()) }}" class="button-primary button-link">Unduh CSV</a></div>
        </div>

        <form method="GET" action="{{ route('reports.reservations') }}" class="filter-bar filter-bar-report transaction-report-filter no-print">
            <div class="filter-field filter-search"><label for="search">Pencarian</label><input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Kode reservasi, anggota, atau judul buku"></div>
            <div class="filter-field"><label for="status">Status</label><select id="status" name="status"><option value="">Semua status</option><option value="waiting" @selected(request('status') === 'waiting')>Menunggu</option><option value="ready" @selected(request('status') === 'ready')>Siap diambil</option><option value="completed" @selected(request('status') === 'completed')>Selesai</option><option value="cancelled" @selected(request('status') === 'cancelled')>Dibatalkan</option><option value="expired" @selected(request('status') === 'expired')>Kedaluwarsa</option></select></div>
            <div class="filter-field"><label for="member_type">Jenis anggota</label><select id="member_type" name="member_type"><option value="">Semua jenis</option>@foreach (\App\Models\Member::typeOptions() as $typeValue => $typeLabel)<option value="{{ $typeValue }}" @selected(request('member_type') === $typeValue)>{{ $typeLabel }}</option>@endforeach</select></div>
            <div class="filter-field"><label for="date_from">Dari tanggal</label><input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}"></div>
            <div class="filter-field"><label for="date_to">Sampai tanggal</label><input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}"></div>
            <div class="filter-actions"><button type="submit" class="button-primary">Terapkan</button><a href="{{ route('reports.reservations') }}" class="button-secondary">Reset</a></div>
        </form>

        <div class="report-print-meta">Dicetak pada {{ now()->translatedFormat('d F Y H:i') }} oleh {{ auth()->user()->full_name }}</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Reservasi</th><th>Anggota</th><th>Buku</th><th>Tanggal</th><th>Antrean</th><th>Batas pengambilan</th><th>Status</th><th>Petugas</th></tr></thead>
                <tbody>
                    @forelse ($reservations as $reservation)
                        <tr>
                            <td><strong>{{ $reservation->reservation_code }}</strong></td>
                            <td><div class="table-primary">{{ $reservation->member?->member_name }}</div><div class="table-secondary">{{ $reservation->member?->member_code }}</div></td>
                            <td><div class="table-primary">{{ $reservation->item?->item_name }}</div><div class="table-secondary">ISBN {{ $reservation->item?->bookDetail?->isbn_13 ?: ($reservation->item?->bookDetail?->isbn_10 ?: '-') }}</div></td>
                            <td>{{ $reservation->reservation_date?->translatedFormat('d M Y H:i') }}</td><td>{{ $reservation->queue_number ? '#'.$reservation->queue_number : '-' }}</td>
                            <td>{{ $reservation->expires_at?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                            <td><span class="badge {{ $reservation->statusBadgeClass() }}">{{ $reservation->statusLabel() }}</span></td><td>{{ $reservation->processor?->full_name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">Tidak ada reservasi yang sesuai dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('reports._pagination', ['paginator' => $reservations, 'label' => 'reservasi'])
    </section>
@endsection
