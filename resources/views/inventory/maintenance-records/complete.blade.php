@extends('layouts.app')

@section('title', 'Selesaikan Pemeliharaan')
@section('page-title', 'Selesaikan Pemeliharaan Aset')

@section('content')
    <div class="detail-heading">
        <div>
            <p class="eyebrow">{{ $record->maintenance_code }}</p>
            <h2>{{ $record->asset?->item?->item_name }}</h2>
            <p class="panel-description">Aset {{ $record->asset?->asset_code }} akan dikembalikan ke status operasional berdasarkan kondisi akhir.</p>
        </div>
        <a href="{{ route('inventory.maintenance-records.show', $record) }}" class="button-secondary">Kembali</a>
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

    <form method="POST" action="{{ route('inventory.maintenance-records.complete', $record) }}" class="panel">
        @csrf
        @method('PATCH')
        <div class="panel-header">
            <div>
                <p class="eyebrow">Hasil akhir</p>
                <h2>Rincian penyelesaian</h2>
            </div>
        </div>

        <div class="form-grid form-grid-two panel-body-form">
            <div class="form-field">
                <label for="completed_at">Tanggal dan waktu selesai <span class="required-mark">*</span></label>
                <input id="completed_at" name="completed_at" type="datetime-local" value="{{ old('completed_at', now()->format('Y-m-d\TH:i')) }}" required>
            </div>

            <div class="form-field">
                <label for="result_condition">Kondisi akhir aset <span class="required-mark">*</span></label>
                <select id="result_condition" name="result_condition" required>
                    <option value="good" @selected(old('result_condition', 'good') === 'good')>Baik</option>
                    <option value="fair" @selected(old('result_condition') === 'fair')>Cukup baik</option>
                    <option value="damaged" @selected(old('result_condition') === 'damaged')>Masih rusak</option>
                </select>
            </div>

            <div class="form-field">
                <label for="vendor_name">Vendor atau teknisi eksternal</label>
                <input id="vendor_name" name="vendor_name" type="text" maxlength="180" value="{{ old('vendor_name', $record->vendor_name) }}">
            </div>

            <div class="form-field">
                <label for="cost">Biaya final <span class="required-mark">*</span></label>
                <input id="cost" name="cost" type="number" min="0" step="0.01" value="{{ old('cost', $record->cost) }}" required>
                <small>Isi 0 jika pemeliharaan tidak menimbulkan biaya.</small>
            </div>

            <div class="form-field form-field-full">
                <label for="action_taken">Tindakan perbaikan <span class="required-mark">*</span></label>
                <textarea id="action_taken" name="action_taken" rows="7" maxlength="5000" required placeholder="Jelaskan pemeriksaan, penggantian komponen, pembersihan, pengujian, dan hasil akhirnya.">{{ old('action_taken', $record->action_taken) }}</textarea>
            </div>
        </div>

        <div class="inline-notice">
            Jika kondisi akhir masih rusak, status aset menjadi <strong>damaged</strong>. Jika baik atau cukup baik, aset nonbuku menjadi tersedia. Eksemplar buku hanya menjadi tersedia jika katalog dan raknya sudah lengkap.
        </div>

        <div class="form-actions">
            <a href="{{ route('inventory.maintenance-records.show', $record) }}" class="button-secondary">Batal</a>
            <button type="submit" class="button-primary" onclick="return confirm('Selesaikan pemeliharaan dan perbarui kondisi aset?')">Selesaikan pemeliharaan</button>
        </div>
    </form>
@endsection
