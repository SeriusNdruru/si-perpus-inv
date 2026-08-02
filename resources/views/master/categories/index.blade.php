@extends('layouts.app')

@section('title', 'Kategori')
@section('page-title', 'Master Kategori')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card">
            <span>Total kategori</span>
            <strong>{{ number_format($summary['total']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Kategori aktif</span>
            <strong>{{ number_format($summary['active']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Cakupan inventaris</span>
            <strong>{{ number_format($summary['inventory']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Cakupan perpustakaan</span>
            <strong>{{ number_format($summary['library']) }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Data bersama</p>
                <h2>Daftar Kategori</h2>
            </div>
            <a href="{{ route('categories.create') }}" class="button-primary button-link">Tambah kategori</a>
        </div>

        <form method="GET" action="{{ route('categories.index') }}" class="filter-bar">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Kode atau nama kategori"
                >
            </div>

            <div class="filter-field">
                <label for="scope">Cakupan</label>
                <select id="scope" name="scope">
                    <option value="">Semua cakupan</option>
                    <option value="inventory" @selected(request('scope') === 'inventory')>Inventaris</option>
                    <option value="library" @selected(request('scope') === 'library')>Perpustakaan</option>
                    <option value="both" @selected(request('scope') === 'both')>Bersama</option>
                </select>
            </div>

            <div class="filter-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua status</option>
                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Tidak aktif</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="button-primary">Terapkan</button>
                <a href="{{ route('categories.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama kategori</th>
                        <th>Induk</th>
                        <th>Cakupan</th>
                        <th>Turunan</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td><strong>{{ $category->category_code }}</strong></td>
                            <td>
                                <div class="table-primary">{{ $category->category_name }}</div>
                                @if ($category->description)
                                    <div class="table-secondary">{{ $category->description }}</div>
                                @endif
                            </td>
                            <td>{{ $category->parent?->category_name ?? 'Kategori utama' }}</td>
                            <td>
                                <span class="badge badge-neutral">
                                    {{ match ($category->scope) {
                                        'inventory' => 'Inventaris',
                                        'library' => 'Perpustakaan',
                                        default => 'Bersama',
                                    } }}
                                </span>
                            </td>
                            <td>{{ $category->children_count }}</td>
                            <td>
                                <span class="badge {{ $category->status === 'active' ? 'badge-success' : 'badge-muted' }}">
                                    {{ $category->status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('categories.edit', $category) }}" class="action-link">Edit</a>
                                    <form method="POST" action="{{ route('categories.toggle-status', $category) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="action-button">
                                            {{ $category->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada kategori yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($categories->hasPages())
            <div class="pagination-bar">
                <span>
                    Menampilkan {{ $categories->firstItem() }} sampai {{ $categories->lastItem() }}
                    dari {{ $categories->total() }} kategori
                </span>
                <div class="pagination-actions">
                    @if ($categories->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $categories->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif

                    <span class="page-indicator">Halaman {{ $categories->currentPage() }} dari {{ $categories->lastPage() }}</span>

                    @if ($categories->hasMorePages())
                        <a href="{{ $categories->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
