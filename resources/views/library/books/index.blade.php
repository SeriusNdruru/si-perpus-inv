@extends('layouts.app')

@section('title', 'Buku Baru Masuk')
@section('page-title', 'Buku Perpustakaan')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card">
            <span>Total judul buku</span>
            <strong>{{ number_format($summary['titles']) }}</strong>
        </article>
        <article class="stat-card {{ $summary['incomplete'] > 0 ? 'stat-warning' : '' }}">
            <span>Katalog belum lengkap</span>
            <strong>{{ number_format($summary['incomplete']) }}</strong>
        </article>
        <article class="stat-card {{ $summary['unprocessed_copies'] > 0 ? 'stat-warning' : '' }}">
            <span>Eksemplar belum diproses</span>
            <strong>{{ number_format($summary['unprocessed_copies']) }}</strong>
        </article>
        <article class="stat-card {{ $summary['without_shelf'] > 0 ? 'stat-warning' : '' }}">
            <span>Eksemplar tanpa rak</span>
            <strong>{{ number_format($summary['without_shelf']) }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <h2>Buku Baru dan Katalog</h2>
            </div>
            <span class="badge badge-neutral">Tidak perlu input judul ulang</span>
        </div>

        <form method="GET" action="{{ route('library.books.index') }}" class="filter-bar filter-bar-library-books">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Kode, judul, ISBN, penulis, atau nomor panggil"
                >
            </div>

            <div class="filter-field">
                <label for="completion_status">Status katalog</label>
                <select id="completion_status" name="completion_status">
                    <option value="">Semua status</option>
                    @foreach ($completionStatuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('completion_status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label for="grade_level">Kategori kelas</label>
                <select id="grade_level" name="grade_level">
                    <option value="">Semua kelas</option>
                    @foreach ($gradeLevels as $value => $label)
                        <option value="{{ $value }}" @selected(request('grade_level') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label for="copy_status">Status eksemplar</label>
                <select id="copy_status" name="copy_status">
                    <option value="">Semua eksemplar</option>
                    <option value="unprocessed" @selected(request('copy_status') === 'unprocessed')>Belum diproses</option>
                    <option value="without_shelf" @selected(request('copy_status') === 'without_shelf')>Belum memiliki rak</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="button-primary">Terapkan</button>
                <a href="{{ route('library.books.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Foto</th>
                        <th>Kode</th>
                        <th>Judul dan penulis</th>
                        <th>Katalog</th>
                        <th>Eksemplar</th>
                        <th>Rak</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($books as $book)
                        @php
                            $status = $book->bookDetail?->completion_status ?? 'incomplete';
                            $authors = $book->authors->pluck('author_name')->join(', ');
                        @endphp
                        <tr><td class="table-number">{{ (is_object($books) && method_exists($books, 'firstItem') && $books->firstItem() !== null ? $books->firstItem() : 1) + $loop->index }}</td>
                            <td>
                                @php($bookImagePath = $book->bookDetail?->cover_path ?: $book->image_path)
                                @if ($bookImagePath)
                                    <button
                                        type="button"
                                        class="item-table-photo item-photo-preview-button"
                                        data-photo-preview
                                        data-photo-src="{{ asset('storage/'.$bookImagePath) }}"
                                        data-photo-title="{{ $book->item_name }}"
                                        aria-label="Lihat cover {{ $book->item_name }}"
                                    >
                                        <img src="{{ asset('storage/'.$bookImagePath) }}" alt="Cover {{ $book->item_name }}">
                                    </button>
                                @else
                                    <div class="item-table-photo">{{ mb_strtoupper(mb_substr($book->item_name, 0, 2)) }}</div>
                                @endif
                            </td>
                            <td><strong>{{ $book->item_code }}</strong></td>
                            <td>
                                <div class="table-primary">{{ $book->item_name }}</div>
                                <div class="table-secondary">
                                    {{ $authors !== '' ? $authors : 'Penulis belum diisi' }}
                                    @if ($book->bookDetail?->publication_year)
                                        · {{ $book->bookDetail->publication_year }}
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $status === 'incomplete' ? 'badge-warning' : 'badge-success' }}">
                                    {{ $completionStatuses[$status] ?? $status }}
                                </span>
                                <div class="table-secondary">
                                    {{ $book->bookDetail?->publisher?->publisher_name ?? 'Penerbit belum diisi' }}
                                    · {{ $book->bookDetail?->grade_level_label ?? 'Umum / Semua Kelas' }}
                                </div>
                            </td>
                            <td>
                                <div class="table-primary">{{ number_format((int) $book->copies_count) }} eksemplar</div>
                                <div class="table-secondary">{{ number_format((int) $book->unprocessed_copies_count) }} belum diproses</div>
                            </td>
                            <td>
                                @if ((int) $book->without_shelf_count > 0)
                                    <span class="badge badge-warning">{{ number_format((int) $book->without_shelf_count) }} tanpa rak</span>
                                @else
                                    <span class="badge badge-success">Sudah diatur</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('library.books.show', $book) }}" class="action-link">Detail</a>
                                    <a href="{{ route('library.books.edit', $book) }}" class="action-link">Lengkapi katalog</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Belum ada buku yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($books->hasPages())
            <div class="pagination-bar">
                <span>
                    Menampilkan {{ $books->firstItem() }} sampai {{ $books->lastItem() }}
                    dari {{ $books->total() }} judul
                </span>
                <div class="pagination-actions">
                    @if ($books->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $books->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif

                    <span class="page-indicator">Halaman {{ $books->currentPage() }} dari {{ $books->lastPage() }}</span>

                    @if ($books->hasMorePages())
                        <a href="{{ $books->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
