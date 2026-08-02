@extends('layouts.member')

@section('title', 'Profil Saya')
@section('page-title', 'Profil Saya')

@section('content')
@php
    $membershipExpired = $member->expiry_date !== null
        && $member->expiry_date->isBefore(today());

    $membershipStatusClass = $member->status === 'active' && ! $membershipExpired
        ? 'member-profile-status-active'
        : 'member-profile-status-warning';

    $displayPhone = $member->phone ?: $user->phone;
@endphp

<section class="member-profile-hero">
    <div class="member-profile-identity">
        <div class="member-profile-avatar">
            {{ mb_strtoupper(mb_substr($member->member_name, 0, 1)) }}
        </div>

        <div>
            <span class="member-kicker">Identitas anggota perpustakaan</span>
            <h2>{{ $member->member_name }}</h2>
            <p>
                {{ $member->member_code }}
                @if ($member->department)
                    · {{ $member->department }}
                @endif
            </p>

            <div class="member-profile-badges">
                <span class="{{ $membershipStatusClass }}">
                    {{ $membershipExpired ? 'Masa berlaku berakhir' : $member->statusLabel() }}
                </span>
                <span>{{ $member->typeLabel() }}</span>
            </div>
        </div>
    </div>

    <div class="member-profile-email">
        <span>Email login siswa</span>
        <strong>{{ $user->email ?: 'Belum tersedia' }}</strong>
        <small>Email ini digunakan untuk masuk ke dashboard siswa.</small>
    </div>
</section>

<div class="member-stat-grid member-profile-stat-grid">
    <article>
        <span>Transaksi peminjaman</span>
        <strong>{{ number_format($statistics['loan_transactions']) }}</strong>
    </article>
    <article>
        <span>Buku sedang dipinjam</span>
        <strong>{{ number_format($statistics['active_books']) }}</strong>
    </article>
    <article>
        <span>Buku sudah dikembalikan</span>
        <strong>{{ number_format($statistics['returned_books']) }}</strong>
    </article>
    <article>
        <span>Sisa denda</span>
        <strong>Rp{{ number_format($statistics['outstanding_fine'], 0, ',', '.') }}</strong>
    </article>
</div>

<div class="member-profile-grid">
    <section class="member-panel member-profile-panel">
        <div class="member-panel-heading">
            <div>
                <small>Data akun</small>
                <h2>Informasi login</h2>
            </div>
        </div>

        <dl class="member-profile-list">
            <div>
                <dt>Nama lengkap</dt>
                <dd>{{ $member->member_name }}</dd>
            </div>
            <div>
                <dt>Username</dt>
                <dd>{{ $user->username }}</dd>
            </div>
            <div>
                <dt>Email</dt>
                <dd>{{ $user->email ?: '-' }}</dd>
            </div>
            <div>
                <dt>Nomor telepon</dt>
                <dd>{{ $displayPhone ?: '-' }}</dd>
            </div>
            <div>
                <dt>Status akun</dt>
                <dd>{{ $user->status === 'active' ? 'Aktif' : ucfirst($user->status) }}</dd>
            </div>
            <div>
                <dt>Login terakhir</dt>
                <dd>{{ $user->last_login_at?->format('d/m/Y H:i') ?? '-' }}</dd>
            </div>
        </dl>
    </section>

    <section class="member-panel member-profile-panel">
        <div class="member-panel-heading">
            <div>
                <small>Data keanggotaan</small>
                <h2>Identitas perpustakaan</h2>
            </div>
        </div>

        <dl class="member-profile-list">
            <div>
                <dt>Kode anggota</dt>
                <dd>{{ $member->member_code }}</dd>
            </div>
            <div>
                <dt>Nomor identitas siswa</dt>
                <dd>{{ $member->identity_number ?: '-' }}</dd>
            </div>
            <div>
                <dt>Kelas</dt>
                <dd>{{ $member->department ?: '-' }}</dd>
            </div>
            <div>
                <dt>Jenis anggota</dt>
                <dd>{{ $member->typeLabel() }}</dd>
            </div>
            <div>
                <dt>Tanggal bergabung</dt>
                <dd>{{ $member->join_date?->format('d/m/Y') ?? '-' }}</dd>
            </div>
            <div>
                <dt>Masa berlaku sampai</dt>
                <dd>{{ $member->expiry_date?->format('d/m/Y') ?? 'Tidak dibatasi' }}</dd>
            </div>
        </dl>
    </section>
</div>

<section class="member-panel member-profile-address">
    <div class="member-panel-heading">
        <div>
            <small>Kontak dan alamat</small>
            <h2>Informasi siswa</h2>
        </div>
    </div>

    <div class="member-profile-address-body">
        <div>
            <span>Alamat</span>
            <p>{{ $member->address ?: 'Alamat belum dicantumkan.' }}</p>
        </div>

        <div class="member-profile-note">
            <strong>Perlu memperbaiki data?</strong>
            <p>
                Hubungi petugas perpustakaan apabila nama, nomor identitas,
                kelas, email, nomor telepon, atau alamat tidak sesuai.
            </p>
        </div>
    </div>
</section>
@endsection
