@extends('layouts.app')

@section('title', 'Detail Pemeliharaan')
@section('page-title', 'Detail Pemeliharaan Aset')

@section('content')
    @php
        $isOpen = in_array($record->status, ['reported', 'in_progress'], true);
    @endphp

    <div class="detail-heading">
        <div>
            <p class="eyebrow">{{ $record->maintenance_code }}</p>
            <h2>{{ $record->asset?->item?->item_name ?? 'Barang tidak tersedia' }}</h2>
            <div class="detail-badges">
                <span class="badge {{ $record->statusBadgeClass() }}">{{ $record->statusLabel() }}</span>
                <span class="badge badge-neutral">Aset {{ $record->asset?->asset_code }}</span>
                <span class="badge badge-neutral">Kondisi {{ ucfirst((string) $record->asset?->condition_status) }}</span>
            </div>
        </div>
        <div class="detail-actions">
            <a href="{{ route('inventory.maintenance-records.index') }}" class="button-secondary">Kembali</a>
            @if ($isOpen)
                <a href="{{ route('inventory.maintenance-records.edit', $record) }}" class="button-secondary">Edit data</a>
                <a href="{{ route('inventory.maintenance-records.complete-form', $record) }}" class="button-primary button-link">Selesaikan</a>
            @endif
        </div>
    </div>

    <div class="detail-grid">
        <section class="panel detail-card">
            <div class="panel-header"><h2>Informasi Aset</h2></div>
            <dl class="definition-list">
                <div><dt>Kode barang</dt><dd>{{ $record->asset?->item?->item_code ?? '-' }}</dd></div>
                <div><dt>Kode aset</dt><dd>{{ $record->asset?->asset_code ?? '-' }}</dd></div>
                <div><dt>Barcode</dt><dd>{{ $record->asset?->barcode ?? '-' }}</dd></div>
                <div><dt>Kategori</dt><dd>{{ $record->asset?->item?->category?->category_name ?? '-' }}</dd></div>
                <div><dt>Lokasi</dt><dd>{{ $record->asset?->location?->location_name ?? '-' }}</dd></div>
                <div><dt>Rak</dt><dd>{{ $record->asset?->shelf?->shelf_code ?? '-' }}</dd></div>
                <div><dt>Status aset</dt><dd>{{ str_replace('_', ' ', ucfirst((string) $record->asset?->asset_status)) }}</dd></div>
                <div><dt>Kondisi aset</dt><dd>{{ ucfirst((string) $record->asset?->condition_status) }}</dd></div>
            </dl>
        </section>

        <section class="panel detail-card">
            <div class="panel-header"><h2>Waktu dan Penanggung Jawab</h2></div>
            <dl class="definition-list">
                <div><dt>Dilaporkan</dt><dd>{{ $record->reported_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
                <div><dt>Mulai diperbaiki</dt><dd>{{ $record->started_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
                <div><dt>Selesai atau batal</dt><dd>{{ $record->completed_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
                <div><dt>Pelapor</dt><dd>{{ $record->reporter?->full_name ?? 'Sistem' }}</dd></div>
                <div><dt>Petugas penanganan</dt><dd>{{ $record->handler?->full_name ?? '-' }}</dd></div>
                <div><dt>Vendor</dt><dd>{{ $record->vendor_name ?: '-' }}</dd></div>
                <div><dt>Biaya</dt><dd>Rp{{ number_format((float) $record->cost, 0, ',', '.') }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="panel detail-card">
        <div class="panel-header"><h2>Keluhan atau Kerusakan</h2></div>
        <div class="panel-body-form">
            <p style="white-space: pre-line;">{{ $record->issue_description }}</p>
        </div>
    </section>

    <section class="panel detail-card">
        <div class="panel-header"><h2>Tindakan Perbaikan</h2></div>
        <div class="panel-body-form">
            <p style="white-space: pre-line;">{{ $record->action_taken ?: 'Belum ada tindakan yang dicatat.' }}</p>
        </div>
    </section>

    @if ($isOpen)
        <section class="panel">
            <div class="panel-header panel-header-wrap">
                <div>
                    <p class="eyebrow">Tindakan</p>
                    <h2>Proses pemeliharaan</h2>
                    <p class="panel-description">Mulai proses saat aset telah diterima teknisi. Selesaikan setelah kondisi akhir dan biaya sudah diketahui.</p>
                </div>
                <div class="detail-actions">
                    @if ($record->status === 'reported')
                        <form method="POST" action="{{ route('inventory.maintenance-records.start', $record) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="button-secondary">Mulai perbaikan</button>
                        </form>
                    @endif
                    <a href="{{ route('inventory.maintenance-records.complete-form', $record) }}" class="button-primary button-link">Selesaikan</a>
                </div>
            </div>

            <form method="POST" action="{{ route('inventory.maintenance-records.cancel', $record) }}" class="panel-body-form" onsubmit="return confirm('Batalkan pemeliharaan ini dan pulihkan status aset?');">
                @csrf
                @method('PATCH')
                <div class="form-field">
                    <label for="cancellation_reason">Alasan pembatalan</label>
                    <textarea id="cancellation_reason" name="cancellation_reason" rows="3" maxlength="2000" required placeholder="Contoh: laporan duplikat atau aset tidak memerlukan perbaikan."></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button-danger-soft">Batalkan pemeliharaan</button>
                </div>
            </form>
        </section>
    @endif
@endsection
