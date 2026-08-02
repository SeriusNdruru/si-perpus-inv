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
        <label for="location_code">Kode lokasi <span>*</span></label>
        <input
            id="location_code"
            name="location_code"
            type="text"
            maxlength="50"
            value="{{ old('location_code', $location?->location_code) }}"
            placeholder="Contoh: GDG-UTAMA"
            required
        >
        <small>Spasi otomatis diubah menjadi tanda hubung. Kode disimpan dengan huruf kapital.</small>
    </div>

    <div class="form-field">
        <label for="location_name">Nama lokasi <span>*</span></label>
        <input
            id="location_name"
            name="location_name"
            type="text"
            maxlength="150"
            value="{{ old('location_name', $location?->location_name) }}"
            placeholder="Contoh: Gedung Utama"
            required
        >
    </div>

    <div class="form-field">
        <label for="location_type">Jenis lokasi <span>*</span></label>
        <select id="location_type" name="location_type" required>
            @foreach ($typeLabels as $typeValue => $typeLabel)
                <option
                    value="{{ $typeValue }}"
                    @selected(old('location_type', $location?->location_type ?? 'room') === $typeValue)
                >
                    {{ $typeLabel }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-field">
        <label for="parent_id">Lokasi induk</label>
        <select id="parent_id" name="parent_id">
            <option value="">Tidak memiliki induk</option>
            @foreach ($parentLocations as $parentLocation)
                <option
                    value="{{ $parentLocation->id }}"
                    @selected((string) old('parent_id', $location?->parent_id) === (string) $parentLocation->id)
                >
                    {{ $parentLocation->option_label }}
                </option>
            @endforeach
        </select>
        <small>Contoh struktur: Gedung Utama → Lantai 1 → Ruang Perpustakaan → Lemari.</small>
    </div>

    <div class="form-field form-field-full">
        <label for="description">Deskripsi</label>
        <textarea
            id="description"
            name="description"
            rows="4"
            maxlength="255"
            placeholder="Keterangan singkat mengenai lokasi"
        >{{ old('description', $location?->description) }}</textarea>
    </div>

    <div class="form-field">
        <label for="status">Status <span>*</span></label>
        <select id="status" name="status" required>
            <option value="active" @selected(old('status', $location?->status ?? 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected(old('status', $location?->status ?? 'active') === 'inactive')>Tidak aktif</option>
        </select>
        <small>Lokasi aktif dapat dipilih saat mencatat aset dan membuat rak perpustakaan.</small>
    </div>
</div>

<div class="form-actions">
    <a href="{{ route('locations.index') }}" class="button-secondary">Batal</a>
    <button type="submit" class="button-primary">{{ $submitLabel }}</button>
</div>
