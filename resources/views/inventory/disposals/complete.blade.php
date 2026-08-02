@extends('layouts.app')

@section('title', 'Laksanakan Penghapusan')
@section('page-title', 'Pelaksanaan Penghapusan Aset')

@section('content')
    <div class="detail-heading">
        <div>
            <p class="eyebrow">{{ $disposal->disposal_code }}</p>
            <h2>{{ $disposal->asset?->item?->item_name }}</h2>
            <p class="panel-description">Aset {{ $disposal->asset?->asset_code }} telah disetujui dan siap diproses menjadi disposed.</p>
        </div>
        <a href="{{ route('inventory.disposals.show', $disposal) }}" class="button-secondary">Kembali</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger content-alert">
            <strong>Penghapusan belum dapat diselesaikan.</strong>
            <ul class="error-list">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('inventory.disposals.complete', $disposal) }}" class="panel">
        @csrf
        @method('PATCH')

        <div class="panel-header">
            <div>
                <p class="eyebrow">Pelaksanaan final</p>
                <h2>Metode dan tanggal penghapusan</h2>
            </div>
        </div>

        <div class="form-grid form-grid-two panel-body-form">
            <div class="form-field">
                <label for="disposed_at">Tanggal dan waktu pelaksanaan <span class="required-mark">*</span></label>
                <input id="disposed_at" name="disposed_at" type="datetime-local" value="{{ old('disposed_at', now()->format('Y-m-d\TH:i')) }}" required>
            </div>

            <div class="form-field">
                <label for="disposal_method">Metode penghapusan <span class="required-mark">*</span></label>
                <select id="disposal_method" name="disposal_method" required>
                    <option value="">Pilih metode</option>
                    @foreach ($methods as $value => $label)
                        <option value="{{ $value }}" @selected(old('disposal_method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-field form-field-full">
                <label for="completion_notes">Catatan pelaksanaan</label>
                <textarea id="completion_notes" name="completion_notes" rows="5" maxlength="3000" placeholder="Nomor berita acara, penerima aset, nilai penjualan, lokasi pemusnahan, atau informasi lain.">{{ old('completion_notes') }}</textarea>
            </div>
        </div>

        <div class="alert alert-danger content-alert">
            Tindakan ini bersifat final. Status aset akan menjadi <strong>disposed</strong>, penempatan rak akan dilepas, dan aset tidak dapat dipinjam atau digunakan kembali.
        </div>

        <div class="form-actions">
            <a href="{{ route('inventory.disposals.show', $disposal) }}" class="button-secondary">Batal</a>
            <button type="submit" class="button-danger" onclick="return confirm('Selesaikan penghapusan aset ini? Status aset akan menjadi disposed.');">Selesaikan penghapusan</button>
        </div>
    </form>
@endsection
