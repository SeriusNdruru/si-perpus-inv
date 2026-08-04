@extends('layouts.public')

@section('title', 'Login Siswa')

@section('content')
<section class="student-login-section">
    <div class="portal-container student-login-grid">
        <div class="student-login-intro">
            <span class="portal-kicker">Akses khusus siswa</span>
            <h1>Masuk dengan email siswa.</h1>
            <p>
                Akun siswa dibuat dan diaktifkan oleh admin perpustakaan. Gunakan email serta password yang diberikan untuk masuk ke dashboard siswa.
            </p>

            <div class="student-login-points">
                <article><span>01</span><div><strong>Ajukan banyak buku</strong><p>Simpan beberapa judul dalam satu pengajuan.</p></div></article>
                <article><span>02</span><div><strong>Pantau pengembalian</strong><p>Lihat tanggal kembali, status buku, dan riwayat transaksi.</p></div></article>
                <article><span>03</span><div><strong>Terima peringatan</strong><p>Notifikasi dashboard dibuat sebelum jatuh tempo.</p></div></article>
            </div>
        </div>

        <div class="student-login-card">
            <div>
                <span class="student-login-icon">S</span>
                <p class="portal-kicker">Login siswa</p>
                <h2>Selamat datang kembali</h2>
                <p>Masukkan email dan password akun siswa.</p>
            </div>

            @if (session('status'))
                <div class="portal-flash portal-flash-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="portal-flash portal-flash-error">
                    <strong>Login siswa gagal.</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('student.login.store') }}" class="student-login-form">
                @csrf
                <label>
                    <span>Email siswa *</span>
                    <input
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        maxlength="150"
                        placeholder="nama@sekolah.sch.id"
                        required
                        autofocus
                    >
                </label>
                <label>
                    <span>Password *</span>
                    <input
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        maxlength="255"
                        required
                    >
                </label>
                <button type="submit" class="portal-button portal-button-primary">Login</button>
            </form>

            <div class="student-login-links">
                <a href="{{ route('student.password.request') }}">Lupa password?</a>
                <p class="student-register-prompt">
                    <span>Belum memiliki akun? Hubungi admin perpustakaan.</span>
                </p>
            </div>
        </div>
    </div>
</section>
@endsection
