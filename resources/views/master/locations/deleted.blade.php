@extends('layouts.app')
@section('title', 'Daftar Hapus Lokasi')
@section('page-title', 'Daftar Hapus Lokasi')
@section('content')
<section class="panel">
<div class="panel-header panel-header-wrap"><div><p class="eyebrow">Arsip data</p><h2>Daftar Hapus Lokasi</h2></div><a href="{{ route('locations.index') }}" class="button-secondary">Kembali</a></div>
<form method="GET" action="{{ route('locations.deleted.index') }}" class="filter-bar filter-bar-compact"><div class="filter-field filter-search"><label for="search">Pencarian</label><input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Cari data"></div><div class="filter-field"><label for="type">Jenis</label><select id="type" name="type"><option value="">Semua</option>@foreach ($typeLabels as $value => $label)<option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>@endforeach</select></div><div class="filter-actions"><button type="submit" class="button-primary">Terapkan</button><a href="{{ route('locations.deleted.index') }}" class="button-secondary">Reset</a></div></form>
<div class="table-wrap"><table><thead><tr><th class="table-number-heading">No.</th><th>Kode</th><th>Lokasi</th><th>Jenis</th><th>Induk</th><th class="table-actions-heading">Aksi</th></tr></thead><tbody>
@forelse ($locations as $record)
<tr><td class="table-number">{{ (is_object($locations) && method_exists($locations, 'firstItem') && $locations->firstItem() !== null ? $locations->firstItem() : 1) + $loop->index }}</td><td><strong>{{ $record->location_code }}</strong></td><td>{{ $record->location_name }}</td><td>{{ $typeLabels[$record->location_type] ?? $record->location_type }}</td><td>{{ $record->parent?->location_name ?? 'Lokasi utama' }}</td><td><form method="POST" action="{{ route('locations.deleted.restore', $record) }}">@csrf @method('PATCH')<button class="action-button">Pulihkan</button></form></td></tr>
@empty<tr><td colspan="6" class="empty-state">Daftar Hapus masih kosong.</td></tr>@endforelse
</tbody></table></div>
@if ($locations->hasPages())<div class="pagination-bar"><span>Menampilkan {{ $locations->firstItem() }} sampai {{ $locations->lastItem() }} dari {{ $locations->total() }} data</span><div class="pagination-actions">@if ($locations->onFirstPage())<span class="button-secondary is-disabled">Sebelumnya</span>@else<a href="{{ $locations->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>@endif<span class="page-indicator">Halaman {{ $locations->currentPage() }} dari {{ $locations->lastPage() }}</span>@if ($locations->hasMorePages())<a href="{{ $locations->nextPageUrl() }}" class="button-secondary">Berikutnya</a>@else<span class="button-secondary is-disabled">Berikutnya</span>@endif</div></div>@endif
</section>
@endsection
