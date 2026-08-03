@extends('layouts.app')

@section('title', 'Edit Barang')
@section('page-title', 'Edit Barang Inventaris')

@section('content')
    <section class="panel form-panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">{{ $item->item_code }}</p>
                <h2>Perbarui Data Barang</h2>
            </div>
            <a href="{{ route('inventory.items.show', $item) }}" class="button-secondary">Lihat detail</a>
        </div>

        <form method="POST" action="{{ route('inventory.items.update', $item) }}" enctype="multipart/form-data" class="data-form">
            @csrf
            @method('PUT')

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

            <div class="locked-data-grid">
                <div>
                    <span>Kode barang</span>
                    <strong>{{ $item->item_code }}</strong>
                </div>
                <div>
                    <span>Jenis</span>
                    <strong>{{ $itemTypes[$item->item_type] ?? $item->item_type }}</strong>
                </div>
                <div>
                    <span>Pencatatan</span>
                    <strong>{{ $trackingTypes[$item->tracking_type] ?? $item->tracking_type }}</strong>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-field form-field-full">
                    <label for="item_name">Nama barang atau judul buku <span>*</span></label>
                    <input id="item_name" name="item_name" type="text" maxlength="220" value="{{ old('item_name', $item->item_name) }}" required>
                </div>

                <div class="form-field">
                    <label for="category_id">Kategori</label>
                    <select id="category_id" name="category_id">
                        <option value="">Tanpa kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id', $item->category_id) === (string) $category->id)>
                                {{ $category->category_code }} - {{ $category->category_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-field">
                    <label for="unit_id">Satuan <span>*</span></label>
                    <select id="unit_id" name="unit_id" required>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}" @selected((string) old('unit_id', $item->unit_id) === (string) $unit->id)>
                                {{ $unit->unit_code }} - {{ $unit->unit_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-field">
                    <label for="minimum_stock">Stok minimum <span>*</span></label>
                    <input id="minimum_stock" name="minimum_stock" type="number" min="0" step="0.01" value="{{ old('minimum_stock', $item->minimum_stock) }}" required>
                </div>

                <div class="form-field">
                    <label>Status</label>
                    <input type="hidden" name="status" value="{{ old('status', $item->status) }}">
                    <div>
                        <span class="badge {{ $item->status === 'active' ? 'badge-success' : 'badge-muted' }}">
                            {{ $item->status === 'active' ? 'Aktif' : 'Dihapus' }}
                        </span>
                    </div>
                    <small>Gunakan tombol Hapus pada Daftar Barang atau tombol Pulihkan pada Daftar Hapus untuk mengubah status.</small>
                </div>

                @php($currentImagePath = $item->image_path ?: $item->bookDetail?->cover_path)
                <div class="form-field form-field-full">
                    <label for="item_image">{{ $currentImagePath ? 'Ganti foto barang atau cover buku' : 'Foto barang atau cover buku' }} @if (! $currentImagePath)<span>*</span>@endif</label>
                    <div class="item-photo-upload">
                        <div class="item-photo-preview" data-image-preview>
                            @if ($currentImagePath)
                                <img src="{{ asset('storage/'.$currentImagePath) }}" data-image-element alt="Foto {{ $item->item_name }}">
                                <span data-image-placeholder hidden>Pratinjau foto</span>
                            @else
                                <span data-image-placeholder>Pratinjau foto</span>
                                <img data-image-element alt="Pratinjau foto barang" hidden>
                            @endif
                        </div>
                        <div>
                            <input
                                id="item_image"
                                name="item_image"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                data-image-input
                                @required(! $currentImagePath)
                            >
                            <small>{{ $currentImagePath ? 'Kosongkan bila foto tidak diganti.' : 'Foto wajib ditambahkan sebelum data dapat disimpan.' }} Format JPG, JPEG, PNG, atau WEBP. Maksimal 3 MB.</small>
                        </div>
                    </div>
                </div>

                <div class="form-field form-field-full">
                    <label for="description">Deskripsi</label>
                    <textarea id="description" name="description" rows="5">{{ old('description', $item->description) }}</textarea>
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('inventory.items.show', $item) }}" class="button-secondary">Batal</a>
                <button type="submit" class="button-primary">Simpan perubahan</button>
            </div>
        </form>
    </section>
    <script src="{{ asset('js/item-image-preview.js') }}" defer></script>
@endsection
