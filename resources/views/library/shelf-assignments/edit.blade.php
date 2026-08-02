@extends('layouts.app')

@section('title', 'Atur Rak Eksemplar')
@section('page-title', 'Atur Rak Eksemplar Buku')

@section('content')
    @php
        $detail = $asset->item?->bookDetail;
        $catalogStatus = $detail?->completion_status ?? 'incomplete';
    @endphp

    <div class="detail-heading">
        <div>
            <p class="eyebrow">{{ $asset->asset_code }}</p>
            <h2>{{ $asset->item?->item_name ?? 'Eksemplar buku' }}</h2>
            <div class="detail-badges">
                <span class="badge {{ $catalogStatus === 'incomplete' ? 'badge-warning' : 'badge-success' }}">Katalog {{ $catalogStatuses[$catalogStatus] ?? $catalogStatus }}</span>
                <span class="badge {{ $asset->asset_status === 'available' ? 'badge-success' : ($asset->asset_status === 'unprocessed' ? 'badge-warning' : 'badge-neutral') }}">{{ $assetStatuses[$asset->asset_status] ?? $asset->asset_status }}</span>
                <span class="badge badge-neutral">Kondisi {{ str($asset->condition_status)->replace('_', ' ')->title() }}</span>
            </div>
        </div>
        <div class="detail-actions">
            <a href="{{ route('library.shelf-assignments.index') }}" class="button-secondary">Kembali</a>
            <a href="{{ route('library.books.show', $asset->item_id) }}" class="button-secondary">Detail buku</a>
        </div>
    </div>

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

    <div class="detail-grid detail-grid-library">
        <section class="panel detail-card">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Identitas unit fisik</p>
                    <h2>Informasi Eksemplar</h2>
                </div>
            </div>
            <dl class="definition-list definition-list-wide">
                <div><dt>Kode aset</dt><dd>{{ $asset->asset_code }}</dd></div>
                <div><dt>Barcode</dt><dd>{{ $asset->barcode }}</dd></div>
                <div><dt>Kategori</dt><dd>{{ $asset->item?->category?->category_name ?? '-' }}</dd></div>
                <div><dt>ISBN</dt><dd>{{ $detail?->isbn_13 ?? $detail?->isbn_10 ?? '-' }}</dd></div>
                <div><dt>Klasifikasi</dt><dd>{{ $detail?->classification_code ?? '-' }}</dd></div>
                <div><dt>Nomor panggil</dt><dd>{{ $detail?->call_number ?? '-' }}</dd></div>
                <div class="definition-full"><dt>Penulis</dt><dd>{{ $asset->item?->authors?->pluck('author_name')->join(', ') ?: '-' }}</dd></div>
            </dl>
        </section>

        <section class="panel detail-card placement-summary-card">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Penempatan sekarang</p>
                    <h2>{{ $asset->shelf?->shelf_code ?? 'Belum memiliki rak' }}</h2>
                </div>
            </div>
            <div class="placement-current">
                @if ($asset->shelf)
                    <strong>{{ $asset->shelf->shelf_name }}</strong>
                    <span>{{ $asset->shelf->location?->location_name ?? $asset->location?->location_name ?? 'Lokasi belum ditentukan' }}</span>
                @else
                    <strong>Perlu diproses</strong>
                    <span>Pilih rak aktif melalui formulir di bawah.</span>
                @endif
            </div>
        </section>
    </div>

    <section class="panel form-panel form-panel-wide">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Pengaturan rak</p>
                <h2>{{ $asset->current_shelf_id ? 'Pindahkan Eksemplar' : 'Tempatkan Eksemplar' }}</h2>
                <p class="panel-description">Rak hanya dapat diubah saat status eksemplar belum diproses atau tersedia.</p>
            </div>
        </div>

        @if ($canChangeShelf)
            <form method="POST" action="{{ route('library.shelf-assignments.update', $asset) }}" class="data-form">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="form-field form-field-full">
                        <label for="shelf_id">Rak tujuan <span aria-hidden="true">*</span></label>
                        <select id="shelf_id" name="shelf_id" required>
                            <option value="">Pilih rak aktif</option>
                            @foreach ($shelves as $shelf)
                                @php
                                    $occupied = (int) $shelf->occupied_count;
                                    $remaining = $shelf->capacity !== null ? max(0, (int) $shelf->capacity - $occupied) : null;
                                    $isCurrent = (int) $asset->current_shelf_id === (int) $shelf->id;
                                    $isFull = $remaining !== null && $remaining <= 0 && ! $isCurrent;
                                @endphp
                                <option value="{{ $shelf->id }}" @selected((string) old('shelf_id', $asset->current_shelf_id) === (string) $shelf->id) @disabled($isFull || $shelf->status !== 'active')>
                                    {{ $shelf->shelf_code }} - {{ $shelf->shelf_name }} · {{ $shelf->location?->location_name ?? 'Lokasi belum diatur' }} · {{ $remaining !== null ? 'sisa '.$remaining : 'tanpa batas kapasitas' }}{{ $isCurrent ? ' (rak saat ini)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('shelf_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-field form-field-full">
                        <label for="notes">Catatan penempatan</label>
                        <textarea id="notes" name="notes" rows="3" maxlength="255" placeholder="Contoh: Dipindahkan mengikuti klasifikasi 005.13">{{ old('notes') }}</textarea>
                        <small>Catatan disimpan pada riwayat perpindahan rak.</small>
                        @error('notes')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>

                <div class="inline-notice">
                    <strong>Status setelah penempatan</strong>
                    <p>
                        @if (in_array($catalogStatus, ['complete', 'verified'], true) && in_array($asset->condition_status, ['good', 'fair'], true))
                            Eksemplar akan menjadi <b>tersedia</b> dan siap digunakan pada proses peminjaman.
                        @else
                            Eksemplar tetap <b>belum diproses</b> sampai katalog lengkap dan kondisi buku layak.
                        @endif
                    </p>
                </div>

                <div class="form-actions">
                    <a href="{{ route('library.shelf-assignments.index') }}" class="button-secondary">Batal</a>
                    <button type="submit" class="button-primary">Simpan penempatan</button>
                </div>
            </form>
        @else
            <div class="locked-action-message">
                <strong>Rak sedang terkunci.</strong>
                <p>Eksemplar berstatus {{ strtolower($assetStatuses[$asset->asset_status] ?? $asset->asset_status) }}. Selesaikan proses terkait sebelum memindahkan rak.</p>
            </div>
        @endif
    </section>

    @if ($asset->current_shelf_id && $canChangeShelf)
        <section class="panel remove-placement-panel">
            <div>
                <p class="eyebrow">Lepas penempatan</p>
                <h2>Hapus rak dari eksemplar</h2>
                <p>Eksemplar akan kembali berstatus belum diproses. Lokasi ruangan terakhir tetap tersimpan.</p>
            </div>
            <form method="POST" action="{{ route('library.shelf-assignments.remove', $asset) }}" class="remove-placement-form" onsubmit="return confirm('Lepas penempatan rak dari eksemplar ini?')">
                @csrf
                @method('DELETE')
                <input name="remove_notes" type="text" maxlength="255" value="{{ old('remove_notes') }}" placeholder="Alasan pelepasan, opsional">
                <button type="submit" class="button-danger">Lepas rak</button>
            </form>
        </section>
    @endif

    <section class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Audit penempatan</p>
                <h2>Riwayat Rak Terakhir</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Waktu</th><th>Rak lama</th><th>Rak baru</th><th>Petugas</th><th>Catatan</th></tr>
                </thead>
                <tbody>
                    @forelse ($history as $entry)
                        <tr>
                            <td>{{ $entry->changed_at?->format('d-m-Y H:i') ?? '-' }}</td>
                            <td>{{ $entry->oldShelf?->shelf_code ?? 'Tanpa rak' }}</td>
                            <td>{{ $entry->newShelf?->shelf_code ?? 'Tanpa rak' }}</td>
                            <td>{{ $entry->changedBy?->full_name ?? 'Sistem' }}</td>
                            <td>{{ $entry->notes ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Belum ada riwayat perpindahan rak.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
