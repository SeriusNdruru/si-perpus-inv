@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Ringkasan Sistem')

@section('content')
    <div class="stat-grid dashboard-stat-grid dashboard-stat-count-6">
        <article class="stat-card"><span>Total barang</span><strong>{{ number_format($statistics['total_items']) }}</strong></article>
        <article class="stat-card"><span>Judul buku</span><strong>{{ number_format($statistics['book_titles']) }}</strong></article>
        <article class="stat-card"><span>Buku tersedia</span><strong>{{ number_format($statistics['available_books']) }}</strong></article>
        <article class="stat-card"><span>Buku dipinjam</span><strong>{{ number_format($statistics['borrowed_books']) }}</strong></article>
        <article class="stat-card"><span>Anggota aktif</span><strong>{{ number_format($statistics['active_members']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Terlambat</span><strong>{{ number_format($statistics['overdue_loans']) }}</strong></article>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Data terbaru</p>
                <h2>Buku baru dari inventaris</h2>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Kode</th>
                        <th>Judul</th>
                        <th>Status katalog</th>
                        <th>Tanggal masuk</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($newBooks as $book)
                        <tr><td class="table-number">{{ (is_object($newBooks) && method_exists($newBooks, 'firstItem') && $newBooks->firstItem() !== null ? $newBooks->firstItem() : 1) + $loop->index }}</td>
                            <td>{{ $book->item_code }}</td>
                            <td>{{ $book->item_name }}</td>
                            <td><span class="badge">{{ $book->completion_status ?? 'incomplete' }}</span></td>
                            <td>{{ \Illuminate\Support\Carbon::parse($book->created_at)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Belum ada buku yang diinput.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
