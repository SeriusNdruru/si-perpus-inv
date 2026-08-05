@extends('layouts.app')

@section('title', 'Edit Pemeliharaan')
@section('page-title', 'Edit Data Pemeliharaan')

@section('content')
    <div class="detail-heading">
        <div>
            <p class="eyebrow">{{ $record->maintenance_code }}</p>
            <h2>{{ $record->asset?->item?->item_name }}</h2>
            <p class="panel-description">Aset {{ $record->asset?->asset_code }} · Status {{ $record->statusLabel() }}</p>
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

    <form method="POST" action="{{ route('inventory.maintenance-records.update', $record) }}" class="panel workflow-form">
        @csrf
        @method('PUT')
        <div class="panel-header">
            <div>
                <p class="eyebrow">Pembaruan proses</p>
                <h2>Informasi pemeliharaan</h2>
            </div>
        </div>

        <div class="form-grid form-grid-two panel-body-form">
            <div class="form-field">
                <label for="reported_at">Tanggal dan waktu laporan <span class="required-mark">*</span></label>
                <input id="reported_at" name="reported_at" type="datetime-local" value="{{ old('reported_at', $record->reported_at?->format('Y-m-d\TH:i')) }}" required>
            </div>

            <div class="form-field">
                <label for="vendor_name">Vendor atau teknisi eksternal</label>
                <input id="vendor_name" name="vendor_name" type="text" maxlength="180" value="{{ old('vendor_name', $record->vendor_name) }}">
            </div>

            <div class="form-field form-field-full">
                <label for="issue_description">Keluhan atau kerusakan <span class="required-mark">*</span></label>
                <textarea id="issue_description" name="issue_description" rows="5" maxlength="5000" required>{{ old('issue_description', $record->issue_description) }}</textarea>
            </div>

            <div class="form-field form-field-full">
                <label for="action_taken">Tindakan sementara atau progres</label>
                <textarea id="action_taken" name="action_taken" rows="5" maxlength="5000">{{ old('action_taken', $record->action_taken) }}</textarea>
            </div>

            <div class="form-field">
                <label for="cost">Biaya berjalan</label>
                <input id="cost" name="cost" type="number" min="0" step="0.01" value="{{ old('cost', $record->cost) }}">
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('inventory.maintenance-records.show', $record) }}" class="button-secondary">Batal</a>
            <button type="submit" class="button-primary">Simpan perubahan</button>
        </div>
    </form>
@endsection
