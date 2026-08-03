@extends('layouts.app')
@section('title', 'Daftar Hapus Satuan')
@section('page-title', 'Daftar Hapus Satuan')
@section('content')
<section class="panel">
<div class="panel-header panel-header-wrap"><div><p class="eyebrow">Arsip data</p><h2>Daftar Hapus Satuan</h2></div><a href="{{ route('units.index') }}" class="button-secondary">Kembali</a></div>
<form method="GET" action="{{ route('units.deleted.index') }}" class="filter-bar filter-bar-compact"><div class="filter-field filter-search"><label for="search">Pencarian</label><input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Cari data"></div><div class="filter-actions"><button type="submit" class="button-primary">Terapkan</button><a href="{{ route('units.deleted.index') }}" class="button-secondary">Reset</a></div></form>
<div class="table-wrap"><table><thead><tr><th>Kode</th><th>Nama</th><th>Deskripsi</th><th>Jumlah barang</th><th class="table-actions-heading">Aksi</th></tr></thead><tbody>
@forelse ($units as $record)
<tr><td><strong>{{ $record->unit_code }}</strong></td><td>{{ $record->unit_name }}</td><td>{{ $record->description ?: '-' }}</td><td>{{ number_format((int) $record->items_count) }}</td><td><form method="POST" action="{{ route('units.deleted.restore', $record) }}">@csrf @method('PATCH')<button class="action-button">Pulihkan</button></form></td></tr>
@empty<tr><td colspan="5" class="empty-state">Daftar Hapus masih kosong.</td></tr>@endforelse
</tbody></table></div>
@if ($units->hasPages())<div class="pagination-bar"><span>Menampilkan {{ $units->firstItem() }} sampai {{ $units->lastItem() }} dari {{ $units->total() }} data</span><div class="pagination-actions">@if ($units->onFirstPage())<span class="button-secondary is-disabled">Sebelumnya</span>@else<a href="{{ $units->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>@endif<span class="page-indicator">Halaman {{ $units->currentPage() }} dari {{ $units->lastPage() }}</span>@if ($units->hasMorePages())<a href="{{ $units->nextPageUrl() }}" class="button-secondary">Berikutnya</a>@else<span class="button-secondary is-disabled">Berikutnya</span>@endif</div></div>@endif
</section>
@endsection
