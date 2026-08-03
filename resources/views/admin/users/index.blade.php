@extends('layouts.app')

@section('title', 'Pengguna Sistem')
@section('page-title', 'Pengguna Sistem')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card">
            <span>Total pengguna sistem</span>
            <strong>{{ number_format($summary['total']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Akun aktif</span>
            <strong>{{ number_format($summary['active']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Admin inventaris</span>
            <strong>{{ number_format($summary['inventory_admins']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Admin perpustakaan</span>
            <strong>{{ number_format($summary['library_admins']) }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Administrasi sistem</p>
                <h2>Daftar Pengguna</h2>
            </div>
            <a href="{{ route('admin.users.create') }}" class="button-primary">Tambah pengguna</a>
        </div>

        <form method="GET" action="{{ route('admin.users.index') }}" class="filter-bar">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Nama, username, email, atau telepon"
                >
            </div>

            <div class="filter-field">
                <label for="role">Peran</label>
                <select id="role" name="role">
                    <option value="">Semua peran</option>
                    @foreach ($roleOptions as $roleOption)
                        <option value="{{ $roleOption->role_code }}" @selected(request('role') === $roleOption->role_code)>
                            {{ $roleOption->role_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua status</option>
                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Tidak aktif</option>
                    <option value="locked" @selected(request('status') === 'locked')>Terkunci</option>
                </select>
            </div>

            <div class="filter-actions">
                <a href="{{ route('admin.users.index') }}" class="button-secondary">Reset</a>
                <button type="submit" class="button-primary">Terapkan</button>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Pengguna</th>
                        <th>Peran</th>
                        <th>Kontak</th>
                        <th>Status</th>
                        <th>Login terakhir</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $managedUser)
                        @php
                            $roleNames = $managedUser->roles->pluck('role_name')->implode(', ');
                            $isCurrentAccount = auth()->id() === $managedUser->id;
                        @endphp
                        <tr><td class="table-number">{{ (is_object($users) && method_exists($users, 'firstItem') && $users->firstItem() !== null ? $users->firstItem() : 1) + $loop->index }}</td>
                            <td>
                                <div class="table-primary">{{ $managedUser->full_name }}</div>
                                <div class="table-secondary">{{ '@'.$managedUser->username }}</div>
                            </td>
                            <td>
                                <span class="badge">{{ $roleNames !== '' ? $roleNames : 'Tanpa peran' }}</span>
                            </td>
                            <td>
                                <div class="table-primary">{{ $managedUser->email ?: '-' }}</div>
                                <div class="table-secondary">{{ $managedUser->phone ?: 'Telepon belum diisi' }}</div>
                            </td>
                            <td>
                                @if ($managedUser->status === 'active')
                                    <span class="badge badge-success">Aktif</span>
                                @elseif ($managedUser->status === 'locked')
                                    <span class="badge badge-danger">Terkunci</span>
                                @else
                                    <span class="badge badge-muted">Tidak aktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="table-primary">
                                    {{ $managedUser->last_login_at?->translatedFormat('d M Y, H:i') ?? 'Belum pernah' }}
                                </div>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('admin.users.edit', $managedUser) }}" class="action-link">Edit</a>
                                    <a href="{{ route('admin.users.password.edit', $managedUser) }}" class="action-link">Password</a>

                                    @if (! $isCurrentAccount)
                                        @if ($managedUser->status !== 'active')
                                            <form method="POST" action="{{ route('admin.users.status', $managedUser) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="active">
                                                <button type="submit" class="action-button">Aktifkan</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.users.status', $managedUser) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="locked">
                                                <button type="submit" class="action-button">Kunci</button>
                                            </form>
                                        @endif
                                    @else
                                        <span class="badge badge-neutral">Akun Anda</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada pengguna yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="pagination-bar">
                <span>
                    Menampilkan {{ $users->firstItem() }} sampai {{ $users->lastItem() }}
                    dari {{ $users->total() }} pengguna
                </span>
                <div class="pagination-actions">
                    @if ($users->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $users->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif

                    <span class="page-indicator">Halaman {{ $users->currentPage() }} dari {{ $users->lastPage() }}</span>

                    @if ($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
