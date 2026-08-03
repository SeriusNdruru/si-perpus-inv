@extends('layouts.inventory-public')

@section('title', 'Inventaris Umum')

@section('content')
<section class="portal-page-hero portal-page-hero-inventory">
    <div class="portal-container">
        <span class="portal-kicker">Dashboard umum guru, kepala sekolah, dan staf</span>
        <h1>Informasi umum barang dan buku</h1>
        <div class="portal-hero-actions">
            <a href="{{ route('public.inventory.report-damage') }}" class="portal-button portal-button-primary">Lapor kerusakan</a>
            <button class="portal-button portal-button-soft" type="button" onclick="window.print()">Cetak laporan</button>
        </div>
    </div>
</section>

<section class="portal-section portal-section-tight">
    <div class="portal-container">
        <div class="portal-stat-grid portal-inventory-stats">
            <article><strong>{{ number_format($statistics['items']) }}</strong><span>Jenis barang</span></article>
            <article><strong>{{ number_format($statistics['assets']) }}</strong><span>Unit aset</span></article>
            <article><strong>{{ number_format($statistics['damaged']) }}</strong><span>Kondisi rusak</span></article>
            <article><strong>{{ number_format($statistics['locations']) }}</strong><span>Lokasi aktif</span></article>
        </div>

        <form method="GET" class="portal-filter">
            <input name="search" type="search" value="{{ request('search') }}" placeholder="Cari barang, buku, kode, atau kategori">
            <button class="portal-button portal-button-primary" type="submit">Cari</button>
            <a href="{{ route('public.inventory.general') }}" class="portal-button portal-button-soft">Reset</a>
        </form>

        <div class="portal-table-wrap">
            <table class="portal-table">
                <thead><tr><th>Foto</th><th>Barang</th><th>Jenis</th><th>Kategori</th><th>Jumlah</th><th>Masalah kondisi</th></tr></thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>
                                @if ($item->image_path)
                                    <button
                                        type="button"
                                        class="portal-photo-thumbnail"
                                        data-photo-preview
                                        data-photo-src="{{ asset('storage/'.$item->image_path) }}"
                                        data-photo-title="{{ $item->item_name }}"
                                        aria-label="Lihat foto {{ $item->item_name }}"
                                    >
                                        <img src="{{ asset('storage/'.$item->image_path) }}" alt="Foto {{ $item->item_name }}" loading="lazy">
                                    </button>
                                @else
                                    <span class="portal-photo-empty">Tanpa foto</span>
                                @endif
                            </td>
                            <td><strong>{{ $item->item_name }}</strong><small>{{ $item->item_code }}</small></td>
                            <td>{{ ucfirst($item->item_type) }}</td>
                            <td>{{ $item->category_name ?: '-' }}</td>
                            <td>
                                {{ $item->tracking_type === 'asset'
                                    ? number_format($item->asset_count).' unit'
                                    : number_format((float) $item->stock_quantity, 2).' stok' }}
                            </td>
                            <td>
                                <span class="portal-status {{ $item->problem_count > 0 ? 'portal-status-danger' : 'portal-status-good' }}">
                                    {{ $item->problem_count > 0 ? $item->problem_count.' rusak/hilang' : 'Tidak ada masalah' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Data tidak ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="portal-pagination">
            @if ($items->onFirstPage())<span>Sebelumnya</span>@else<a href="{{ $items->previousPageUrl() }}">Sebelumnya</a>@endif
            <strong>Halaman {{ $items->currentPage() }} dari {{ $items->lastPage() }}</strong>
            @if ($items->hasMorePages())<a href="{{ $items->nextPageUrl() }}">Berikutnya</a>@else<span>Berikutnya</span>@endif
        </div>
    </div>
</section>
@include('shared.photo-preview-modal')
@endsection
