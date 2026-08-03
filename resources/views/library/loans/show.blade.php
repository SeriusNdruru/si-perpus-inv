@extends('layouts.app')

@section('title', 'Detail Peminjaman')
@section('page-title', 'Detail Peminjaman Buku')

@section('content')
    @php
        $statusClass = match ($loan->status) {
            'active' => 'badge-success',
            'overdue' => 'badge-warning',
            'completed' => 'badge-neutral',
            default => 'badge-muted',
        };
    @endphp

    <div class="detail-heading">
        <div>
            <p class="eyebrow">{{ $loan->loan_code }}</p>
            <h2>{{ $loan->member?->member_name }}</h2>
            <div class="detail-badges">
                <span class="badge {{ $statusClass }}">{{ $loan->statusLabel() }}</span>
                <span class="badge badge-neutral">{{ number_format($summary['total_items']) }} eksemplar</span>
            </div>
        </div>
        <div class="detail-actions">
            <a href="{{ route('library.loans.index') }}" class="button-secondary">Kembali</a>
            @if (in_array($loan->status, ['active', 'overdue'], true))
                <a href="{{ route('library.returns.index', ['search' => $loan->loan_code]) }}" class="button-primary button-link">Proses pengembalian</a>
            @endif
        </div>
    </div>

    <div class="detail-grid detail-grid-loan">
        <section class="panel detail-card">
            <div class="panel-header"><h2>Informasi Transaksi</h2></div>
            <dl class="definition-list">
                <div><dt>Kode anggota</dt><dd>{{ $loan->member?->member_code }}</dd></div>
                <div><dt>Jenis anggota</dt><dd>{{ $loan->member?->typeLabel() }}</dd></div>
                <div><dt>Tanggal pinjam</dt><dd>{{ $loan->loan_date?->translatedFormat('d F Y H:i') }}</dd></div>
                <div><dt>Jatuh tempo standar</dt><dd>{{ $loan->default_due_date?->translatedFormat('d F Y') }}</dd></div>
                <div><dt>Petugas</dt><dd>{{ $loan->processor?->full_name ?? '-' }}</dd></div>
                <div><dt>Username petugas</dt><dd>{{ $loan->processor?->username ?? '-' }}</dd></div>
                <div class="definition-full"><dt>Catatan</dt><dd>{{ $loan->notes ?: 'Tidak ada catatan.' }}</dd></div>
            </dl>
        </section>

        <section class="panel detail-card">
            <div class="panel-header"><h2>Ringkasan</h2></div>
            <dl class="definition-list">
                <div><dt>Total eksemplar</dt><dd>{{ number_format($summary['total_items']) }}</dd></div>
                <div><dt>Masih dipinjam</dt><dd>{{ number_format($summary['borrowed_items']) }}</dd></div>
                <div><dt>Sudah diproses</dt><dd>{{ number_format($summary['returned_items']) }}</dd></div>
                <div><dt>Total denda tercatat/berjalan</dt><dd>Rp{{ number_format($summary['total_fine'], 0, ',', '.') }}</dd></div>
            </dl>
            <div class="inline-notice inline-notice-compact">
                Denda dihitung Rp{{ number_format($finePerDay, 0, ',', '.') }} per hari untuk setiap eksemplar terlambat. Nilai final dicatat saat pengembalian.
            </div>
        </section>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Rincian koleksi</p>
                <h2>Eksemplar yang Dipinjam</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Eksemplar</th>
                        <th>Judul buku</th>
                        <th>Rak asal</th>
                        <th>Kondisi keluar</th>
                        <th>Jatuh tempo</th>
                        <th>Keterlambatan</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($itemRows as $row)
                        @php
                            $loanItem = $row['loan_item'];
                            $asset = $loanItem->asset;
                            $itemStatusClass = match ($loanItem->return_status) {
                                'borrowed' => $row['days_late'] > 0 ? 'badge-warning' : 'badge-success',
                                'returned' => 'badge-neutral',
                                default => 'badge-warning',
                            };
                        @endphp
                        <tr><td class="table-number">{{ (is_object($itemRows) && method_exists($itemRows, 'firstItem') && $itemRows->firstItem() !== null ? $itemRows->firstItem() : 1) + $loop->index }}</td>
                            <td>
                                <div class="table-primary">{{ $asset?->asset_code }}</div>
                                <div class="table-secondary">{{ $asset?->barcode }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $asset?->item?->item_name }}</div>
                                <div class="table-secondary">
                                    {{ $asset?->item?->bookDetail?->call_number ?: 'Nomor panggil belum diisi' }}
                                </div>
                            </td>
                            <td>{{ $asset?->shelf?->shelf_code ?? '-' }}</td>
                            <td>{{ $loanItem->conditionOutLabel() }}</td>
                            <td>{{ $loanItem->due_date?->translatedFormat('d F Y') }}</td>
                            <td>
                                @if ($row['days_late'] > 0)
                                    <div class="table-primary text-danger">{{ number_format($row['days_late']) }} hari</div>
                                    <div class="table-secondary">{{ $row['fine_is_final'] ? 'Final' : 'Estimasi' }} Rp{{ number_format($row['fine_amount'], 0, ',', '.') }}</div>
                                @else
                                    <span class="table-secondary">Tidak terlambat</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $itemStatusClass }}">{{ $loanItem->returnStatusLabel() }}</span></td>
                            <td>
                                @if ($loanItem->return_status === 'borrowed')
                                    <a href="{{ route('library.returns.edit', $loanItem) }}" class="action-link">Kembalikan</a>
                                @else
                                    <span class="table-secondary">Selesai</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
