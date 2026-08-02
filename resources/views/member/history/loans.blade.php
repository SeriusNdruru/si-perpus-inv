@extends('layouts.member')

@section('title', 'Riwayat Peminjaman')
@section('page-title', 'Riwayat Peminjaman')

@section('content')
<div class="member-page-heading"><div><span class="member-kicker">Rekam sirkulasi</span><h2>Pinjaman dan pengembalian</h2><p>Setiap transaksi dapat berisi beberapa buku.</p></div></div>
<section class="member-panel">
    <div class="member-table-wrap">
        <table class="member-table">
            <thead><tr><th>Kode</th><th>Tanggal pinjam</th><th>Jatuh tempo</th><th>Buku</th><th>Masih dipinjam</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($loans as $loan)
                    <tr>
                        <td><strong>{{ $loan->loan_code }}</strong></td>
                        <td>{{ \Illuminate\Support\Carbon::parse($loan->loan_date)->format('d/m/Y H:i') }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($loan->default_due_date)->format('d/m/Y') }}</td>
                        <td>{{ $loan->items_count }}</td>
                        <td>{{ $loan->active_items_count }}</td>
                        <td><span class="member-status member-status-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span></td>
                        <td><a href="{{ route('member.history.loan-detail', $loan->id) }}">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7">Belum ada riwayat peminjaman.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
