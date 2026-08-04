<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login Pengguna | {{ $systemBrand['application.name'] ?? config('app.name') }}</title>
    @include('shared.favicon-links')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=62">
    <script src="{{ asset('js/login.js') }}" defer></script>
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-visual" aria-labelledby="application-title">
            <div class="brand-mark" aria-hidden="true">
                @include('shared.brand-logo', ['class' => 'brand-logo-image', 'width' => 64, 'height' => 64, 'alt' => 'login'])
            </div>
            <div>
                <p class="eyebrow">{{ $systemBrand['institution.name'] ?? 'Sistem Terintegrasi' }}</p>
                <h1 id="application-title">{{ $systemBrand['application.name'] ?? 'Inventaris dan Perpustakaan' }}</h1>
                <p class="visual-copy">
                    Satu pusat data untuk barang, buku, eksemplar, rak, anggota, dan transaksi peminjaman.
                </p>
            </div>
            <div class="visual-points" aria-label="Fitur utama">
                <span>Buku otomatis dari inventaris</span>
                <span>Hak akses berbasis peran</span>
                <span>Riwayat data terpusat</span>
            </div>
        </section>

        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-card">
                <div class="login-heading">
                    <p class="eyebrow">Login pengguna internal</p>
                    <h2 id="login-title">Admin, guru, kepala sekolah, dan staf</h2>
                    </div>

                @if (session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <strong>Login gagal.</strong>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="login-form" novalidate>
                    @csrf

                    <div class="field-group">
                        <label for="login">Username atau email</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M20 21a8 8 0 0 0-16 0"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <input
                                id="login"
                                name="login"
                                type="text"
                                value="{{ old('login') }}"
                                autocomplete="username"
                                maxlength="150"
                                required
                                autofocus
                                aria-invalid="{{ $errors->has('login') ? 'true' : 'false' }}"
                                placeholder="contoh: admin"
                            >
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="4" y="10" width="16" height="11" rx="2"/>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                            </svg>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                maxlength="255"
                                required
                                placeholder="Masukkan password"
                            >
                            <button class="password-toggle" type="button" data-password-toggle aria-label="Tampilkan password">
                                <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="m3 3 18 18"/>
                                    <path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-2.1 2.8M6.6 6.7C3.5 8.4 2 12 2 12s3.5 6 10 6c1.5 0 2.8-.3 4-.7"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="button-primary">Login</button>
                </form>

            </div>
        </section>
    </main>
</body>
</html>
