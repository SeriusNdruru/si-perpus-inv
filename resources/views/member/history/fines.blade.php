@extends('layouts.member')

@section('title', 'Denda')
@section('page-title', 'Denda Anggota')

@section('content')
<div class="member-page-heading"><div><span class="member-kicker">Rekam pembayaran</span><h2>Denda keterlambatan atau kondisi buku</h2><p>Nominal dibayar dicatat oleh petugas perpustakaan.</p></div></div>
<section class="member-panel">
    <div class="member-table-wrap">
        <table class="member-table">
            <thead><tr><th>Transaksi</th><th>Buku</th><th>Denda</th><th>Dibayar</th><th>Sisa</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($fines as $fine)
                    @php $remaining = max((float) $fine->fine_amount - (float) $fine->paid_amount, 0); @endphp
                    <tr>
                        <td>{{ $fine->loan_code }}</td>
                        <td><strong>{{ $fine->item_name }}</strong></td>
                        <td>Rp{{ number_format((float) $fine->fine_amount, 0, ',', '.') }}</td>
                        <td>Rp{{ number_format((float) $fine->paid_amount, 0, ',', '.') }}</td>
                        <td>Rp{{ number_format($remaining, 0, ',', '.') }}</td>
                        <td><span class="member-status {{ $remaining <= 0 ? 'member-status-collected' : 'member-status-ready' }}">{{ $remaining <= 0 ? 'Lunas' : 'Belum lunas' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6">Tidak ada denda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
