@extends('layouts.app')

@section('title', 'Buat Stock Opname')
@section('page-title', 'Buat Stock Opname')

@section('content')
    <div class="detail-heading">
        <div>
            <p class="eyebrow">Tahap awal pemeriksaan</p>
            <h2>Pilih lokasi inventaris</h2>
            <p class="panel-description">Sistem akan mengambil seluruh aset yang seharusnya berada di lokasi tersebut dan seluruh saldo barang berbasis jumlah.</p>
        </div>
        <a href="{{ route('inventory.stock-opnames.index') }}" class="button-secondary">Kembali</a>
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

    <form method="POST" action="{{ route('inventory.stock-opnames.store') }}" class="panel stock-opname-create-card">
        @csrf
        <div class="panel-header">
            <div>
                <p class="eyebrow">Informasi stock opname</p>
                <h2>Data pemeriksaan</h2>
            </div>
        </div>

        <div class="form-grid form-grid-two panel-body-form">
            <div class="form-field form-field-full">
                <label for="location_id">Lokasi pemeriksaan <span class="required-mark">*</span></label>
                <select id="location_id" name="location_id" required>
                    <option value="">Pilih lokasi</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected((int) old('location_id') === $location->id)>
                            {{ $location->location_code }} · {{ $location->location_name }} ({{ ucfirst($location->location_type) }})
                        </option>
                    @endforeach
                </select>
                <small>Pilih ruangan, gudang, atau lokasi tempat barang akan dihitung secara fisik.</small>
            </div>

            <div class="form-field">
                <label for="opname_date">Tanggal stock opname <span class="required-mark">*</span></label>
                <input id="opname_date" name="opname_date" type="date" value="{{ old('opname_date', today()->format('Y-m-d')) }}" required>
            </div>

            <div class="form-field form-field-full">
                <label for="notes">Catatan</label>
                <textarea id="notes" name="notes" rows="4" placeholder="Contoh: Pemeriksaan rutin semester ganjil">{{ old('notes') }}</textarea>
            </div>
        </div>

        <div class="form-note stock-opname-note">
            <strong>Data awal yang akan diambil</strong>
            <p>Aset yang sedang dipinjam, dalam pemeliharaan, sudah hilang, atau sudah dihapuskan tidak dimasukkan sebagai barang yang seharusnya berada di lokasi.</p>
        </div>

        <div class="form-actions panel-form-actions">
            <a href="{{ route('inventory.stock-opnames.index') }}" class="button-secondary">Batal</a>
            <button type="submit" class="button-primary">Buat draf stock opname</button>
        </div>
    </form>
@endsection
