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

<div class="form-section">
    <div class="form-section-heading">
        <span>1</span>
        <div>
            <h3>Informasi barang</h3>
            <p>Data umum yang dipakai oleh inventaris dan perpustakaan.</p>
        </div>
    </div>

    <div class="form-grid">
        <div class="form-field">
            <label for="item_code">Kode barang <span>*</span></label>
            <input
                id="item_code"
                name="item_code"
                type="text"
                maxlength="60"
                value="{{ old('item_code') }}"
                placeholder="Contoh: BK-0001"
                autocomplete="off"
                required
            >
            <small>Kode menjadi dasar pembuatan kode setiap aset atau eksemplar.</small>
        </div>

        <div class="form-field">
            <label for="item_name">Nama barang atau judul buku <span>*</span></label>
            <input
                id="item_name"
                name="item_name"
                type="text"
                maxlength="220"
                value="{{ old('item_name') }}"
                placeholder="Contoh: Dasar Pemrograman Web"
                required
            >
        </div>

        <div class="form-field">
            <label for="item_type">Jenis barang <span>*</span></label>
            <select id="item_type" name="item_type" required>
                <option value="">Pilih jenis barang</option>
                @foreach ($itemTypes as $value => $label)
                    <option value="{{ $value }}" @selected(old('item_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <small>Barang dengan jenis Buku otomatis masuk ke modul perpustakaan.</small>
        </div>

        <div class="form-field">
            <label for="tracking_type">Metode pencatatan <span>*</span></label>
            <select id="tracking_type" name="tracking_type" required>
                @foreach ($trackingTypes as $value => $label)
                    <option value="{{ $value }}" @selected(old('tracking_type', 'asset') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <small id="tracking-help">Per aset digunakan jika setiap unit mempunyai kode inventaris sendiri.</small>
        </div>

        <div class="form-field">
            <label for="category_id">Kategori</label>
            <select id="category_id" name="category_id">
                <option value="">Tanpa kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>
                        {{ $category->category_code }} - {{ $category->category_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-field">
            <label for="unit_id">Satuan <span>*</span></label>
            <select id="unit_id" name="unit_id" required>
                <option value="">Pilih satuan</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}" @selected((string) old('unit_id') === (string) $unit->id)>
                        {{ $unit->unit_code }} - {{ $unit->unit_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-field">
            <label for="minimum_stock">Stok minimum <span>*</span></label>
            <input
                id="minimum_stock"
                name="minimum_stock"
                type="number"
                min="0"
                step="0.01"
                value="{{ old('minimum_stock', 0) }}"
                required
            >
            <small>Digunakan untuk peringatan saat stok berada di bawah batas.</small>
        </div>

        <div class="form-field form-field-full">
            <label for="description">Deskripsi</label>
            <textarea id="description" name="description" rows="4" placeholder="Keterangan singkat barang">{{ old('description') }}</textarea>
        </div>
    </div>
</div>

<div class="form-section">
    <div class="form-section-heading">
        <span>2</span>
        <div>
            <h3>Stok awal dan pengadaan</h3>
            <p>Sistem membuat aset atau saldo stok secara otomatis setelah data disimpan.</p>
        </div>
    </div>

    <div class="form-grid">
        <div class="form-field">
            <label for="quantity">Jumlah awal <span>*</span></label>
            <input
                id="quantity"
                name="quantity"
                type="number"
                min="1"
                step="1"
                value="{{ old('quantity', 1) }}"
                required
            >
            <small id="quantity-help">Masukkan jumlah unit fisik. Setiap unit akan memperoleh kode aset.</small>
        </div>

        <div class="form-field">
            <label for="location_id">Lokasi awal <span>*</span></label>
            <select id="location_id" name="location_id" required>
                <option value="">Pilih lokasi</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>
                        {{ $location->location_code }} - {{ $location->location_name }}
                    </option>
                @endforeach
            </select>
            <small>Rak buku diatur kemudian melalui modul perpustakaan.</small>
        </div>

        <div class="form-field">
            <label for="acquisition_date">Tanggal perolehan</label>
            <input
                id="acquisition_date"
                name="acquisition_date"
                type="date"
                value="{{ old('acquisition_date', now()->format('Y-m-d')) }}"
            >
        </div>

        <div class="form-field">
            <label for="acquisition_source">Sumber perolehan <span>*</span></label>
            <select id="acquisition_source" name="acquisition_source" required>
                @foreach ($acquisitionSources as $value => $label)
                    <option value="{{ $value }}" @selected(old('acquisition_source', 'purchase') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-field">
            <label for="supplier_id">Supplier</label>
            <select id="supplier_id" name="supplier_id">
                <option value="">Tanpa supplier</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>
                        {{ $supplier->supplier_code }} - {{ $supplier->supplier_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-field">
            <label for="acquisition_price">Harga per unit</label>
            <input
                id="acquisition_price"
                name="acquisition_price"
                type="number"
                min="0"
                step="0.01"
                value="{{ old('acquisition_price') }}"
                placeholder="0"
            >
        </div>
    </div>
</div>

<div class="inline-notice" id="book-notice" hidden>
    <strong>Alur buku otomatis aktif.</strong>
    <p>Data katalog awal dibuat otomatis. Semua eksemplar berstatus belum diproses sampai detail buku dan rak dilengkapi oleh petugas perpustakaan.</p>
</div>

<div class="form-actions">
    <a href="{{ route('inventory.items.index') }}" class="button-secondary">Batal</a>
    <button type="submit" class="button-primary">Simpan barang dan stok awal</button>
</div>
