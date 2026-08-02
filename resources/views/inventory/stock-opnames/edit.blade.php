@extends('layouts.app')

@section('title', 'Pemeriksaan Stock Opname')
@section('page-title', 'Pemeriksaan Fisik Stock Opname')

@section('content')
    <div class="detail-heading">
        <div>
            <p class="eyebrow">{{ $stockOpname->opname_code }}</p>
            <h2>{{ $stockOpname->location?->location_name }}</h2>
            <p class="panel-description">Isi jumlah fisik setiap baris. Untuk aset individual, gunakan 1 jika ditemukan atau 0 jika tidak ditemukan.</p>
        </div>
        <div class="detail-actions">
            <a href="{{ route('inventory.stock-opnames.show', $stockOpname) }}" class="button-secondary">Kembali ke detail</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger content-alert">
            <strong>Hasil pemeriksaan belum dapat disimpan.</strong>
            <ul class="error-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('inventory.stock-opnames.update', $stockOpname) }}" class="stock-opname-check-form">
        @csrf
        @method('PUT')

        <section class="panel">
            <div class="panel-header panel-header-wrap">
                <div>
                    <p class="eyebrow">Input hasil lapangan</p>
                    <h2>{{ number_format($lines->count()) }} baris pemeriksaan</h2>
                </div>
                <button type="submit" class="button-primary">Simpan seluruh hasil</button>
            </div>

            <div class="stock-opname-help-grid">
                <div><strong>Aset individual</strong><span>Jumlah fisik 1 berarti ditemukan, 0 berarti tidak ditemukan.</span></div>
                <div><strong>Barang kuantitas</strong><span>Masukkan jumlah hasil hitung. Sistem menentukan lebih atau kurang.</span></div>
                <div><strong>Kondisi rusak</strong><span>Pilih “Rusak” hanya jika unit ditemukan tetapi kondisinya rusak.</span></div>
            </div>

            <div class="table-wrap stock-opname-edit-table">
                <table>
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Unit atau aset</th>
                            <th>Menurut sistem</th>
                            <th class="stock-opname-input-column">Jumlah fisik</th>
                            <th class="stock-opname-finding-column">Kondisi aset</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            <tr>
                                <td>
                                    <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $line->id }}">
                                    <div class="table-primary">{{ $line->item?->item_name }}</div>
                                    <div class="table-secondary">{{ $line->item?->item_code }} · {{ $line->item?->unit?->unit_code }}</div>
                                </td>
                                <td>
                                    @if ($line->asset)
                                        <div class="table-primary">{{ $line->asset->asset_code }}</div>
                                        <div class="table-secondary">{{ $line->asset->barcode }}</div>
                                    @else
                                        <span class="badge badge-neutral">Stok kuantitas</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ number_format((float) $line->expected_quantity, 2, ',', '.') }}</strong>
                                </td>
                                <td>
                                    <input
                                        class="stock-opname-quantity-input"
                                        name="items[{{ $loop->index }}][actual_quantity]"
                                        type="number"
                                        min="0"
                                        @if ($line->asset) max="1" step="1" @else step="0.01" @endif
                                        value="{{ old('items.'.$loop->index.'.actual_quantity', (float) $line->actual_quantity) }}"
                                        required
                                    >
                                </td>
                                <td>
                                    @if ($line->asset)
                                        <select name="items[{{ $loop->index }}][finding_status]">
                                            <option value="matched" @selected(old('items.'.$loop->index.'.finding_status', $line->finding_status) !== 'damaged')>Normal</option>
                                            <option value="damaged" @selected(old('items.'.$loop->index.'.finding_status', $line->finding_status) === 'damaged')>Rusak</option>
                                        </select>
                                    @else
                                        <span class="table-secondary">Otomatis dari selisih</span>
                                    @endif
                                </td>
                                <td>
                                    <input
                                        name="items[{{ $loop->index }}][notes]"
                                        type="text"
                                        value="{{ old('items.'.$loop->index.'.notes', $line->notes) }}"
                                        placeholder="Catatan opsional"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="stock-opname-save-bar">
                <p>Semua baris harus disimpan sebelum stock opname dapat diselesaikan.</p>
                <button type="submit" class="button-primary">Simpan hasil pemeriksaan</button>
            </div>
        </section>
    </form>
@endsection
