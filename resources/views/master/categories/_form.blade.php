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
        <label for="category_code">Kode kategori <span>*</span></label>
        <input
            id="category_code"
            name="category_code"
            type="text"
            maxlength="50"
            value="{{ old('category_code', $category?->category_code) }}"
            placeholder="Contoh: BUKU"
            required
        >
        <small>Spasi otomatis diubah menjadi tanda hubung. Kode disimpan dengan huruf kapital.</small>
    </div>

    <div class="form-field">
        <label for="category_name">Nama kategori <span>*</span></label>
        <input
            id="category_name"
            name="category_name"
            type="text"
            maxlength="150"
            value="{{ old('category_name', $category?->category_name) }}"
            placeholder="Contoh: Buku"
            required
        >
    </div>

    <div class="form-field">
        <label for="parent_id">Kategori induk</label>
        <select id="parent_id" name="parent_id">
            <option value="">Tidak memiliki induk</option>
            @foreach ($parentCategories as $parentCategory)
                <option
                    value="{{ $parentCategory->id }}"
                    @selected((string) old('parent_id', $category?->parent_id) === (string) $parentCategory->id)
                >
                    {{ $parentCategory->category_code }} - {{ $parentCategory->category_name }}
                </option>
            @endforeach
        </select>
        <small>Gunakan kategori induk untuk membuat struktur kategori bertingkat.</small>
    </div>

    <div class="form-field">
        <label for="scope">Cakupan <span>*</span></label>
        <select id="scope" name="scope" required>
            <option value="inventory" @selected(old('scope', $category?->scope ?? 'both') === 'inventory')>Inventaris</option>
            <option value="library" @selected(old('scope', $category?->scope ?? 'both') === 'library')>Perpustakaan</option>
            <option value="both" @selected(old('scope', $category?->scope ?? 'both') === 'both')>Bersama</option>
        </select>
    </div>

    <div class="form-field form-field-full">
        <label for="description">Deskripsi</label>
        <textarea
            id="description"
            name="description"
            rows="4"
            maxlength="255"
            placeholder="Keterangan singkat mengenai kategori"
        >{{ old('description', $category?->description) }}</textarea>
    </div>

    <div class="form-field">
        <label for="status">Status <span>*</span></label>
        <select id="status" name="status" required>
            <option value="active" @selected(old('status', $category?->status ?? 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected(old('status', $category?->status ?? 'active') === 'inactive')>Tidak aktif</option>
        </select>
    </div>
</div>

<div class="form-actions">
    <a href="{{ route('categories.index') }}" class="button-secondary">Batal</a>
    <button type="submit" class="button-primary">{{ $submitLabel }}</button>
</div>
