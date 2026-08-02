@extends('layouts.app')

@section('title', 'Laporan Anggota')
@section('page-title', 'Laporan Anggota')

@section('content')
    @include('reports._tabs')

    <div class="report-stat-grid report-stat-grid-five">
        <article class="stat-card"><span>Total anggota</span><strong>{{ number_format($summary['total']) }}</strong></article>
        <article class="stat-card"><span>Aktif</span><strong>{{ number_format($summary['active']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Kedaluwarsa</span><strong>{{ number_format($summary['expired']) }}</strong></article>
        <article class="stat-card"><span>Transaksi aktif</span><strong>{{ number_format($summary['active_loans']) }}</strong></article>
        <article class="stat-card"><span>Reservasi aktif</span><strong>{{ number_format($summary['active_reservations']) }}</strong></article>
    </div>

    <section class="panel report-panel">
        <div class="panel-header panel-header-wrap">
            <div><p class="eyebrow">Keanggotaan</p><h2>Aktivitas dan status anggota</h2></div>
            <div class="report-actions no-print"><button type="button" class="button-secondary" onclick="window.print()">Cetak</button><a href="{{ route('reports.members.csv', request()->query()) }}" class="button-primary button-link">Unduh CSV</a></div>
        </div>

        <form method="GET" action="{{ route('reports.members') }}" class="filter-bar filter-bar-report transaction-report-filter no-print">
            <div class="filter-field filter-search"><label for="search">Pencarian</label><input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Kode, nama, identitas, kelas, atau email"></div>
            <div class="filter-field"><label for="member_type">Jenis anggota</label><select id="member_type" name="member_type"><option value="">Semua jenis</option>@foreach (\App\Models\Member::typeOptions() as $typeValue => $typeLabel)<option value="{{ $typeValue }}" @selected(request('member_type') === $typeValue)>{{ $typeLabel }}</option>@endforeach</select></div>
            <div class="filter-field"><label for="status">Status</label><select id="status" name="status"><option value="">Semua status</option><option value="active" @selected(request('status') === 'active')>Aktif</option><option value="suspended" @selected(request('status') === 'suspended')>Ditangguhkan</option><option value="inactive" @selected(request('status') === 'inactive')>Tidak aktif</option><option value="expired" @selected(request('status') === 'expired')>Kedaluwarsa</option></select></div>
            <div class="filter-field"><label for="date_from">Bergabung mulai</label><input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}"></div>
            <div class="filter-field"><label for="date_to">Bergabung sampai</label><input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}"></div>
            <div class="filter-actions"><button type="submit" class="button-primary">Terapkan</button><a href="{{ route('reports.members') }}" class="button-secondary">Reset</a></div>
        </form>

        <div class="report-print-meta">Dicetak pada {{ now()->translatedFormat('d F Y H:i') }} oleh {{ auth()->user()->full_name }}</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Anggota</th><th>Jenis</th><th>Kelas</th><th>Masa berlaku</th><th>Status</th><th>Total transaksi</th><th>Pinjaman aktif</th><th>Reservasi aktif</th><th>Sisa denda</th></tr></thead>
                <tbody>
                    @forelse ($members as $member)
                        @php $remainingFine = max((float) ($member->total_fines ?? 0) - (float) ($member->paid_fines ?? 0), 0); @endphp
                        <tr>
                            <td><div class="table-primary">{{ $member->member_name }}</div><div class="table-secondary">{{ $member->member_code }} · {{ $member->identity_number ?: '-' }}</div></td>
                            <td>{{ $member->typeLabel() }}</td><td>{{ $member->department ?: '-' }}</td>
                            <td><div class="table-primary">{{ $member->join_date?->translatedFormat('d M Y') }}</div><div class="table-secondary">sampai {{ $member->expiry_date?->translatedFormat('d M Y') ?? 'tanpa batas' }}</div></td>
                            <td><span class="badge {{ $member->status === 'active' ? 'badge-success' : ($member->status === 'suspended' ? 'badge-warning' : 'badge-muted') }}">{{ $member->statusLabel() }}</span></td>
                            <td>{{ number_format((int) $member->total_loans) }}</td><td>{{ number_format((int) $member->active_loans) }}</td><td>{{ number_format((int) $member->active_reservations) }}</td>
                            <td>Rp{{ number_format($remainingFine, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Tidak ada anggota yang sesuai dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('reports._pagination', ['paginator' => $members, 'label' => 'anggota'])
    </section>
@endsection
