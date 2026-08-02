@if ($errors->any())
    <div class="alert alert-danger form-errors">
        <strong>Data belum dapat disimpan.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="form-grid">
    <div class="form-field">
        <label for="unit_code">Kode satuan <span>*</span></label>
        <input
            id="unit_code"
            name="unit_code"
            type="text"
            maxlength="30"
            value="{{ old('unit_code', $unit?->unit_code) }}"
            placeholder="Contoh: PCS"
            required
        >
        <small>Spasi otomatis diubah menjadi tanda hubung. Kode disimpan dengan huruf kapital.</small>
    </div>

    <div class="form-field">
        <label for="unit_name">Nama satuan <span>*</span></label>
        <input
            id="unit_name"
            name="unit_name"
            type="text"
            maxlength="100"
            value="{{ old('unit_name', $unit?->unit_name) }}"
            placeholder="Contoh: Pcs"
            required
        >
    </div>

    <div class="form-field form-field-full">
        <label for="description">Deskripsi</label>
        <textarea
            id="description"
            name="description"
            rows="4"
            maxlength="255"
            placeholder="Keterangan penggunaan satuan"
        >{{ old('description', $unit?->description) }}</textarea>
    </div>

    <div class="form-field">
        <label for="status">Status <span>*</span></label>
        <select id="status" name="status" required>
            <option value="active" @selected(old('status', $unit?->status ?? 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected(old('status', $unit?->status ?? 'active') === 'inactive')>Tidak aktif</option>
        </select>
        <small>Satuan aktif dapat dipilih saat menambahkan barang.</small>
    </div>
</div>

<div class="form-actions">
    <a href="{{ route('units.index') }}" class="button-secondary">Batal</a>
    <button type="submit" class="button-primary">{{ $submitLabel }}</button>
</div>
