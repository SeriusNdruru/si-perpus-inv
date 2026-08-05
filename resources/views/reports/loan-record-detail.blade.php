@extends('layouts.app')

@section('title', 'Detail Riwayat Peminjaman Siswa')
@section('page-title', 'Detail Riwayat Peminjaman Siswa')

@section('content')
@include('reports._tabs')

<div class="detail-heading">
    <div>
        <p class="eyebrow">Riwayat per siswa</p>
        <h2>{{ $member->member_name }}</h2>
        <div class="detail-badges">
            <span class="badge badge-neutral">{{ $member->member_code }}</span>
            <span class="badge badge-neutral">NIS/NISN: {{ $member->identity_number ?: '-' }}</span>
            <span class="badge badge-neutral">{{ $member->department ?: 'Kelas belum diisi' }}</span>
        </div>
    </div>
    <div class="detail-actions no-print">
        <a href="{{ route('reports.loan-records', ['date_from' => $filters['date_from'] ?? null, 'date_to' => $filters['date_to'] ?? null]) }}" class="button-secondary button-link">Kembali</a>
    </div>
</div>

<div class="report-stat-grid report-stat-grid-four">
    <article class="stat-card"><span>Jumlah peminjaman</span><strong>{{ number_format($summary['loans']) }} kali</strong></article>
    <article class="stat-card"><span>Total buku dipinjam</span><strong>{{ number_format($summary['books']) }}</strong></article>
    <article class="stat-card stat-warning"><span>Masih dipinjam</span><strong>{{ number_format($summary['active']) }}</strong></article>
    <article class="stat-card"><span>Total denda</span><strong>Rp{{ number_format($summary['fines'], 0, ',', '.') }}</strong></article>
</div>

<section class="panel report-panel">
    <div class="panel-header panel-header-wrap">
        <div>
            <p class="eyebrow">Rincian buku</p>
            <h2>Buku yang pernah dipinjam</h2>
            @if (! empty($filters['date_from']) || ! empty($filters['date_to']))
                <p class="table-secondary">
                    Periode {{ $filters['date_from'] ?? 'awal' }} sampai {{ $filters['date_to'] ?? 'sekarang' }}.
                </p>
            @endif
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th class="table-number-heading">No.</th>
                    <th>Transaksi</th>
                    <th>Detail buku</th>
                    <th>Hari dan tanggal peminjaman</th>
                    <th>Tanggal pengembalian</th>
                    <th>Denda</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $fine = (float) $record->fine_amount;
                        $paid = (float) $record->paid_amount;
                        $remaining = max($fine - $paid, 0);
                        [$statusClass, $statusLabel] = match ($record->return_status) {
                            'borrowed' => ['badge-warning', 'Masih dipinjam'],
                            'returned' => ['badge-success', 'Dikembalikan'],
                            'damaged' => ['badge-danger', 'Rusak'],
                            'lost' => ['badge-danger', 'Hilang'],
                            default => ['badge-muted', ucfirst((string) $record->return_status)],
                        };
                    @endphp
                    <tr>
                        <td class="table-number">{{ ($records->firstItem() ?? 1) + $loop->index }}</td>
                        <td>
                            <div class="table-primary">{{ $record->loan_code }}</div>
                            <div class="table-secondary">Jatuh tempo {{ \Illuminate\Support\Carbon::parse($record->due_date)->translatedFormat('d F Y') }}</div>
                        </td>
                        <td>
                            <div class="table-primary">{{ $record->item_name }}</div>
                            <div class="table-secondary">{{ $record->book_code }} · {{ $record->asset_code }}</div>
                        </td>
                        <td>{{ \Illuminate\Support\Carbon::parse($record->borrowed_at)->translatedFormat('l, d F Y H:i') }}</td>
                        <td>
                            {{ $record->returned_at
                                ? \Illuminate\Support\Carbon::parse($record->returned_at)->translatedFormat('l, d F Y H:i')
                                : '-' }}
                        </td>
                        <td>
                            @if ($fine > 0)
                                <div class="table-primary">Rp{{ number_format($fine, 0, ',', '.') }}</div>
                                <div class="table-secondary">
                                    {{ $remaining <= 0 ? 'Lunas' : 'Sisa Rp'.number_format($remaining, 0, ',', '.') }}
                                </div>
                            @else
                                <span class="badge badge-success">Tidak ada</span>
                            @endif
                        </td>
                        <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">Belum ada detail peminjaman untuk siswa ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('reports._pagination', ['paginator' => $records, 'label' => 'buku'])
</section>
@endsection
