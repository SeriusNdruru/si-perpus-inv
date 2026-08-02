@extends('layouts.app')

@section('title', 'Riwayat Aktivitas')
@section('page-title', 'Riwayat Aktivitas')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card">
            <span>Total aktivitas</span>
            <strong>{{ number_format($summary['total']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Aktivitas hari ini</span>
            <strong>{{ number_format($summary['today']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Login dan logout</span>
            <strong>{{ number_format($summary['auth']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Perubahan data</span>
            <strong>{{ number_format($summary['changes']) }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Keamanan dan penelusuran</p>
                <h2>Catatan Aktivitas Sistem</h2>
                <p class="panel-description">Menampilkan aktivitas yang dicatat oleh modul autentikasi, pengguna, transaksi perpustakaan, pengaturan, dan ekspor data.</p>
            </div>
            <a href="{{ route('admin.audit-logs.csv', request()->query()) }}" class="button-secondary">Unduh CSV</a>
        </div>

        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="filter-bar audit-filter-grid">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Pengguna, modul, tabel, IP, atau ID"
                >
            </div>

            <div class="filter-field">
                <label for="action">Aksi</label>
                <select id="action" name="action">
                    <option value="">Semua aksi</option>
                    @foreach ($actions as $value => $label)
                        <option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label for="module">Modul</label>
                <select id="module" name="module">
                    <option value="">Semua modul</option>
                    @foreach ($modules as $module)
                        <option value="{{ $module }}" @selected(request('module') === $module)>
                            {{ str($module)->replace('_', ' ')->title() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label for="user_id">Pengguna</label>
                <select id="user_id" name="user_id">
                    <option value="">Semua pengguna</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>
                            {{ $user->full_name }} ({{ '@'.$user->username }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label for="date_from">Tanggal awal</label>
                <input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}">
            </div>

            <div class="filter-field">
                <label for="date_to">Tanggal akhir</label>
                <input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}">
            </div>

            <div class="filter-actions">
                <a href="{{ route('admin.audit-logs.index') }}" class="button-secondary">Reset</a>
                <button type="submit" class="button-primary">Terapkan</button>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Pengguna</th>
                        <th>Aksi</th>
                        <th>Modul</th>
                        <th>Data terkait</th>
                        <th>Alamat IP</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>
                                <div class="table-primary">{{ $log->created_at?->translatedFormat('d M Y') }}</div>
                                <div class="table-secondary">{{ $log->created_at?->format('H:i:s') }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $log->actorLabel() }}</div>
                                <div class="table-secondary">
                                    @if ($log->user)
                                        {{ '@'.$log->user->username }}
                                    @elseif ($log->user_id)
                                        ID pengguna {{ $log->user_id }}
                                    @else
                                        Aktivitas otomatis
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $log->actionBadgeClass() }}">{{ $log->actionLabel() }}</span>
                            </td>
                            <td>
                                <div class="table-primary">{{ $log->moduleLabel() }}</div>
                                <div class="table-secondary">{{ $log->module_name }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $log->table_name ?: '-' }}</div>
                                <div class="table-secondary">
                                    {{ $log->record_id ? 'ID '.$log->record_id : 'Tidak ada ID khusus' }}
                                </div>
                            </td>
                            <td>
                                <div class="table-primary audit-ip">{{ $log->ip_address ?: '-' }}</div>
                            </td>
                            <td>
                                <a href="{{ route('admin.audit-logs.show', $log) }}" class="action-link">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Tidak ada aktivitas yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="pagination-bar">
                <span>
                    Menampilkan {{ $logs->firstItem() }} sampai {{ $logs->lastItem() }}
                    dari {{ $logs->total() }} aktivitas
                </span>
                <div class="pagination-actions">
                    @if ($logs->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $logs->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif

                    <span class="page-indicator">Halaman {{ $logs->currentPage() }} dari {{ $logs->lastPage() }}</span>

                    @if ($logs->hasMorePages())
                        <a href="{{ $logs->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
