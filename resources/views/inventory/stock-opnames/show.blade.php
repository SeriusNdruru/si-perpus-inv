@extends('layouts.app')

@section('title', 'Detail Stock Opname')
@section('page-title', 'Detail Stock Opname')

@section('content')
    @php
        $statusClass = match ($stockOpname->status) {
            'completed' => 'badge-success',
            'draft', 'in_progress' => 'badge-warning',
            default => 'badge-muted',
        };
        $canProcess = in_array($stockOpname->status, ['draft', 'in_progress'], true);
    @endphp

    <div class="stock-opname-hero">
        <div>
            <p class="eyebrow">{{ $stockOpname->opname_code }}</p>
            <h2>{{ $stockOpname->location?->location_name ?? 'Lokasi tidak tersedia' }}</h2>
            <div class="detail-badges">
                <span class="badge {{ $statusClass }}">{{ $stockOpname->statusLabel() }}</span>
                <span class="badge badge-neutral">{{ $stockOpname->opname_date?->format('d F Y') }}</span>
            </div>
            @if ($stockOpname->notes)
                <p class="stock-opname-hero-note">{{ $stockOpname->notes }}</p>
            @endif
        </div>
        <div class="detail-actions no-print">
            <a href="{{ route('inventory.stock-opnames.index') }}" class="button-secondary">Kembali</a>
            @if ($canProcess)
                <a href="{{ route('inventory.stock-opnames.edit', $stockOpname) }}" class="button-primary button-link">Periksa fisik</a>
            @endif
        </div>
    </div>

    <div class="stat-grid stock-opname-stat-grid">
        <article class="stat-card"><span>Total baris</span><strong>{{ number_format($summary['total']) }}</strong></article>
        <article class="stat-card"><span>Sudah diperiksa</span><strong>{{ number_format($summary['checked']) }}</strong></article>
        <article class="stat-card"><span>Sesuai</span><strong>{{ number_format($summary['matched']) }}</strong></article>
        <article class="stat-card {{ ($summary['surplus'] + $summary['shortage']) > 0 ? 'stat-warning' : '' }}">
            <span>Selisih jumlah</span><strong>{{ number_format($summary['surplus'] + $summary['shortage']) }}</strong>
        </article>
        <article class="stat-card {{ $summary['damaged'] > 0 ? 'stat-warning' : '' }}"><span>Rusak</span><strong>{{ number_format($summary['damaged']) }}</strong></article>
        <article class="stat-card {{ $summary['missing'] > 0 ? 'stat-warning' : '' }}"><span>Tidak ditemukan</span><strong>{{ number_format($summary['missing']) }}</strong></article>
    </div>

    <div class="detail-grid stock-opname-meta-grid">
        <section class="panel detail-card">
            <div class="panel-header"><h2>Informasi Pemeriksaan</h2></div>
            <dl class="definition-list">
                <div><dt>Kode lokasi</dt><dd>{{ $stockOpname->location?->location_code ?? '-' }}</dd></div>
                <div><dt>Dibuat oleh</dt><dd>{{ $stockOpname->creator?->full_name ?? 'Sistem' }}</dd></div>
                <div><dt>Disetujui oleh</dt><dd>{{ $stockOpname->approver?->full_name ?? '-' }}</dd></div>
                <div><dt>Waktu persetujuan</dt><dd>{{ $stockOpname->approved_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
            </dl>
        </section>
        <section class="panel detail-card">
            <div class="panel-header"><h2>Rekap Kuantitas</h2></div>
            <dl class="definition-list">
                <div><dt>Jumlah menurut sistem</dt><dd>{{ number_format($summary['expected'], 2, ',', '.') }}</dd></div>
                <div><dt>Jumlah hasil fisik</dt><dd>{{ number_format($summary['actual'], 2, ',', '.') }}</dd></div>
                <div><dt>Selisih bersih</dt><dd>{{ number_format($summary['actual'] - $summary['expected'], 2, ',', '.') }}</dd></div>
            </dl>
        </section>
    </div>

    @if ($canProcess)
        <section class="panel stock-opname-action-panel no-print">
            <div>
                <p class="eyebrow">Tindakan</p>
                <h2>Proses stock opname</h2>
                <p class="panel-description">Simpan seluruh hasil pemeriksaan sebelum menyelesaikan. Penyelesaian akan menerapkan selisih stok, menandai aset rusak, dan menandai aset yang tidak ditemukan sebagai hilang.</p>
            </div>
            <div class="stock-opname-action-buttons">
                @if ($stockOpname->status === 'draft')
                    <form method="POST" action="{{ route('inventory.stock-opnames.start', $stockOpname) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="button-secondary">Mulai pemeriksaan</button>
                    </form>
                @endif

                <form method="POST" action="{{ route('inventory.stock-opnames.complete', $stockOpname) }}" onsubmit="return confirm('Selesaikan stock opname? Saldo stok dan status aset akan diperbarui sesuai hasil pemeriksaan.');">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="button-primary">Selesaikan dan terapkan</button>
                </form>

                <form method="POST" action="{{ route('inventory.stock-opnames.cancel', $stockOpname) }}" onsubmit="return confirm('Batalkan stock opname ini? Hasil pemeriksaan tidak akan diterapkan.');">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="button-danger-soft">Batalkan</button>
                </form>
            </div>
        </section>
    @endif

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Rincian hasil</p>
                <h2>Barang dan aset diperiksa</h2>
            </div>
            <button type="button" class="button-secondary no-print" onclick="window.print()">Cetak</button>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th>Unit atau aset</th>
                        <th>Data sistem</th>
                        <th>Hasil fisik</th>
                        <th>Selisih</th>
                        <th>Temuan</th>
                        <th>Petugas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        @php
                            $findingClass = match ($line->finding_status) {
                                'matched' => 'badge-success',
                                'surplus' => 'badge-neutral',
                                default => 'badge-warning',
                            };
                        @endphp
                        <tr>
                            <td>
                                <div class="table-primary">{{ $line->item?->item_name }}</div>
                                <div class="table-secondary">{{ $line->item?->item_code }}</div>
                            </td>
                            <td>
                                @if ($line->asset)
                                    <div class="table-primary">{{ $line->asset->asset_code }}</div>
                                    <div class="table-secondary">Barcode {{ $line->asset->barcode }}</div>
                                @else
                                    <span class="badge badge-neutral">Stok kuantitas</span>
                                @endif
                            </td>
                            <td>{{ number_format((float) $line->expected_quantity, 2, ',', '.') }} {{ $line->item?->unit?->unit_code }}</td>
                            <td>{{ number_format((float) $line->actual_quantity, 2, ',', '.') }} {{ $line->item?->unit?->unit_code }}</td>
                            <td class="{{ (float) $line->difference_quantity !== 0.0 ? 'text-warning-strong' : '' }}">
                                {{ number_format((float) $line->difference_quantity, 2, ',', '.') }}
                            </td>
                            <td>
                                <span class="badge {{ $findingClass }}">{{ $line->findingLabel() }}</span>
                                @if ($line->notes)
                                    <div class="table-secondary">{{ $line->notes }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="table-primary">{{ $line->checker?->full_name ?? 'Belum diperiksa' }}</div>
                                <div class="table-secondary">{{ $line->checked_at?->format('d/m/Y H:i') }}</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
