@extends('layouts.public')

@section('title', $pageTitle)

@section('content')
<section class="student-login-section password-security-section">
    <div class="portal-container student-login-grid">
        <div class="student-login-intro">
            <span class="portal-kicker">Keamanan akun</span>
            <h1>{{ $heading }}</h1>
            <p>{{ $description }}</p>

            <div class="password-rules-card">
                <strong>Syarat password</strong>
                <ul>
                    <li>Minimal 8 karakter.</li>
                    <li>Memiliki huruf dan angka.</li>
                    <li>Tidak menggunakan password lama yang mudah ditebak.</li>
                    <li>Jangan membagikan password kepada orang lain.</li>
                </ul>
            </div>
        </div>

        <div class="student-login-card">
            <div>
                <span class="student-login-icon">{{ $portal === 'student' ? 'S' : 'A' }}</span>
                <p class="portal-kicker">{{ $portal === 'student' ? 'Akun siswa' : 'Akun pengguna internal' }}</p>
                <h2>Masukkan password baru</h2>
                <p>Pastikan kedua kolom password berisi nilai yang sama.</p>
            </div>

            @if ($errors->any())
                <div class="portal-flash portal-flash-error">
                    <strong>Password belum dapat diperbarui.</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ $submitRoute }}" class="student-login-form">
                @csrf
                <input name="token" type="hidden" value="{{ $token }}">

                <label>
                    <span>Email akun *</span>
                    <input
                        name="email"
                        type="email"
                        value="{{ old('email', $email) }}"
                        autocomplete="email"
                        maxlength="150"
                        required
                    >
                </label>

                <label>
                    <span>Password baru *</span>
                    <input
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        maxlength="255"
                        required
                    >
                </label>

                <label>
                    <span>Konfirmasi password baru *</span>
                    <input
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        maxlength="255"
                        required
                    >
                </label>

                <button type="submit" class="portal-button portal-button-primary">
                    Simpan password baru
                </button>
            </form>

            <div class="student-login-links password-security-links">
                <a href="{{ $backRoute }}">{{ $backLabel }}</a>
            </div>
        </div>
    </div>
</section>
@endsection
