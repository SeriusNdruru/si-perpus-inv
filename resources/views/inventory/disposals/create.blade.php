@extends('layouts.app')

@section('title', 'Buat Usulan Penghapusan')
@section('page-title', 'Buat Usulan Penghapusan Aset')

@section('content')
    <div class="detail-heading">
        <div>
            <p class="eyebrow">Pengajuan awal</p>
            <h2>Pilih aset yang akan dihapuskan</h2>
            <p class="panel-description">Usulan tidak langsung mengubah status aset. Super Admin harus memberikan persetujuan sebelum penghapusan dilaksanakan.</p>
        </div>
        <a href="{{ route('inventory.disposals.index') }}" class="button-secondary">Kembali</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger content-alert">
            <strong>Data belum dapat disimpan.</strong>
            <ul class="error-list">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('inventory.disposals.store') }}" class="panel">
        @csrf
        <div class="panel-header">
            <div>
                <p class="eyebrow">Informasi usulan</p>
                <h2>Aset dan alasan penghapusan</h2>
            </div>
        </div>

        <div class="form-grid form-grid-two panel-body-form">
            <div class="form-field form-field-full">
                <label for="asset_id">Aset <span class="required-mark">*</span></label>
                <select id="asset_id" name="asset_id" required>
                    <option value="">Pilih aset</option>
                    @foreach ($assets as $asset)
                        <option value="{{ $asset->id }}" @selected((int) old('asset_id', $selectedAssetId) === $asset->id)>
                            {{ $asset->asset_code }} · {{ $asset->item?->item_name }} · {{ $asset->location?->location_name ?? 'Tanpa lokasi' }} · {{ ucfirst($asset->condition_status) }} · {{ ucfirst($asset->asset_status) }}
                        </option>
                    @endforeach
                </select>
                <small>Aset yang sedang dipinjam, direservasi, dipelihara, atau sudah dihapuskan tidak ditampilkan.</small>
            </div>

            <div class="form-field">
                <label for="proposed_at">Tanggal dan waktu usulan <span class="required-mark">*</span></label>
                <input id="proposed_at" name="proposed_at" type="datetime-local" value="{{ old('proposed_at', now()->format('Y-m-d\TH:i')) }}" required>
            </div>

            <div class="form-field form-field-full">
                <label for="reason">Alasan penghapusan <span class="required-mark">*</span></label>
                <textarea id="reason" name="reason" rows="6" maxlength="5000" required placeholder="Jelaskan kondisi, umur ekonomis, hasil pemeriksaan, kerusakan, kehilangan, atau alasan administratif lainnya.">{{ old('reason') }}</textarea>
            </div>

            <div class="form-field form-field-full">
                <label for="notes">Catatan pendukung</label>
                <textarea id="notes" name="notes" rows="4" maxlength="3000" placeholder="Nomor berita acara, hasil rapat, atau informasi tambahan.">{{ old('notes') }}</textarea>
            </div>
        </div>

        <div class="inline-notice">
            Pastikan data aset benar. Setiap aset hanya memiliki satu riwayat penghapusan. Usulan yang ditolak dapat diperbaiki dan diajukan kembali.
        </div>

        <div class="form-actions">
            <a href="{{ route('inventory.disposals.index') }}" class="button-secondary">Batal</a>
            <button type="submit" class="button-primary">Kirim usulan</button>
        </div>
    </form>
@endsection
