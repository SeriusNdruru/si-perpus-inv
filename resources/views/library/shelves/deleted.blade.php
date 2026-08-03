@extends('layouts.app')
@section('title', 'Daftar Hapus Rak Perpustakaan')
@section('page-title', 'Daftar Hapus Rak Perpustakaan')
@section('content')
<section class="panel">
<div class="panel-header panel-header-wrap"><div><p class="eyebrow">Arsip data</p><h2>Daftar Hapus Rak Perpustakaan</h2></div><a href="{{ route('library.shelves.index') }}" class="button-secondary">Kembali</a></div>
<form method="GET" action="{{ route('library.shelves.deleted.index') }}" class="filter-bar filter-bar-compact"><div class="filter-field filter-search"><label for="search">Pencarian</label><input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Cari data"></div><div class="filter-field"><label for="location_id">Lokasi</label><select id="location_id" name="location_id"><option value="">Semua</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->location_code }} - {{ $location->location_name }}</option>@endforeach</select></div><div class="filter-actions"><button type="submit" class="button-primary">Terapkan</button><a href="{{ route('library.shelves.deleted.index') }}" class="button-secondary">Reset</a></div></form>
<div class="table-wrap"><table><thead><tr><th>Kode</th><th>Rak</th><th>Lokasi</th><th>Klasifikasi</th><th class="table-actions-heading">Aksi</th></tr></thead><tbody>
@forelse ($shelves as $record)
<tr><td><strong>{{ $record->shelf_code }}</strong></td><td>{{ $record->shelf_name }}</td><td>{{ $record->location?->location_name ?? '-' }}</td><td>{{ $record->classification_range ?: '-' }}</td><td><form method="POST" action="{{ route('library.shelves.deleted.restore', $record) }}">@csrf @method('PATCH')<button class="action-button">Pulihkan</button></form></td></tr>
@empty<tr><td colspan="5" class="empty-state">Daftar Hapus masih kosong.</td></tr>@endforelse
</tbody></table></div>
@if ($shelves->hasPages())<div class="pagination-bar"><span>Menampilkan {{ $shelves->firstItem() }} sampai {{ $shelves->lastItem() }} dari {{ $shelves->total() }} data</span><div class="pagination-actions">@if ($shelves->onFirstPage())<span class="button-secondary is-disabled">Sebelumnya</span>@else<a href="{{ $shelves->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>@endif<span class="page-indicator">Halaman {{ $shelves->currentPage() }} dari {{ $shelves->lastPage() }}</span>@if ($shelves->hasMorePages())<a href="{{ $shelves->nextPageUrl() }}" class="button-secondary">Berikutnya</a>@else<span class="button-secondary is-disabled">Berikutnya</span>@endif</div></div>@endif
</section>
@endsection
