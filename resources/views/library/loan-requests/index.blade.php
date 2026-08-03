@extends('layouts.app')

@section('title', 'Pengajuan Peminjaman Online')
@section('page-title', 'Pengajuan Peminjaman Online')

@section('content')
<div class="detail-heading">
    <div>
        <p class="eyebrow">Dashboard perpustakaan</p>
        <h2>Persetujuan pengajuan siswa</h2>
        <p class="panel-description">Setujui pengajuan, siapkan eksemplar, lalu konfirmasi pengambilan agar transaksi peminjaman dibuat.</p>
    </div>
</div>

<div class="stat-grid">
    <article class="stat-card {{ $summary['submitted'] > 0 ? 'stat-warning' : '' }}"><span>Menunggu persetujuan</span><strong>{{ $summary['submitted'] }}</strong></article>
    <article class="stat-card"><span>Sedang disiapkan</span><strong>{{ $summary['approved'] }}</strong></article>
    <article class="stat-card {{ $summary['ready'] > 0 ? 'stat-warning' : '' }}"><span>Siap diambil</span><strong>{{ $summary['ready'] }}</strong></article>
    <article class="stat-card"><span>Diambil hari ini</span><strong>{{ $summary['today_collected'] }}</strong></article>
</div>

<section class="panel">
    <form method="GET" class="filter-bar">
        <div class="form-field filter-search"><label>Pencarian</label><input name="search" type="search" value="{{ request('search') }}" placeholder="Kode pengajuan, anggota, atau identitas"></div>
        <div class="form-field"><label>Status</label><select name="status">
            <option value="">Semua status</option>
            @foreach (['submitted'=>'Menunggu persetujuan','approved'=>'Disetujui','ready'=>'Siap diambil','collected'=>'Sudah diambil','rejected'=>'Ditolak','cancelled'=>'Dibatalkan','expired'=>'Kedaluwarsa'] as $value=>$label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select></div>
        <div class="filter-actions"><button class="button-primary" type="submit">Terapkan</button><a href="{{ route('library.loan-requests.index') }}" class="button-secondary">Reset</a></div>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th class="table-number-heading">No.</th><th>Kode</th><th>Anggota</th><th>Tanggal</th><th>Buku</th><th>Status</th><th>Batas ambil</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse ($requests as $loanRequest)
                    <tr><td class="table-number">{{ (is_object($requests) && method_exists($requests, 'firstItem') && $requests->firstItem() !== null ? $requests->firstItem() : 1) + $loop->index }}</td>
                        <td><strong>{{ $loanRequest->request_code }}</strong></td>
                        <td><div class="table-primary">{{ $loanRequest->member?->member_name }}</div><div class="table-secondary">{{ $loanRequest->member?->member_code }} · {{ $loanRequest->member?->identity_number }}</div></td>
                        <td>{{ $loanRequest->requested_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $loanRequest->items_count }} judul</td>
                        <td><span class="badge {{ $loanRequest->statusBadgeClass() }}">{{ $loanRequest->statusLabel() }}</span></td>
                        <td>{{ $loanRequest->pickup_expires_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td><a href="{{ route('library.loan-requests.show', $loanRequest) }}" class="action-link">Proses</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">Belum ada pengajuan sesuai filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
