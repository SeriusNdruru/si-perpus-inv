@extends('layouts.app')

@section('title', 'Detail Anggota')
@section('page-title', 'Detail Anggota Perpustakaan')

@section('content')
    @php
        $statusClass = match ($member->status) {
            'active' => 'badge-success',
            'suspended', 'expired' => 'badge-warning',
            default => 'badge-muted',
        };
    @endphp

    <div class="detail-heading">
        <div class="admin-member-detail-identity">
            @include('shared.member-avatar', [
                'member' => $member,
                'class' => 'admin-member-avatar admin-member-avatar-detail',
                'size' => 320,
                'preview' => true,
                'previewSize' => 1200,
            ])
            <div>
                <p class="eyebrow">{{ $member->member_code }}</p>
                <h2>{{ $member->member_name }}</h2>
                <div class="detail-badges">
                    <span class="badge badge-neutral">{{ $member->typeLabel() }}</span>
                    <span class="badge {{ $statusClass }}">{{ $member->statusLabel() }}</span>
                </div>
                @if ($member->profile_photo_path)
                    <p class="admin-member-photo-help">Klik foto untuk melihat ukuran lebih besar.</p>
                @else
                    <p class="admin-member-photo-help">Anggota belum menambahkan foto profil.</p>
                @endif
            </div>
        </div>
        <div class="detail-actions">
            <a href="{{ route('library.members.index') }}" class="button-secondary">Kembali</a>
            <a href="{{ route('library.members.edit', $member) }}" class="button-primary button-link">Edit anggota</a>
        </div>
    </div>

    <div class="detail-grid">
        <section class="panel detail-card">
            <div class="panel-header"><h2>Informasi Anggota</h2></div>
            <dl class="definition-list">
                <div><dt>Nomor identitas</dt><dd>{{ $member->identity_number ?: '-' }}</dd></div>
                <div><dt>Kelas</dt><dd>{{ $member->department ?: '-' }}</dd></div>
                <div><dt>Telepon</dt><dd>{{ $member->phone ?: '-' }}</dd></div>
                <div><dt>Email</dt><dd>{{ $member->email ?: '-' }}</dd></div>
                <div><dt>Tanggal bergabung</dt><dd>{{ $member->join_date?->translatedFormat('d F Y') }}</dd></div>
                <div><dt>Masa berlaku</dt><dd>{{ $member->expiry_date?->translatedFormat('d F Y') ?? 'Tidak dibatasi' }}</dd></div>
                <div><dt>Dibuat oleh</dt><dd>{{ $member->creator?->full_name ?? '-' }}</dd></div>
                <div><dt>Username akun</dt><dd>{{ $member->user?->username ?? 'Belum dibuat' }}</dd></div>
                <div><dt>Email login</dt><dd>{{ $member->user?->email ?? $member->email ?? '-' }}</dd></div>
                <div><dt>Status akun</dt><dd>{{ $member->user?->status === 'active' ? 'Aktif' : ($member->user ? 'Tidak aktif' : 'Belum dibuat') }}</dd></div>
                <div class="definition-full"><dt>Alamat</dt><dd>{{ $member->address ?: 'Alamat belum diisi.' }}</dd></div>
            </dl>
        </section>

        <section class="panel detail-card">
            <div class="panel-header"><h2>Ringkasan Peminjaman</h2></div>
            <dl class="definition-list">
                <div><dt>Total transaksi</dt><dd>{{ number_format($loanSummary['total']) }}</dd></div>
                <div><dt>Masih aktif</dt><dd>{{ number_format($loanSummary['active']) }}</dd></div>
                <div><dt>Selesai</dt><dd>{{ number_format($loanSummary['completed']) }}</dd></div>
                <div><dt>Total denda tercatat</dt><dd>Rp{{ number_format($loanSummary['fine_total'], 0, ',', '.') }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Kontrol keanggotaan</p>
                <h2>Ubah Status</h2>
            </div>
        </div>
        <form method="POST" action="{{ route('library.members.status', $member) }}" class="member-status-form">
            @csrf
            @method('PATCH')
            <div class="form-field">
                <label for="status">Status anggota</label>
                <select id="status" name="status">
                    <option value="active" @selected($member->status === 'active')>Aktif</option>
                    <option value="suspended" @selected($member->status === 'suspended')>Ditangguhkan</option>
                    <option value="inactive" @selected($member->status === 'inactive')>Tidak aktif</option>
                    <option value="expired" @selected($member->status === 'expired')>Kedaluwarsa</option>
                </select>
            </div>
            <button type="submit" class="button-primary button-link">Simpan status</button>
        </form>
        <div class="inline-notice inline-notice-compact">
            Anggota yang ditangguhkan, tidak aktif, atau kedaluwarsa tidak dapat membuat peminjaman baru.
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Riwayat</p>
                <h2>Transaksi Peminjaman</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Kode transaksi</th>
                        <th>Tanggal pinjam</th>
                        <th>Jatuh tempo</th>
                        <th>Eksemplar</th>
                        <th>Denda</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($loans as $loan)
                        <tr><td class="table-number">{{ (is_object($loans) && method_exists($loans, 'firstItem') && $loans->firstItem() !== null ? $loans->firstItem() : 1) + $loop->index }}</td>
                            <td><a href="{{ route('library.loans.show', $loan) }}" class="action-link"><strong>{{ $loan->loan_code }}</strong></a></td>
                            <td>{{ $loan->loan_date?->translatedFormat('d F Y H:i') }}</td>
                            <td>{{ $loan->default_due_date?->translatedFormat('d F Y') }}</td>
                            <td>{{ number_format((int) $loan->items_count) }}</td>
                            <td>Rp{{ number_format((float) $loan->fine_total, 0, ',', '.') }}</td>
                            <td><span class="badge badge-neutral">{{ ucfirst($loan->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">Anggota ini belum memiliki riwayat peminjaman.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($loans->hasPages())
            <div class="pagination-bar">
                <span>Menampilkan {{ $loans->firstItem() }} sampai {{ $loans->lastItem() }} dari {{ $loans->total() }} transaksi</span>
                <div class="pagination-actions">
                    @if ($loans->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $loans->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif
                    <span class="page-indicator">Halaman {{ $loans->currentPage() }} dari {{ $loans->lastPage() }}</span>
                    @if ($loans->hasMorePages())
                        <a href="{{ $loans->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
