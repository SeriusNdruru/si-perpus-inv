@extends('layouts.app')

@section('title', 'Detail Buku')
@section('page-title', 'Detail Buku Perpustakaan')

@section('content')
    @php
        $detail = $book->bookDetail;
        $status = $detail?->completion_status ?? 'incomplete';
    @endphp

    <div class="detail-heading">
        <div>
            <p class="eyebrow">{{ $book->item_code }}</p>
            <h2>{{ $book->item_name }}</h2>
            <div class="detail-badges">
                <span class="badge {{ $status === 'incomplete' ? 'badge-warning' : 'badge-success' }}">
                    Katalog {{ $completionStatuses[$status] ?? $status }}
                </span>
                <span class="badge badge-neutral">{{ $book->category?->category_name ?? 'Tanpa kategori' }}</span>
                <span class="badge badge-neutral">{{ number_format((int) $copies->total()) }} eksemplar</span>
            </div>
        </div>
        <div class="detail-actions">
            <a href="{{ route('library.books.index') }}" class="button-secondary">Kembali</a>
            <a href="{{ route('library.books.edit', $book) }}" class="button-primary button-link">Lengkapi katalog</a>
        </div>
    </div>

    <div class="detail-grid detail-grid-library">
        <section class="panel detail-card">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Bibliografi</p>
                    <h2>Informasi Katalog</h2>
                </div>
            </div>
            <dl class="definition-list definition-list-wide">
                <div>
                    <dt>ISBN-10</dt>
                    <dd>{{ $detail?->isbn_10 ?? '-' }}</dd>
                </div>
                <div>
                    <dt>ISBN-13</dt>
                    <dd>{{ $detail?->isbn_13 ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Penerbit</dt>
                    <dd>{{ $detail?->publisher?->publisher_name ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Tahun terbit</dt>
                    <dd>{{ $detail?->publication_year ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Kategori kelas</dt>
                    <dd>{{ $detail?->grade_level_label ?? 'Umum / Semua Kelas' }}</dd>
                </div>
                <div>
                    <dt>Edisi</dt>
                    <dd>{{ $detail?->edition ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Bahasa</dt>
                    <dd>{{ $detail?->language ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Jumlah halaman</dt>
                    <dd>{{ $detail?->page_count ? number_format($detail->page_count).' halaman' : '-' }}</dd>
                </div>
                <div>
                    <dt>Kode klasifikasi</dt>
                    <dd>{{ $detail?->classification_code ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Nomor panggil</dt>
                    <dd>{{ $detail?->call_number ?? '-' }}</dd>
                </div>
                <div class="definition-full">
                    <dt>Penulis</dt>
                    <dd>{{ $book->authors->pluck('author_name')->join(', ') ?: '-' }}</dd>
                </div>
                <div class="definition-full">
                    <dt>Catatan katalog</dt>
                    <dd>{{ $detail?->catalog_notes ?? '-' }}</dd>
                </div>
                <div class="definition-full">
                    <dt>Terakhir diperbarui</dt>
                    <dd>
                        {{ $detail?->updated_at?->format('d-m-Y H:i') ?? '-' }}
                        {{ $detail?->updater ? 'oleh '.$detail->updater->full_name : '' }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="panel detail-card">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Ringkasan</p>
                    <h2>Status Eksemplar</h2>
                </div>
            </div>
            <div class="copy-status-list">
                @forelse ($copySummary as $copyStatus => $total)
                    <div>
                        <span>{{ str($copyStatus)->replace('_', ' ')->title() }}</span>
                        <strong>{{ number_format((int) $total) }}</strong>
                    </div>
                @empty
                    <p class="empty-copy-summary">Belum ada eksemplar.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Unit fisik</p>
                <h2>Daftar Eksemplar</h2>
            </div>
            <a href="{{ route('library.shelf-assignments.index', ['search' => $book->item_code]) }}" class="button-secondary button-link">Atur penempatan rak</a>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Kode aset</th>
                        <th>Barcode</th>
                        <th>Kondisi</th>
                        <th>Status</th>
                        <th>Lokasi</th>
                        <th>Rak</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($copies as $copy)
                        <tr><td class="table-number">{{ (is_object($copies) && method_exists($copies, 'firstItem') && $copies->firstItem() !== null ? $copies->firstItem() : 1) + $loop->index }}</td>
                            <td><strong>{{ $copy->asset_code }}</strong></td>
                            <td>{{ $copy->barcode }}</td>
                            <td>{{ str($copy->condition_status)->replace('_', ' ')->title() }}</td>
                            <td>
                                <span class="badge {{ $copy->asset_status === 'available' ? 'badge-success' : ($copy->asset_status === 'unprocessed' ? 'badge-warning' : 'badge-neutral') }}">
                                    {{ str($copy->asset_status)->replace('_', ' ')->title() }}
                                </span>
                            </td>
                            <td>{{ $copy->location?->location_name ?? '-' }}</td>
                            <td>{{ $copy->shelf?->shelf_code ?? 'Belum diatur' }}</td>
                            <td>
                                @if (in_array($copy->asset_status, ['unprocessed', 'available'], true))
                                    <a href="{{ route('library.shelf-assignments.edit', $copy) }}" class="action-link">{{ $copy->current_shelf_id ? 'Ubah rak' : 'Atur rak' }}</a>
                                @else
                                    <span class="table-secondary">Tidak dapat diubah</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Belum ada eksemplar untuk buku ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($copies->hasPages())
            <div class="pagination-bar">
                <span>
                    Menampilkan {{ $copies->firstItem() }} sampai {{ $copies->lastItem() }}
                    dari {{ $copies->total() }} eksemplar
                </span>
                <div class="pagination-actions">
                    @if ($copies->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $copies->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif

                    <span class="page-indicator">Halaman {{ $copies->currentPage() }} dari {{ $copies->lastPage() }}</span>

                    @if ($copies->hasMorePages())
                        <a href="{{ $copies->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
