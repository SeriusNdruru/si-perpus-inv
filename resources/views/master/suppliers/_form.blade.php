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
        <label for="supplier_code">Kode supplier <span>*</span></label>
        <input
            id="supplier_code"
            name="supplier_code"
            type="text"
            maxlength="50"
            value="{{ old('supplier_code', $supplier?->supplier_code) }}"
            placeholder="Contoh: SUP-001"
            required
        >
        <small>Spasi otomatis diubah menjadi tanda hubung. Kode disimpan dengan huruf kapital.</small>
    </div>

    <div class="form-field">
        <label for="supplier_name">Nama supplier <span>*</span></label>
        <input
            id="supplier_name"
            name="supplier_name"
            type="text"
            maxlength="150"
            value="{{ old('supplier_name', $supplier?->supplier_name) }}"
            placeholder="Contoh: PT Sumber Ilmu"
            required
        >
    </div>

    <div class="form-field">
        <label for="contact_person">Nama kontak</label>
        <input
            id="contact_person"
            name="contact_person"
            type="text"
            maxlength="150"
            value="{{ old('contact_person', $supplier?->contact_person) }}"
            placeholder="Nama orang yang dapat dihubungi"
        >
    </div>

    <div class="form-field">
        <label for="phone">Nomor telepon</label>
        <input
            id="phone"
            name="phone"
            type="text"
            maxlength="30"
            value="{{ old('phone', $supplier?->phone) }}"
            placeholder="Contoh: 0812-3456-7890"
        >
    </div>

    <div class="form-field">
        <label for="email">Email</label>
        <input
            id="email"
            name="email"
            type="email"
            maxlength="150"
            value="{{ old('email', $supplier?->email) }}"
            placeholder="Contoh: supplier@example.com"
        >
    </div>

    <div class="form-field">
        <label for="status">Status <span>*</span></label>
        <select id="status" name="status" required>
            <option value="active" @selected(old('status', $supplier?->status ?? 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected(old('status', $supplier?->status ?? 'active') === 'inactive')>Tidak aktif</option>
        </select>
        <small>Supplier aktif dapat dipilih saat mencatat pengadaan aset.</small>
    </div>

    <div class="form-field form-field-full">
        <label for="address">Alamat</label>
        <textarea
            id="address"
            name="address"
            rows="5"
            maxlength="2000"
            placeholder="Alamat lengkap supplier"
        >{{ old('address', $supplier?->address) }}</textarea>
    </div>
</div>

<div class="form-actions">
    <a href="{{ route('suppliers.index') }}" class="button-secondary">Batal</a>
    <button type="submit" class="button-primary">{{ $submitLabel }}</button>
</div>
