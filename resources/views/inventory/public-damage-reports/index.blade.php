@extends('layouts.app')

@section('title', 'Laporan Kerusakan Publik')
@section('page-title', 'Laporan Kerusakan Publik')

@section('content')
<div class="detail-heading"><div><h2>Kerusakan barang dan buku</h2><p class="panel-description">Laporan berasal dari dashboard inventaris umum. Periksa foto, lokasi, dan data barang sebelum memperbarui status.</p></div><a href="{{ route('public.inventory.report-damage') }}" target="_blank" class="button-secondary">Buka form publik</a></div>

<section class="panel">
    <form method="GET" class="filter-bar">
        <div class="form-field filter-search"><label>Pencarian</label><input name="search" type="search" value="{{ request('search') }}" placeholder="Kode laporan, aset, barang, atau pelapor"></div>
        <div class="form-field"><label>Status</label><select name="status"><option value="">Semua status</option>@foreach (['submitted'=>'Baru','reviewed'=>'Diperiksa','in_progress'=>'Ditangani','resolved'=>'Selesai','rejected'=>'Ditolak'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="filter-actions"><button class="button-primary" type="submit">Terapkan</button><a class="button-secondary" href="{{ route('inventory.public-damage-reports.index') }}">Reset</a></div>
    </form>
    <div class="table-wrap"><table>
        <thead><tr><th>Kode</th><th>Barang/aset</th><th>Lokasi</th><th>Pelapor</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($reports as $report)
                <tr>
                    <td><strong>{{ $report->report_code }}</strong></td>
                    <td><div class="table-primary">{{ $report->item?->item_name ?: 'Barang tidak dipilih' }}</div><div class="table-secondary">{{ $report->asset?->asset_code ?: '-' }}</div></td>
                    <td>{{ $report->location?->location_name ?: '-' }}</td>
                    <td>{{ $report->reporter_name ?: 'Anonim' }}</td>
                    <td><span class="badge {{ $report->status === 'resolved' ? 'badge-success' : ($report->status === 'submitted' ? 'badge-warning' : 'badge-neutral') }}">{{ $report->statusLabel() }}</span></td>
                    <td>{{ $report->created_at?->format('d/m/Y H:i') }}</td>
                    <td><a class="action-link" href="{{ route('inventory.public-damage-reports.show', $report) }}">Periksa</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-state">Belum ada laporan kerusakan.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</section>
@endsection
