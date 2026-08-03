<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal') | {{ $systemBrand['application.name'] ?? config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v=56">
</head>
<body class="portal-page">
    <header class="portal-header">
        <div class="portal-container portal-nav">
            <a href="{{ \Illuminate\Support\Facades\Route::has('public.home') ? route('public.home') : url('/perpustakaan') }}" class="portal-brand">
                <span>{{ $systemBrand['application.short_name'] ?? 'SIP' }}</span>
                <div>
                    <strong>{{ $systemBrand['institution.name'] ?? 'Perpustakaan' }}</strong>
                    <small>Portal layanan perpustakaan</small>
                </div>
            </a>

            <button class="portal-menu-button" type="button" onclick="document.querySelector('.portal-links').classList.toggle('is-open')" aria-label="Buka menu">☰</button>

            <nav class="portal-links">
                <a href="{{ route('public.home') }}" class="{{ request()->routeIs('public.home') ? 'active' : '' }}">Home</a>
                <a href="{{ route('public.catalog') }}" class="{{ request()->routeIs('public.catalog') ? 'active' : '' }}">Katalog</a>
                <a href="{{ route('public.about') }}" class="{{ request()->routeIs('public.about') ? 'active' : '' }}">Tentang</a>
                <a href="{{ route('public.contact') }}" class="{{ request()->routeIs('public.contact') ? 'active' : '' }}">Kontak</a>

                @auth
                    <a href="{{ auth()->user()->hasRole('MEMBER') ? route('dashboard.member') : route('dashboard') }}" class="portal-button portal-button-soft">Dashboard</a>
                @else
                    <a href="{{ route('student.login') }}" class="portal-button portal-button-soft">Login siswa</a>
                    <a href="{{ route('register') }}" class="portal-button portal-button-primary">Daftar siswa</a>
                @endauth
            </nav>
        </div>
    </header>

    @if (session('success'))
        <div class="portal-container portal-flash portal-flash-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="portal-container portal-flash portal-flash-error">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="portal-container portal-flash portal-flash-error">
            <strong>Data belum dapat diproses.</strong>
            <ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <main>
        @yield('content')
    </main>

    <footer class="portal-footer">
        <div class="portal-container portal-footer-grid">
            <div>
                <div class="portal-brand portal-brand-footer">
                    <span>{{ $systemBrand['application.short_name'] ?? 'SIP' }}</span>
                    <div>
                        <strong>{{ $systemBrand['institution.name'] ?? 'Perpustakaan' }}</strong>
                        <small>{{ $systemBrand['application.name'] ?? 'Sistem Inventaris dan Perpustakaan' }}</small>
                    </div>
                </div>
                <p>{{ $systemBrand['institution.address'] ?? '-' }}</p>
            </div>
            <div>
                <strong>Navigasi</strong>
                <a href="{{ route('public.catalog') }}">Katalog buku</a>
                <a href="{{ route('public.about') }}">Tentang perpustakaan</a>
                <a href="{{ route('public.contact') }}">Kontak pengelola</a>
            </div>
            <div>
                <strong>Kontak</strong>
                <span>{{ $systemBrand['institution.phone'] ?: '-' }}</span>
                <span>{{ $systemBrand['institution.email'] ?: '-' }}</span>
                <span>{{ $systemBrand['portal.opening_hours'] ?? '-' }}</span>
            </div>
        </div>
        <div class="portal-container portal-footer-bottom">
            <span>© {{ date('Y') }} {{ $systemBrand['institution.name'] ?? 'Perpustakaan' }}</span>
            <span>Layanan perpustakaan untuk siswa dan masyarakat sekolah</span>
        </div>
    </footer>
    <script src="{{ asset('js/image-retry.js') }}?v=57" defer></script>
</body>
</html>
