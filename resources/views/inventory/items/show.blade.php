@extends('layouts.app')

@section('title', 'Detail Barang')
@section('page-title', 'Detail Barang Inventaris')

@section('content')
    <div class="detail-heading">
        <div>
            <p class="eyebrow">{{ $item->item_code }}</p>
            <h2>{{ $item->item_name }}</h2>
            <div class="detail-badges">
                <span class="badge badge-neutral">{{ $itemTypes[$item->item_type] ?? $item->item_type }}</span>
                <span class="badge {{ $item->status === 'active' ? 'badge-success' : 'badge-muted' }}">{{ $item->status === 'active' ? 'Aktif' : 'Tidak aktif' }}</span>
                @if ($item->item_type === 'book')
                    <span class="badge {{ $item->bookDetail?->completion_status === 'incomplete' ? 'badge-warning' : 'badge-success' }}">
                        Katalog {{ $item->bookDetail?->completion_status === 'incomplete' ? 'belum lengkap' : $item->bookDetail?->completion_status }}
                    </span>
                @endif
            </div>
        </div>
        <div class="detail-actions">
            <a href="{{ route('inventory.items.index') }}" class="button-secondary">Kembali</a>
            <a href="{{ route('inventory.items.edit', $item) }}" class="button-primary button-link">Edit barang</a>
        </div>
    </div>

    @php($itemImagePath = $item->image_path ?: $item->bookDetail?->cover_path)
    <section class="panel item-photo-detail-panel">
        <div class="panel-header">
            <h2>Foto Barang</h2>
        </div>
        <div class="item-photo-detail">
            @if ($itemImagePath)
                <button
                    type="button"
                    class="item-photo-detail-button"
                    data-photo-preview
                    data-photo-src="{{ asset('storage/'.$itemImagePath) }}"
                    data-photo-title="{{ $item->item_name }}"
                    aria-label="Perbesar foto {{ $item->item_name }}"
                >
                    <img src="{{ asset('storage/'.$itemImagePath) }}" alt="Foto {{ $item->item_name }}">
                    <span>Klik foto untuk memperbesar</span>
                </button>
            @else
                <span>Foto belum tersedia</span>
            @endif
        </div>
    </section>

    <div class="detail-grid">
        <section class="panel detail-card">
            <div class="panel-header">
                <h2>Informasi Umum</h2>
            </div>
            <dl class="definition-list">
                <div><dt>Kategori</dt><dd>{{ $item->category?->category_name ?? '-' }}</dd></div>
                <div><dt>Satuan</dt><dd>{{ $item->unit?->unit_code }} - {{ $item->unit?->unit_name }}</dd></div>
                <div><dt>Metode pencatatan</dt><dd>{{ $trackingTypes[$item->tracking_type] ?? $item->tracking_type }}</dd></div>
                <div><dt>Stok minimum</dt><dd>{{ number_format((float) $item->minimum_stock, 2, ',', '.') }}</dd></div>
                <div><dt>Dibuat oleh</dt><dd>{{ $item->creator?->full_name ?? '-' }}</dd></div>
                <div><dt>Tanggal dibuat</dt><dd>{{ $item->created_at?->translatedFormat('d F Y H:i') }}</dd></div>
                <div class="definition-full"><dt>Deskripsi</dt><dd>{{ $item->description ?: 'Tidak ada deskripsi.' }}</dd></div>
            </dl>
        </section>

        @if ($item->tracking_type === 'asset')
            <section class="panel detail-card">
                <div class="panel-header">
                    <h2>Ringkasan Aset</h2>
                </div>
                <dl class="definition-list">
                    <div><dt>Total unit</dt><dd>{{ number_format($statusSummary->sum()) }}</dd></div>
                    <div><dt>Tersedia</dt><dd>{{ number_format((int) ($statusSummary['available'] ?? 0)) }}</dd></div>
                    <div><dt>Belum diproses</dt><dd>{{ number_format((int) ($statusSummary['unprocessed'] ?? 0)) }}</dd></div>
                    <div><dt>Dipinjam</dt><dd>{{ number_format((int) ($statusSummary['borrowed'] ?? 0)) }}</dd></div>
                    <div><dt>Rusak</dt><dd>{{ number_format((int) ($statusSummary['damaged'] ?? 0)) }}</dd></div>
                    <div><dt>Hilang</dt><dd>{{ number_format((int) ($statusSummary['lost'] ?? 0)) }}</dd></div>
                </dl>
            </section>
        @else
            <section class="panel detail-card">
                <div class="panel-header">
                    <h2>Saldo Stok</h2>
                </div>
                <div class="stock-total">
                    <span>Total saat ini</span>
                    <strong>{{ number_format((float) $stockBalances->sum('quantity'), 2, ',', '.') }} {{ $item->unit?->unit_code }}</strong>
                </div>
            </section>
        @endif
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Dokumen Inventaris</p>
                <h2>Kontrak, SPK, dan Klasifikasi Aset</h2>
            </div>
        </div>
        <dl class="definition-list definition-list-wide">
            <div><dt>Jenis barang/buku</dt><dd>{{ $itemTypes[$item->item_type] ?? $item->item_type }}</dd></div>
            <div><dt>Nomor kontrak/SPK/surat pesanan</dt><dd>{{ $item->contract_number ?: '-' }}</dd></div>
            <div><dt>Tanggal dokumen</dt><dd>{{ $item->contract_date?->format('d-m-Y') ?? '-' }}</dd></div>
            <div><dt>Tanggal mulai</dt><dd>{{ $item->contract_start_date?->format('d-m-Y') ?? '-' }}</dd></div>
            <div><dt>Tanggal akhir</dt><dd>{{ $item->contract_end_date?->format('d-m-Y') ?? '-' }}</dd></div>
            <div><dt>Jenis aset</dt><dd>{{ $item->asset_type_code ?: '-' }}</dd></div>
            <div><dt>SKPD</dt><dd>{{ $item->skpd_name ?: 'SDN MEKARSARI 08' }}</dd></div>
            <div><dt>Foto</dt><dd>{{ $itemImagePath ? 'Tersedia' : 'Belum tersedia' }}</dd></div>
        </dl>
    </section>

    @if ($item->item_type === 'book')
        <section class="panel">
            <div class="panel-header panel-header-wrap">
                <div>
                    <p class="eyebrow">Perpustakaan</p>
                    <h2>Status Katalog Buku</h2>
                </div>
            </div>
            <dl class="definition-list definition-list-wide">
                <div><dt>ISBN-10</dt><dd>{{ $item->bookDetail?->isbn_10 ?: '-' }}</dd></div>
                <div><dt>ISBN-13</dt><dd>{{ $item->bookDetail?->isbn_13 ?: '-' }}</dd></div>
                <div><dt>Tahun terbit</dt><dd>{{ $item->bookDetail?->publication_year ?: '-' }}</dd></div>
                <div><dt>Kategori kelas</dt><dd>{{ $item->bookDetail?->grade_level_label ?? 'Umum / Semua Kelas' }}</dd></div>
                <div><dt>Klasifikasi</dt><dd>{{ $item->bookDetail?->classification_code ?: '-' }}</dd></div>
                <div><dt>Nomor panggil</dt><dd>{{ $item->bookDetail?->call_number ?: '-' }}</dd></div>
                <div><dt>Status</dt><dd>{{ ucfirst($item->bookDetail?->completion_status ?? 'incomplete') }}</dd></div>
            </dl>
            <div class="inline-notice inline-notice-compact">
                Detail katalog dan rak akan dilengkapi dari modul perpustakaan pada tahap berikutnya.
            </div>
        </section>
    @endif

    @if ($item->tracking_type === 'asset')
        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Unit fisik</p>
                    <h2>Daftar Aset atau Eksemplar</h2>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th class="table-number-heading">No.</th>
                            <th>Kode aset</th>
                            <th>Barcode</th>
                            <th>Lokasi</th>
                            <th>Rak</th>
                            <th>Kondisi</th>
                            <th>Status</th>
                            <th>Perolehan</th>
                            <th class="table-actions-heading">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assets as $asset)
                            <tr><td class="table-number">{{ (is_object($assets) && method_exists($assets, 'firstItem') && $assets->firstItem() !== null ? $assets->firstItem() : 1) + $loop->index }}</td>
                                <td><strong>{{ $asset->asset_code }}</strong></td>
                                <td>{{ $asset->barcode }}</td>
                                <td>{{ $asset->location?->location_name ?? '-' }}</td>
                                <td>{{ $asset->shelf?->shelf_code ?? 'Belum diatur' }}</td>
                                <td>{{ ucfirst($asset->condition_status) }}</td>
                                <td><span class="badge badge-neutral">{{ str_replace('_', ' ', ucfirst($asset->asset_status)) }}</span></td>
                                <td>
                                    <div class="table-primary">{{ $asset->acquisition_date?->format('d-m-Y') ?? '-' }}</div>
                                    <div class="table-secondary">{{ $asset->supplier?->supplier_name ?? 'Tanpa supplier' }}</div>
                                </td>
                                <td>
                                    @if (in_array($asset->asset_status, ['available', 'unprocessed', 'damaged'], true))
                                        <a href="{{ route('inventory.maintenance-records.create', ['asset_id' => $asset->id]) }}" class="action-link">Rawat</a>
                                    @else
                                        <span class="table-secondary">Tidak tersedia</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="empty-state">Belum ada aset untuk barang ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($assets->hasPages())
                <div class="pagination-bar">
                    <span>Menampilkan {{ $assets->firstItem() }} sampai {{ $assets->lastItem() }} dari {{ $assets->total() }} aset</span>
                    <div class="pagination-actions">
                        @if ($assets->onFirstPage())
                            <span class="button-secondary is-disabled">Sebelumnya</span>
                        @else
                            <a href="{{ $assets->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                        @endif
                        <span class="page-indicator">Halaman {{ $assets->currentPage() }} dari {{ $assets->lastPage() }}</span>
                        @if ($assets->hasMorePages())
                            <a href="{{ $assets->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                        @else
                            <span class="button-secondary is-disabled">Berikutnya</span>
                        @endif
                    </div>
                </div>
            @endif
        </section>
    @else
        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Per lokasi</p>
                    <h2>Rincian Saldo Stok</h2>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th class="table-number-heading">No.</th><th>Lokasi</th><th>Jumlah</th><th>Diperbarui</th></tr></thead>
                    <tbody>
                        @forelse ($stockBalances as $balance)
                            <tr><td class="table-number">{{ (is_object($stockBalances) && method_exists($stockBalances, 'firstItem') && $stockBalances->firstItem() !== null ? $stockBalances->firstItem() : 1) + $loop->index }}</td>
                                <td>{{ $balance->location?->location_code }} - {{ $balance->location?->location_name }}</td>
                                <td><strong>{{ number_format((float) $balance->quantity, 2, ',', '.') }} {{ $item->unit?->unit_code }}</strong></td>
                                <td>{{ $balance->updated_at?->translatedFormat('d F Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Belum ada saldo stok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
