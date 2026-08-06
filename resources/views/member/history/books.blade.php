@extends('layouts.member')

@section('title', 'Riwayat Buku yang Dipinjam')
@section('page-title', 'Riwayat Buku yang Dipinjam')

@section('content')
<div class="member-page-heading">
    <div>
        <span class="member-kicker">Riwayat buku</span>
        <h2>Seluruh buku yang pernah dipinjam</h2>
        <p>Daftar ini menampilkan setiap buku yang pernah masuk dalam transaksi peminjaman Anda.</p>
    </div>
</div>

<section class="member-panel">
    <div class="member-panel-heading">
        <div><small>Riwayat buku</small><h2>Buku yang pernah dipinjam</h2></div>
        <a href="{{ route('member.activity.index') }}">Kembali ke aktivitas</a>
    </div>
    <div class="member-table-wrap">
        <table class="member-table">
            <thead><tr><th class="table-number-heading">No.</th><th>Buku</th><th>Kode buku</th><th>Tanggal pinjam</th><th>Jatuh tempo</th><th>Tanggal kembali</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($borrowedBooks as $book)
                    @php
                        $statusLabel = match ($book->return_status) {
                            'borrowed' => 'Masih dipinjam',
                            'returned' => 'Dikembalikan',
                            'damaged' => 'Rusak',
                            'lost' => 'Hilang',
                            default => ucfirst($book->return_status),
                        };
                    @endphp
                    <tr>
                        <td class="table-number">{{ ($borrowedBooks->firstItem() ?? 1) + $loop->index }}</td>
                        <td><strong>{{ $book->item_name }}</strong><div>{{ $book->loan_code }}</div></td>
                        <td>{{ $book->asset_code }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($book->borrowed_at)->format('d/m/Y H:i') }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($book->due_date)->format('d/m/Y') }}</td>
                        <td>{{ $book->returned_at ? \Illuminate\Support\Carbon::parse($book->returned_at)->format('d/m/Y H:i') : '-' }}</td>
                        <td><span class="member-status member-status-{{ $book->return_status }}">{{ $statusLabel }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7">Belum ada buku yang pernah dipinjam.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($borrowedBooks->hasPages())
        <div class="member-pagination">{{ $borrowedBooks->links() }}</div>
    @endif
</section>
@endsection
