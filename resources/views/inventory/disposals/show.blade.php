@extends('layouts.app')

@section('title', 'Detail Penghapusan')
@section('page-title', 'Detail Penghapusan Aset')

@section('content')
    @php
        $isSuperAdmin = auth()->user()->hasRole('SUPER_ADMIN');
    @endphp

    <div class="detail-heading">
        <div>
            <p class="eyebrow">{{ $disposal->disposal_code }}</p>
            <h2>{{ $disposal->asset?->item?->item_name ?? 'Barang tidak tersedia' }}</h2>
            <div class="detail-badges">
                <span class="badge {{ $disposal->statusBadgeClass() }}">{{ $disposal->statusLabel() }}</span>
                <span class="badge badge-neutral">Aset {{ $disposal->asset?->asset_code }}</span>
                <span class="badge badge-neutral">Kondisi {{ ucfirst((string) $disposal->asset?->condition_status) }}</span>
            </div>
        </div>
        <div class="detail-actions">
            <a href="{{ route('inventory.disposals.index') }}" class="button-secondary">Kembali</a>
            @if (in_array($disposal->status, ['proposed', 'rejected'], true))
                <a href="{{ route('inventory.disposals.edit', $disposal) }}" class="button-secondary">Edit usulan</a>
            @endif
            @if ($disposal->status === 'approved')
                <a href="{{ route('inventory.disposals.complete-form', $disposal) }}" class="button-primary button-link">Laksanakan</a>
            @endif
        </div>
    </div>

    <div class="detail-grid">
        <section class="panel detail-card">
            <div class="panel-header"><h2>Informasi Aset</h2></div>
            <dl class="definition-list">
                <div><dt>Kode barang</dt><dd>{{ $disposal->asset?->item?->item_code ?? '-' }}</dd></div>
                <div><dt>Kode aset</dt><dd>{{ $disposal->asset?->asset_code ?? '-' }}</dd></div>
                <div><dt>Barcode</dt><dd>{{ $disposal->asset?->barcode ?? '-' }}</dd></div>
                <div><dt>Kategori</dt><dd>{{ $disposal->asset?->item?->category?->category_name ?? '-' }}</dd></div>
                <div><dt>Lokasi terakhir</dt><dd>{{ $disposal->asset?->location?->location_name ?? '-' }}</dd></div>
                <div><dt>Rak</dt><dd>{{ $disposal->asset?->shelf?->shelf_code ?? '-' }}</dd></div>
                <div><dt>Supplier</dt><dd>{{ $disposal->asset?->supplier?->supplier_name ?? '-' }}</dd></div>
                <div><dt>Status aset</dt><dd>{{ str_replace('_', ' ', ucfirst((string) $disposal->asset?->asset_status)) }}</dd></div>
            </dl>
        </section>

        <section class="panel detail-card">
            <div class="panel-header"><h2>Persetujuan dan Pelaksanaan</h2></div>
            <dl class="definition-list">
                <div><dt>Diusulkan</dt><dd>{{ $disposal->proposed_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
                <div><dt>Pengusul</dt><dd>{{ $disposal->proposer?->full_name ?? 'Sistem' }}</dd></div>
                <div><dt>Diputuskan</dt><dd>{{ $disposal->approved_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
                <div><dt>Penyetuju</dt><dd>{{ $disposal->approver?->full_name ?? '-' }}</dd></div>
                <div><dt>Dilaksanakan</dt><dd>{{ $disposal->disposed_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
                <div><dt>Metode</dt><dd>{{ $disposal->methodLabel() }}</dd></div>
                <div><dt>Status</dt><dd>{{ $disposal->statusLabel() }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="panel detail-card">
        <div class="panel-header"><h2>Alasan Penghapusan</h2></div>
        <div class="panel-body-form"><p style="white-space: pre-line;">{{ $disposal->reason }}</p></div>
    </section>

    <section class="panel detail-card">
        <div class="panel-header"><h2>Catatan</h2></div>
        <div class="panel-body-form"><p style="white-space: pre-line;">{{ $disposal->notes ?: 'Tidak ada catatan tambahan.' }}</p></div>
    </section>

    @if ($disposal->status === 'proposed' && $isSuperAdmin)
        <section class="panel">
            <div class="panel-header panel-header-wrap">
                <div>
                    <p class="eyebrow">Keputusan Super Admin</p>
                    <h2>Setujui atau tolak usulan</h2>
                    <p class="panel-description">Persetujuan belum mengubah status aset. Penghapusan final dilakukan pada langkah pelaksanaan.</p>
                </div>
                <form method="POST" action="{{ route('inventory.disposals.approve', $disposal) }}" onsubmit="return confirm('Setujui usulan penghapusan ini?');">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="button-primary">Setujui usulan</button>
                </form>
            </div>

            <form method="POST" action="{{ route('inventory.disposals.reject', $disposal) }}" class="panel-body-form" onsubmit="return confirm('Tolak usulan penghapusan ini?');">
                @csrf
                @method('PATCH')
                <div class="form-field">
                    <label for="rejection_reason">Alasan penolakan</label>
                    <textarea id="rejection_reason" name="rejection_reason" rows="4" maxlength="3000" required placeholder="Jelaskan data atau dokumen yang perlu diperbaiki."></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button-danger-soft">Tolak usulan</button>
                </div>
            </form>
        </section>
    @elseif ($disposal->status === 'proposed')
        <div class="inline-notice">Usulan sedang menunggu persetujuan Super Admin.</div>
    @elseif ($disposal->status === 'approved')
        <div class="inline-notice">Usulan sudah disetujui. Admin Inventaris atau Super Admin dapat melaksanakan penghapusan.</div>
    @endif
@endsection
