@extends('layouts.app')

@section('title', 'Edit Usulan Penghapusan')
@section('page-title', 'Edit Usulan Penghapusan')

@section('content')
    <div class="detail-heading">
        <div>
            <p class="eyebrow">{{ $disposal->disposal_code }}</p>
            <h2>{{ $disposal->asset?->item?->item_name }}</h2>
            <p class="panel-description">Aset {{ $disposal->asset?->asset_code }} · {{ $disposal->statusLabel() }}</p>
        </div>
        <a href="{{ route('inventory.disposals.show', $disposal) }}" class="button-secondary">Kembali</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger content-alert">
            <strong>Data belum dapat disimpan.</strong>
            <ul class="error-list">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('inventory.disposals.update', $disposal) }}" class="panel workflow-form">
        @csrf
        @method('PUT')

        <div class="panel-header">
            <div>
                <p class="eyebrow">Perbaikan usulan</p>
                <h2>Alasan dan catatan</h2>
            </div>
        </div>

        <div class="form-grid form-grid-two panel-body-form">
            <div class="form-field">
                <label for="proposed_at">Tanggal dan waktu usulan <span class="required-mark">*</span></label>
                <input id="proposed_at" name="proposed_at" type="datetime-local" value="{{ old('proposed_at', $disposal->proposed_at?->format('Y-m-d\TH:i')) }}" required>
            </div>

            <div class="form-field form-field-full">
                <label for="reason">Alasan penghapusan <span class="required-mark">*</span></label>
                <textarea id="reason" name="reason" rows="7" maxlength="5000" required>{{ old('reason', $disposal->reason) }}</textarea>
            </div>

            <div class="form-field form-field-full">
                <label for="notes">Catatan pendukung</label>
                <textarea id="notes" name="notes" rows="5" maxlength="3000">{{ old('notes', $disposal->notes) }}</textarea>
            </div>
        </div>

        @if ($disposal->status === 'rejected')
            <div class="inline-notice">Saat disimpan, status usulan kembali menjadi menunggu persetujuan.</div>
        @endif

        <div class="form-actions">
            <a href="{{ route('inventory.disposals.show', $disposal) }}" class="button-secondary">Batal</a>
            <button type="submit" class="button-primary">{{ $disposal->status === 'rejected' ? 'Ajukan kembali' : 'Simpan perubahan' }}</button>
        </div>
    </form>
@endsection
