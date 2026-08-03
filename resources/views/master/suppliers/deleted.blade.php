@extends('layouts.app')
@section('title', 'Daftar Hapus Supplier')
@section('page-title', 'Daftar Hapus Supplier')
@section('content')
<section class="panel">
<div class="panel-header panel-header-wrap"><div><p class="eyebrow">Arsip data</p><h2>Daftar Hapus Supplier</h2></div><a href="{{ route('suppliers.index') }}" class="button-secondary">Kembali</a></div>
<form method="GET" action="{{ route('suppliers.deleted.index') }}" class="filter-bar filter-bar-compact"><div class="filter-field filter-search"><label for="search">Pencarian</label><input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Cari data"></div><div class="filter-actions"><button type="submit" class="button-primary">Terapkan</button><a href="{{ route('suppliers.deleted.index') }}" class="button-secondary">Reset</a></div></form>
<div class="table-wrap"><table><thead><tr><th class="table-number-heading">No.</th><th>Kode</th><th>Supplier</th><th>Kontak</th><th>Jumlah aset</th><th class="table-actions-heading">Aksi</th></tr></thead><tbody>
@forelse ($suppliers as $record)
<tr><td class="table-number">{{ (is_object($suppliers) && method_exists($suppliers, 'firstItem') && $suppliers->firstItem() !== null ? $suppliers->firstItem() : 1) + $loop->index }}</td><td><strong>{{ $record->supplier_code }}</strong></td><td>{{ $record->supplier_name }}</td><td>{{ $record->contact_person ?: '-' }}</td><td>{{ number_format((int) $record->assets_count) }}</td><td><form method="POST" action="{{ route('suppliers.deleted.restore', $record) }}">@csrf @method('PATCH')<button class="action-button">Pulihkan</button></form></td></tr>
@empty<tr><td colspan="6" class="empty-state">Daftar Hapus masih kosong.</td></tr>@endforelse
</tbody></table></div>
@if ($suppliers->hasPages())<div class="pagination-bar"><span>Menampilkan {{ $suppliers->firstItem() }} sampai {{ $suppliers->lastItem() }} dari {{ $suppliers->total() }} data</span><div class="pagination-actions">@if ($suppliers->onFirstPage())<span class="button-secondary is-disabled">Sebelumnya</span>@else<a href="{{ $suppliers->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>@endif<span class="page-indicator">Halaman {{ $suppliers->currentPage() }} dari {{ $suppliers->lastPage() }}</span>@if ($suppliers->hasMorePages())<a href="{{ $suppliers->nextPageUrl() }}" class="button-secondary">Berikutnya</a>@else<span class="button-secondary is-disabled">Berikutnya</span>@endif</div></div>@endif
</section>
@endsection
