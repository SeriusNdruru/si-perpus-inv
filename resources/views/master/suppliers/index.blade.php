@extends('layouts.app')

@section('title', 'Supplier')
@section('page-title', 'Master Supplier')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card">
            <span>Total supplier</span>
            <strong>{{ number_format($summary['total']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Supplier aktif</span>
            <strong>{{ number_format($summary['active']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Di Daftar Hapus</span>
            <strong>{{ number_format($summary['inactive']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Sudah digunakan</span>
            <strong>{{ number_format($summary['used']) }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Data bersama</p>
                <h2>Daftar Supplier</h2>
            </div>
            <div class="panel-header-actions">

                <a href="{{ route('suppliers.deleted.index') }}" class="button-secondary">Daftar Hapus</a>

                <a href="{{ route('suppliers.create') }}" class="button-primary button-link">Tambah supplier</a>

            </div>
        </div>

        <form method="GET" action="{{ route('suppliers.index') }}" class="filter-bar filter-bar-compact">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Kode, nama, kontak, telepon, atau email"
                >
            </div>

            <div class="filter-actions">
                <button type="submit" class="button-primary">Terapkan</button>
                <a href="{{ route('suppliers.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Supplier</th>
                        <th>Kontak</th>
                        <th>Alamat</th>
                        <th>Jumlah aset</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td><strong>{{ $supplier->supplier_code }}</strong></td>
                            <td>
                                <div class="table-primary">{{ $supplier->supplier_name }}</div>
                                <div class="table-secondary">{{ $supplier->contact_person ?: 'Kontak belum diisi' }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $supplier->phone ?: '-' }}</div>
                                <div class="table-secondary">{{ $supplier->email ?: '-' }}</div>
                            </td>
                            <td>
                                <div class="table-secondary">
                                    {{ $supplier->address ? \Illuminate\Support\Str::limit($supplier->address, 70) : 'Alamat belum diisi.' }}
                                </div>
                            </td>
                            <td>{{ number_format((int) $supplier->assets_count) }}</td>
                            <td>
                                <span class="badge {{ $supplier->status === 'active' ? 'badge-success' : 'badge-muted' }}">
                                    {{ $supplier->status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('suppliers.edit', $supplier) }}" class="action-link">Edit</a>
                                    <form method="POST" action="{{ route('suppliers.toggle-status', $supplier) }}" onsubmit="return confirm('Hapus supplier ini dari daftar aktif dan pindahkan ke Daftar Hapus?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="action-button">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada supplier yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($suppliers->hasPages())
            <div class="pagination-bar">
                <span>
                    Menampilkan {{ $suppliers->firstItem() }} sampai {{ $suppliers->lastItem() }}
                    dari {{ $suppliers->total() }} supplier
                </span>
                <div class="pagination-actions">
                    @if ($suppliers->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $suppliers->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif

                    <span class="page-indicator">Halaman {{ $suppliers->currentPage() }} dari {{ $suppliers->lastPage() }}</span>

                    @if ($suppliers->hasMorePages())
                        <a href="{{ $suppliers->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
