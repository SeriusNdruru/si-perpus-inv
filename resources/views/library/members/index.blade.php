@extends('layouts.app')

@section('title', 'Anggota Perpustakaan')
@section('page-title', 'Anggota Perpustakaan')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card">
            <span>Total anggota</span>
            <strong>{{ number_format($summary['total']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Anggota aktif</span>
            <strong>{{ number_format($summary['active']) }}</strong>
        </article>
        <article class="stat-card stat-warning">
            <span>Ditangguhkan</span>
            <strong>{{ number_format($summary['suspended']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Masa berlaku habis</span>
            <strong>{{ number_format($summary['expired']) }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Keanggotaan</p>
                <h2>Daftar Anggota</h2>
            </div>
            <a href="{{ route('library.members.create') }}" class="button-primary button-link">Tambah anggota dan akun</a>
        </div>

        <form method="GET" action="{{ route('library.members.index') }}" class="filter-bar filter-bar-members">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Kode, nama, identitas, kelas, telepon, atau email"
                >
            </div>
            <div class="filter-field">
                <label for="member_type">Jenis anggota</label>
                <select id="member_type" name="member_type">
                    <option value="">Semua jenis</option>
                    @foreach (\App\Models\Member::typeOptions() as $typeValue => $typeLabel)
                        <option value="{{ $typeValue }}" @selected(request('member_type') === $typeValue)>{{ $typeLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua status</option>
                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                    <option value="suspended" @selected(request('status') === 'suspended')>Ditangguhkan</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Tidak aktif</option>
                    <option value="expired" @selected(request('status') === 'expired')>Kedaluwarsa</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="button-primary">Terapkan</button>
                <a href="{{ route('library.members.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Kode</th>
                        <th>Anggota</th>
                        <th>Jenis</th>
                        <th>Kontak</th>
                        <th>Masa berlaku</th>
                        <th>Pinjaman</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        @php
                            $statusClass = match ($member->status) {
                                'active' => 'badge-success',
                                'suspended', 'expired' => 'badge-warning',
                                default => 'badge-muted',
                            };
                        @endphp
                        <tr><td class="table-number">{{ (is_object($members) && method_exists($members, 'firstItem') && $members->firstItem() !== null ? $members->firstItem() : 1) + $loop->index }}</td>
                            <td><strong>{{ $member->member_code }}</strong></td>
                            <td>
                                <div class="table-primary">{{ $member->member_name }}</div>
                                <div class="table-secondary">
                                    {{ $member->identity_number ?: 'Tanpa nomor identitas' }}
                                    @if ($member->department)
                                        · {{ $member->department }}
                                    @endif
                                </div>
                            </td>
                            <td>{{ $member->typeLabel() }}</td>
                            <td>
                                <div class="table-primary">{{ $member->phone ?: '-' }}</div>
                                <div class="table-secondary">{{ $member->email ?: 'Email belum diisi' }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $member->join_date?->format('d-m-Y') }}</div>
                                <div class="table-secondary">
                                    Berakhir: {{ $member->expiry_date?->format('d-m-Y') ?? 'Tidak dibatasi' }}
                                </div>
                            </td>
                            <td>
                                <div class="table-primary">{{ number_format((int) $member->active_loans) }} aktif</div>
                                <div class="table-secondary">{{ number_format((int) $member->total_loans) }} total transaksi</div>
                            </td>
                            <td><span class="badge {{ $statusClass }}">{{ $member->statusLabel() }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('library.members.show', $member) }}" class="action-link">Detail</a>
                                    <a href="{{ route('library.members.edit', $member) }}" class="action-link">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="empty-state">Belum ada anggota yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($members->hasPages())
            <div class="pagination-bar">
                <span>
                    Menampilkan {{ $members->firstItem() }} sampai {{ $members->lastItem() }}
                    dari {{ $members->total() }} anggota
                </span>
                <div class="pagination-actions">
                    @if ($members->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $members->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif
                    <span class="page-indicator">Halaman {{ $members->currentPage() }} dari {{ $members->lastPage() }}</span>
                    @if ($members->hasMorePages())
                        <a href="{{ $members->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
