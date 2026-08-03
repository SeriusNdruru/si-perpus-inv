@extends('layouts.app')
@section('title', 'Daftar Hapus Kategori')
@section('page-title', 'Daftar Hapus Kategori')
@section('content')
<section class="panel">
<div class="panel-header panel-header-wrap"><div><p class="eyebrow">Arsip data</p><h2>Daftar Hapus Kategori</h2></div><a href="{{ route('categories.index') }}" class="button-secondary">Kembali</a></div>
<form method="GET" action="{{ route('categories.deleted.index') }}" class="filter-bar filter-bar-compact"><div class="filter-field filter-search"><label for="search">Pencarian</label><input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Cari data"></div><div class="filter-field"><label for="scope">Cakupan</label><select id="scope" name="scope"><option value="">Semua</option><option value="inventory" @selected(request('scope') === 'inventory')>Inventaris</option><option value="library" @selected(request('scope') === 'library')>Perpustakaan</option><option value="both" @selected(request('scope') === 'both')>Bersama</option></select></div><div class="filter-actions"><button type="submit" class="button-primary">Terapkan</button><a href="{{ route('categories.deleted.index') }}" class="button-secondary">Reset</a></div></form>
<div class="table-wrap"><table><thead><tr><th>Kode</th><th>Nama</th><th>Induk</th><th>Cakupan</th><th class="table-actions-heading">Aksi</th></tr></thead><tbody>
@forelse ($categories as $record)
<tr><td><strong>{{ $record->category_code }}</strong></td><td>{{ $record->category_name }}</td><td>{{ $record->parent?->category_name ?? 'Kategori utama' }}</td><td>{{ match ($record->scope) { 'inventory' => 'Inventaris', 'library' => 'Perpustakaan', default => 'Bersama' } }}</td><td><form method="POST" action="{{ route('categories.deleted.restore', $record) }}">@csrf @method('PATCH')<button class="action-button">Pulihkan</button></form></td></tr>
@empty<tr><td colspan="5" class="empty-state">Daftar Hapus masih kosong.</td></tr>@endforelse
</tbody></table></div>
@if ($categories->hasPages())<div class="pagination-bar"><span>Menampilkan {{ $categories->firstItem() }} sampai {{ $categories->lastItem() }} dari {{ $categories->total() }} data</span><div class="pagination-actions">@if ($categories->onFirstPage())<span class="button-secondary is-disabled">Sebelumnya</span>@else<a href="{{ $categories->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>@endif<span class="page-indicator">Halaman {{ $categories->currentPage() }} dari {{ $categories->lastPage() }}</span>@if ($categories->hasMorePages())<a href="{{ $categories->nextPageUrl() }}" class="button-secondary">Berikutnya</a>@else<span class="button-secondary is-disabled">Berikutnya</span>@endif</div></div>@endif
</section>
@endsection
