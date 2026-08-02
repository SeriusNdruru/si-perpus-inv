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
        <label for="shelf_code">Kode rak <span>*</span></label>
        <input
            id="shelf_code"
            name="shelf_code"
            type="text"
            maxlength="50"
            value="{{ old('shelf_code', $shelf?->shelf_code) }}"
            placeholder="Contoh: RK-TI-01"
            required
        >
        <small>Spasi otomatis diubah menjadi tanda hubung. Kode disimpan dengan huruf kapital.</small>
    </div>

    <div class="form-field">
        <label for="shelf_name">Nama rak <span>*</span></label>
        <input
            id="shelf_name"
            name="shelf_name"
            type="text"
            maxlength="150"
            value="{{ old('shelf_name', $shelf?->shelf_name) }}"
            placeholder="Contoh: Rak Teknologi Informasi"
            required
        >
    </div>

    <div class="form-field">
        <label for="location_id">Lokasi rak</label>
        <select id="location_id" name="location_id">
            <option value="">Belum ditentukan</option>
            @foreach ($locations as $location)
                <option
                    value="{{ $location->id }}"
                    @selected((string) old('location_id', $shelf?->location_id) === (string) $location->id)
                >
                    {{ $location->location_code }} - {{ $location->location_name }}
                    {{ $location->status === 'inactive' ? '(tidak aktif)' : '' }}
                </option>
            @endforeach
        </select>
        <small>Lokasi dibuat oleh Admin Inventaris melalui menu Master Lokasi.</small>
    </div>

    <div class="form-field">
        <label for="classification_range">Rentang klasifikasi</label>
        <input
            id="classification_range"
            name="classification_range"
            type="text"
            maxlength="100"
            value="{{ old('classification_range', $shelf?->classification_range) }}"
            placeholder="Contoh: 000-099 atau 005.1-005.9"
        >
        <small>Gunakan rentang yang sesuai dengan sistem klasifikasi perpustakaan Anda.</small>
    </div>

    <div class="form-field">
        <label for="capacity">Kapasitas eksemplar</label>
        <input
            id="capacity"
            name="capacity"
            type="number"
            min="1"
            max="100000"
            value="{{ old('capacity', $shelf?->capacity) }}"
            placeholder="Contoh: 100"
        >
        <small>Kosongkan jika kapasitas belum ditetapkan.</small>
    </div>

    <div class="form-field">
        <label for="status">Status <span>*</span></label>
        <select id="status" name="status" required>
            <option value="active" @selected(old('status', $shelf?->status ?? 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected(old('status', $shelf?->status ?? 'active') === 'inactive')>Tidak aktif</option>
        </select>
        <small>Hanya rak aktif yang dapat dipilih untuk penempatan buku.</small>
    </div>

    <div class="form-field form-field-full">
        <label for="description">Deskripsi</label>
        <textarea
            id="description"
            name="description"
            rows="4"
            maxlength="255"
            placeholder="Keterangan singkat mengenai rak"
        >{{ old('description', $shelf?->description) }}</textarea>
    </div>
</div>

@if (isset($occupiedCount) && $occupiedCount > 0)
    <div class="inline-notice">
        <strong>Rak sedang digunakan</strong>
        <p>Rak ini ditempati {{ number_format($occupiedCount) }} eksemplar. Kapasitas tidak boleh dikurangi di bawah jumlah tersebut dan rak belum dapat dinonaktifkan.</p>
    </div>
@endif

<div class="form-actions">
    <a href="{{ route('library.shelves.index') }}" class="button-secondary">Batal</a>
    <button type="submit" class="button-primary">{{ $submitLabel }}</button>
</div>
