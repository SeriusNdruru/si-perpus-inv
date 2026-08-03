@extends('layouts.member')

@section('title', 'Pengajuan Peminjaman')
@section('page-title', 'Pengajuan Peminjaman')

@section('content')
<div class="member-page-heading">
    <div><span class="member-kicker">Status persetujuan</span><h2>Pengajuan buku Anda</h2><p>Pengajuan siap diambil harus dikonfirmasi petugas ketika buku diserahkan.</p></div>
    <a href="{{ route('member.books.index') }}" class="member-button member-button-primary">Buat pengajuan</a>
</div>

<section class="member-panel">
    <div class="member-table-wrap">
        <table class="member-table">
            <thead><tr><th class="table-number-heading">No.</th><th>Kode</th><th>Tanggal</th><th>Jumlah buku</th><th>Status</th><th>Batas ambil</th><th></th></tr></thead>
            <tbody>
                @forelse ($requests as $loanRequest)
                    <tr><td class="table-number">{{ (is_object($requests) && method_exists($requests, 'firstItem') && $requests->firstItem() !== null ? $requests->firstItem() : 1) + $loop->index }}</td>
                        <td><strong>{{ $loanRequest->request_code }}</strong></td>
                        <td>{{ $loanRequest->requested_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $loanRequest->items_count }} judul</td>
                        <td><span class="member-status member-status-{{ $loanRequest->status }}">{{ $loanRequest->statusLabel() }}</span></td>
                        <td>{{ $loanRequest->pickup_expires_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td><a href="{{ route('member.loan-requests.show', $loanRequest) }}">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7">Belum ada pengajuan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
