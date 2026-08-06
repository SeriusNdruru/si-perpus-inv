@extends('layouts.member')

@section('title', 'Riwayat Kunjungan Perpustakaan')
@section('page-title', 'Riwayat Kunjungan Perpustakaan')

@section('content')
<div class="member-page-heading">
    <div>
        <span class="member-kicker">Rekam kunjungan</span>
        <h2>Seluruh riwayat datang ke perpustakaan</h2>
        <p>Daftar ini menampilkan seluruh kunjungan yang telah dicatat oleh Admin Perpustakaan.</p>
    </div>
</div>

<section class="member-panel">
    <div class="member-panel-heading">
        <div><small>Kunjungan membaca</small><h2>Riwayat kunjungan</h2></div>
        <a href="{{ route('member.activity.index') }}">Kembali ke aktivitas</a>
    </div>
    <div class="member-table-wrap">
        <table class="member-table">
            <thead><tr><th class="table-number-heading">No.</th><th>Tanggal</th><th>Waktu</th><th>Kegiatan</th><th>Catatan</th></tr></thead>
            <tbody>
                @forelse ($visits as $visit)
                    <tr>
                        <td class="table-number">{{ ($visits->firstItem() ?? 1) + $loop->index }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($visit->visit_date)->format('d/m/Y') }}</td>
                        <td>{{ substr((string) $visit->visit_time, 0, 5) }} WIB</td>
                        <td>{{ $visit->activity }}</td>
                        <td>{{ $visit->notes ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Belum ada kunjungan yang dicatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($visits->hasPages())
        <div class="member-pagination">{{ $visits->links() }}</div>
    @endif
</section>
@endsection
