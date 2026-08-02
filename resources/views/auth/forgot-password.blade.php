@extends('layouts.public')

@section('title', $pageTitle)

@section('content')
<section class="student-login-section password-security-section">
    <div class="portal-container student-login-grid">
        <div class="student-login-intro">
            <span class="portal-kicker">Keamanan akun</span>
            <h1>{{ $heading }}</h1>
            <p>{{ $description }}</p>

            <div class="student-login-points">
                <article>
                    <span>01</span>
                    <div>
                        <strong>Tautan pribadi</strong>
                        <p>Tautan hanya dikirim ke email akun yang tersimpan.</p>
                    </div>
                </article>
                <article>
                    <span>02</span>
                    <div>
                        <strong>Berlaku 60 menit</strong>
                        <p>Tautan kedaluwarsa otomatis dan tidak dapat dipakai kembali setelah berhasil.</p>
                    </div>
                </article>
                <article>
                    <span>03</span>
                    <div>
                        <strong>Sesi lama berakhir</strong>
                        <p>Perangkat yang masih login akan diminta masuk kembali setelah password berubah.</p>
                    </div>
                </article>
            </div>
        </div>

        <div class="student-login-card">
            <div>
                <span class="student-login-icon">{{ $portal === 'student' ? 'S' : 'A' }}</span>
                <p class="portal-kicker">{{ $portal === 'student' ? 'Akun siswa' : 'Akun pengguna internal' }}</p>
                <h2>Kirim tautan reset</h2>
                <p>Masukkan email yang digunakan pada akun.</p>
            </div>

            @if (session('status'))
                <div class="portal-flash portal-flash-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="portal-flash portal-flash-error">
                    <strong>Permintaan belum dapat diproses.</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ $submitRoute }}" class="student-login-form">
                @csrf
                <label>
                    <span>Email akun *</span>
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

                <button type="submit" class="portal-button portal-button-primary">
                    Kirim tautan reset password
                </button>
            </form>

            <div class="student-login-links password-security-links">
                <a href="{{ $backRoute }}">{{ $backLabel }}</a>
            </div>
        </div>
    </div>
</section>
@endsection
