@extends('layouts.app')

@section('title', 'Buat Pemeliharaan')
@section('page-title', 'Buat Laporan Pemeliharaan')

@section('content')
    <div class="detail-heading">
        <div>
            <p class="eyebrow">Pencatatan awal</p>
            <h2>Laporkan aset yang perlu dirawat</h2>
            <p class="panel-description">Aset yang dilaporkan langsung dikeluarkan dari status tersedia agar tidak digunakan selama proses perbaikan.</p>
        </div>
        <a href="{{ route('inventory.maintenance-records.index') }}" class="button-secondary">Kembali</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger content-alert">
            <strong>Data belum dapat disimpan.</strong>
            <ul class="error-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('inventory.maintenance-records.store') }}" class="panel">
        @csrf
        <div class="panel-header">
            <div>
                <p class="eyebrow">Informasi laporan</p>
                <h2>Data aset dan kerusakan</h2>
            </div>
        </div>

        <div class="form-grid form-grid-two panel-body-form">
            <div class="form-field form-field-full">
                <label for="asset_id">Aset <span class="required-mark">*</span></label>
                <select id="asset_id" name="asset_id" required>
                    <option value="">Pilih aset</option>
                    @foreach ($assets as $asset)
                        <option value="{{ $asset->id }}" @selected((int) old('asset_id', $selectedAssetId) === $asset->id)>
                            {{ $asset->asset_code }} · {{ $asset->item?->item_name }} · {{ $asset->location?->location_name ?? 'Tanpa lokasi' }} · {{ ucfirst($asset->condition_status) }}
                        </option>
                    @endforeach
                </select>
                <small>Hanya aset aktif yang tidak sedang dipinjam, direservasi, hilang, dihapuskan, atau dipelihara yang ditampilkan.</small>
            </div>

            <div class="form-field">
                <label for="reported_at">Tanggal dan waktu laporan <span class="required-mark">*</span></label>
                <input id="reported_at" name="reported_at" type="datetime-local" value="{{ old('reported_at', now()->format('Y-m-d\TH:i')) }}" required>
            </div>

            <div class="form-field">
                <label for="vendor_name">Vendor atau teknisi eksternal</label>
                <input id="vendor_name" name="vendor_name" type="text" maxlength="180" value="{{ old('vendor_name') }}" placeholder="Opsional">
            </div>

            <div class="form-field form-field-full">
                <label for="issue_description">Keluhan atau kerusakan <span class="required-mark">*</span></label>
                <textarea id="issue_description" name="issue_description" rows="6" maxlength="5000" required placeholder="Jelaskan gejala, bagian yang rusak, dan kondisi saat ditemukan.">{{ old('issue_description') }}</textarea>
            </div>

            <div class="form-field form-field-full">
                <label for="notes">Catatan awal</label>
                <textarea id="notes" name="notes" rows="3" maxlength="2000" placeholder="Informasi tambahan, lokasi penyerahan, atau prioritas penanganan.">{{ old('notes') }}</textarea>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('inventory.maintenance-records.index') }}" class="button-secondary">Batal</a>
            <button type="submit" class="button-primary">Simpan laporan</button>
        </div>
    </form>
@endsection
