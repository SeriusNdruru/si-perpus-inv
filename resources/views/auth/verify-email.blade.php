@extends('layouts.public')

@section('title', 'Verifikasi Email Siswa')

@section('content')
<section class="portal-page-hero portal-page-hero-register student-verification-hero">
    <div class="portal-container">
        <span class="portal-kicker">Aktivasi akun siswa</span>
        <h1>Verifikasi email siswa</h1>
        <p>Akun dapat digunakan setelah tautan verifikasi pada email dibuka.</p>
    </div>
</section>

<section class="portal-section">
    <div class="portal-container student-verification-grid">
        <article class="student-verification-card">
            <span class="student-verification-icon">✉</span>
            <h2>Periksa kotak masuk</h2>
            <p>
                Tautan aktivasi berlaku selama 60 menit. Periksa juga folder spam atau promosi
                apabila email belum terlihat.
            </p>
            <ol>
                <li>Buka email verifikasi dari sistem perpustakaan.</li>
                <li>Tekan tombol <strong>Verifikasi Email</strong>.</li>
                <li>Akun langsung aktif dan siswa masuk ke dashboard.</li>
            </ol>
        </article>

        <form method="POST" action="{{ route('student.verification.resend') }}" class="portal-form student-verification-form">
            @csrf
            <h2>Kirim ulang tautan</h2>
            <p>Masukkan email yang digunakan pada saat pendaftaran.</p>

            <label>
                <span>Email siswa *</span>
                <input
                    name="email"
                    type="email"
                    maxlength="150"
                    value="{{ old('email', $verificationEmail) }}"
                    autocomplete="email"
                    required
                >
            </label>

            <button type="submit" class="portal-button portal-button-primary">
                Kirim ulang verifikasi
            </button>

            <a href="{{ route('student.login') }}" class="student-verification-login-link">
                Kembali ke login siswa
            </a>
        </form>
    </div>
</section>
@endsection
