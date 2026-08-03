@extends('layouts.inventory-audit')

@section('title', 'Audit Inventaris')

@section('content')
<section class="portal-page-hero portal-page-hero-audit">
    <div class="portal-container">
        <span class="portal-kicker">Pemeriksaan aset sekolah</span>
        <h1>Kondisi dan letak setiap aset</h1>
        <p>Gunakan filter untuk memeriksa barang atau buku berdasarkan kondisi dan ruangan.</p>
        <button class="portal-button portal-button-soft" type="button" onclick="window.print()">Cetak laporan</button>
    </div>
</section>

<section class="portal-section portal-section-tight">
    <div class="portal-container">
        <form method="GET" class="portal-filter portal-filter-four">
            <input name="search" type="search" value="{{ request('search') }}" placeholder="Kode aset, barang, atau lokasi">
            <select name="condition">
                <option value="">Semua kondisi</option>
                @foreach (['good' => 'Baik', 'fair' => 'Cukup', 'damaged' => 'Rusak', 'lost' => 'Hilang'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('condition') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="location">
                <option value="">Semua lokasi</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected((int) request('location') === $location->id)>{{ $location->location_name }}</option>
                @endforeach
            </select>
            <button class="portal-button portal-button-primary" type="submit">Terapkan</button>
        </form>

        <div class="portal-table-wrap">
            <table class="portal-table portal-table-audit">
                <thead>
                    <tr><th class="table-number-heading">No.</th><th>Foto</th><th>Kode aset</th><th>Barang/buku</th><th>Kategori</th><th>Kondisi</th><th>Status</th><th>Lokasi</th><th>Rak</th></tr>
                </thead>
                <tbody>
                    @forelse ($assets as $asset)
                        <tr><td class="table-number">{{ (is_object($assets) && method_exists($assets, 'firstItem') && $assets->firstItem() !== null ? $assets->firstItem() : 1) + $loop->index }}</td>
                            <td>
                                @if ($asset->image_path)
                                    <button
                                        type="button"
                                        class="item-table-photo item-photo-preview-button"
                                        data-photo-preview
                                        data-photo-src="{{ route('media.image', ['path' => $asset->image_path]) }}"
                                        data-photo-title="{{ $asset->item_name }}"
                                        aria-label="Lihat foto {{ $asset->item_name }}"
                                    >
                                        <img src="{{ route('media.thumbnail', ['path' => $asset->image_path, 'size' => 160]) }}" alt="Foto {{ $asset->item_name }}" width="58" height="58" loading="lazy" decoding="async" fetchpriority="low" data-image-retry>
                                    </button>
                                @else
                                    <div class="item-table-photo" aria-label="Tidak ada foto {{ $asset->item_name }}">
                                        <span>{{ mb_strtoupper(mb_substr($asset->item_name, 0, 2)) }}</span>
                                    </div>
                                @endif
                            </td>
                            <td><strong>{{ $asset->asset_code }}</strong><small>{{ $asset->item_code }}</small></td>
                            <td>{{ $asset->item_name }}</td>
                            <td>{{ $asset->category_name ?: '-' }}</td>
                            <td>
                                @php
                                    $conditionLabels = [
                                        'good' => 'Baik',
                                        'fair' => 'Cukup',
                                        'damaged' => 'Rusak',
                                        'lost' => 'Hilang',
                                    ];
                                @endphp
                                <span class="portal-status portal-status-{{ $asset->condition_status }}">
                                    {{ $conditionLabels[$asset->condition_status] ?? ucfirst($asset->condition_status) }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $assetStatusLabels = [
                                        'available' => 'Tersedia',
                                        'borrowed' => 'Dipinjam',
                                        'maintenance' => 'Pemeliharaan',
                                        'damaged' => 'Rusak',
                                        'lost' => 'Hilang',
                                        'disposed' => 'Dihapuskan',
                                    ];
                                @endphp
                                {{ $assetStatusLabels[$asset->asset_status] ?? str_replace('_', ' ', ucfirst($asset->asset_status)) }}
                            </td>
                            <td>{{ $asset->location_name ?: 'Belum ditentukan' }}</td>
                            <td>{{ $asset->shelf_code ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9">Tidak ada aset sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="portal-pagination">
            @if ($assets->onFirstPage())<span>Sebelumnya</span>@else<a href="{{ $assets->previousPageUrl() }}">Sebelumnya</a>@endif
            <strong>Halaman {{ $assets->currentPage() }} dari {{ $assets->lastPage() }}</strong>
            @if ($assets->hasMorePages())<a href="{{ $assets->nextPageUrl() }}">Berikutnya</a>@else<span>Berikutnya</span>@endif
        </div>

        <div class="portal-section-heading portal-subheading">
            <div><span class="portal-kicker">Barang berbasis jumlah</span><h2>Saldo per lokasi</h2></div>
        </div>
        <div class="portal-table-wrap">
            <table class="portal-table">
                <thead><tr><th class="table-number-heading">No.</th><th>Foto</th><th>Kode</th><th>Barang</th><th>Lokasi</th><th>Jumlah</th></tr></thead>
                <tbody>
                    @forelse ($stockBalances as $stock)
                        <tr><td class="table-number">{{ (is_object($stockBalances) && method_exists($stockBalances, 'firstItem') && $stockBalances->firstItem() !== null ? $stockBalances->firstItem() : 1) + $loop->index }}</td>
                            <td>
                                @if ($stock->image_path)
                                    <button
                                        type="button"
                                        class="item-table-photo item-photo-preview-button"
                                        data-photo-preview
                                        data-photo-src="{{ route('media.image', ['path' => $stock->image_path]) }}"
                                        data-photo-title="{{ $stock->item_name }}"
                                        aria-label="Lihat foto {{ $stock->item_name }}"
                                    >
                                        <img src="{{ route('media.thumbnail', ['path' => $stock->image_path, 'size' => 160]) }}" alt="Foto {{ $stock->item_name }}" width="58" height="58" loading="lazy" decoding="async" fetchpriority="low" data-image-retry>
                                    </button>
                                @else
                                    <div class="item-table-photo" aria-label="Tidak ada foto {{ $stock->item_name }}">
                                        <span>{{ mb_strtoupper(mb_substr($stock->item_name, 0, 2)) }}</span>
                                    </div>
                                @endif
                            </td>
                            <td>{{ $stock->item_code }}</td>
                            <td>{{ $stock->item_name }}</td>
                            <td>{{ $stock->location_name }}</td>
                            <td>{{ number_format((float) $stock->quantity, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Tidak ada saldo barang berbasis jumlah.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
