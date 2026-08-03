@extends('layouts.app')

@section('title', 'Detail Laporan Kerusakan')
@section('page-title', 'Detail Laporan Kerusakan')

@section('content')
<div class="detail-heading"><div><p class="eyebrow">{{ $publicDamageReport->report_code }}</p><h2>{{ $publicDamageReport->item?->item_name ?: 'Laporan lokasi' }}</h2><p class="panel-description">{{ $publicDamageReport->created_at?->format('d/m/Y H:i') }}</p></div><a href="{{ route('inventory.public-damage-reports.index') }}" class="button-secondary">Kembali</a></div>

<div class="detail-grid">
    <section class="panel detail-card"><div class="panel-header"><h2>Objek laporan</h2></div><dl class="definition-list">
        <div><dt>Barang</dt><dd>{{ $publicDamageReport->item?->item_name ?: '-' }}</dd></div>
        <div><dt>Kode barang</dt><dd>{{ $publicDamageReport->item?->item_code ?: '-' }}</dd></div>
        <div><dt>Kode aset</dt><dd>{{ $publicDamageReport->asset?->asset_code ?: '-' }}</dd></div>
        <div><dt>Kondisi aset</dt><dd>{{ $publicDamageReport->asset?->condition_status ?: '-' }}</dd></div>
        <div><dt>Status aset</dt><dd>{{ $publicDamageReport->asset?->asset_status ?: '-' }}</dd></div>
        <div><dt>Lokasi</dt><dd>{{ $publicDamageReport->location?->location_name ?: '-' }}</dd></div>
    </dl></section>
    <section class="panel detail-card"><div class="panel-header"><h2>Pelapor</h2></div><dl class="definition-list">
        <div><dt>Nama</dt><dd>{{ $publicDamageReport->reporter_name ?: 'Anonim' }}</dd></div>
        <div><dt>Kontak</dt><dd>{{ $publicDamageReport->reporter_contact ?: '-' }}</dd></div>
        <div><dt>Status</dt><dd>{{ $publicDamageReport->statusLabel() }}</dd></div>
        <div><dt>Petugas</dt><dd>{{ $publicDamageReport->handler?->full_name ?: '-' }}</dd></div>
    </dl></section>
</div>

<section class="panel detail-card"><div class="panel-header"><h2>Penjelasan kerusakan</h2></div><div class="panel-body-form"><p style="white-space:pre-line">{{ $publicDamageReport->issue_description }}</p>@if ($publicDamageReport->photo_path)<img class="public-report-photo" src="{{ route('media.thumbnail', ['path' => $publicDamageReport->photo_path, 'size' => 1200]) }}" alt="Foto kerusakan" loading="lazy" decoding="async" data-image-retry>@endif</div></section>

<section class="panel">
    <div class="panel-header"><h2>Perbarui penanganan</h2></div>
    <form method="POST" action="{{ route('inventory.public-damage-reports.update', $publicDamageReport) }}" class="data-form">@csrf @method('PATCH')
        <div class="form-grid">
            <div class="form-field"><label>Status</label><select name="status" required>@foreach (['submitted'=>'Baru dilaporkan','reviewed'=>'Sudah diperiksa','in_progress'=>'Sedang ditangani','resolved'=>'Selesai','rejected'=>'Ditolak'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$publicDamageReport->status)===$value)>{{ $label }}</option>@endforeach</select></div>
            <div class="form-field form-field-full"><label>Catatan admin</label><textarea name="admin_notes" rows="5" maxlength="3000">{{ old('admin_notes',$publicDamageReport->admin_notes) }}</textarea></div>
        </div>
        <div class="form-actions"><button class="button-primary" type="submit">Simpan status</button></div>
    </form>
</section>
@endsection
