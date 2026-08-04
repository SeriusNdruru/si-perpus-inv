<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inventaris Umum') | {{ $systemBrand['institution.name'] ?? config('app.name') }}</title>
    @include('shared.favicon-links')
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v=62">
</head>
<body class="portal-page inventory-public-page inventory-general-page">
    <header class="portal-header inventory-public-header">
        <div class="portal-container portal-nav">
            <a
                href="{{ \Illuminate\Support\Facades\Route::has('public.inventory.general') ? route('public.inventory.general') : url('/inventaris/umum') }}"
                class="portal-brand inventory-public-brand"
            >
                <span class="portal-brand-mark">@include('shared.brand-logo', ['class' => 'brand-logo-image', 'alt' => 'inventaris umum'])</span>
                <div>
                    <strong>Inventaris Umum {{ $systemBrand['institution.name'] ?? 'Sekolah' }}</strong>
                    <small>Informasi barang, buku, dan laporan kerusakan</small>
                </div>
            </a>

            <button
                class="portal-menu-button"
                type="button"
                data-portal-menu-toggle
                aria-controls="inventory-general-links"
                aria-expanded="false"
                aria-label="Buka menu"
            ><span aria-hidden="true">☰</span></button>

            <nav id="inventory-general-links" class="portal-links inventory-public-links inventory-general-links" data-portal-menu>
                <a
                    href="{{ \Illuminate\Support\Facades\Route::has('public.inventory.general') ? route('public.inventory.general') : url('/inventaris/umum') }}"
                    class="{{ request()->routeIs('public.inventory.general') ? 'active' : '' }}"
                >
                    Dashboard Umum
                </a>

                <a
                    href="{{ \Illuminate\Support\Facades\Route::has('public.inventory.report-damage') ? route('public.inventory.report-damage') : url('/inventaris/lapor-kerusakan') }}"
                    class="{{ request()->routeIs('public.inventory.report-damage*') ? 'active' : '' }}"
                >
                    Lapor Kerusakan
                </a>
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
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <main>
        @yield('content')
    </main>

    <footer class="portal-footer inventory-public-footer">
        <div class="portal-container portal-footer-grid inventory-general-footer-grid">
            <div>
                <div class="portal-brand portal-brand-footer inventory-public-brand">
                    <span class="portal-brand-mark">@include('shared.brand-logo', ['class' => 'brand-logo-image', 'alt' => 'inventaris umum'])</span>
                    <div>
                        <strong>Inventaris Umum {{ $systemBrand['institution.name'] ?? 'Sekolah' }}</strong>
                        <small>Informasi inventaris sekolah tanpa login</small>
                    </div>
                </div>
                <p>{{ $systemBrand['institution.address'] ?? '-' }}</p>
            </div>

            <div>
                <strong>Layanan inventaris umum</strong>
                <a href="{{ \Illuminate\Support\Facades\Route::has('public.inventory.general') ? route('public.inventory.general') : url('/inventaris/umum') }}">
                    Dashboard umum
                </a>
                <a href="{{ \Illuminate\Support\Facades\Route::has('public.inventory.report-damage') ? route('public.inventory.report-damage') : url('/inventaris/lapor-kerusakan') }}">
                    Lapor kerusakan
                </a>
            </div>

            <div>
                <strong>Informasi sekolah</strong>
                <span>{{ $systemBrand['institution.phone'] ?: '-' }}</span>
                <span>{{ $systemBrand['institution.email'] ?? '-' }}</span>
            </div>
        </div>

        <div class="portal-container portal-footer-bottom">
            <span>© {{ date('Y') }} {{ $systemBrand['institution.name'] ?? 'Sekolah' }}</span>
            <span>Dashboard inventaris umum sekolah</span>
        </div>
    </footer>
    @include('shared.photo-preview-modal')
    <script src="{{ asset('js/portal-menu.js') }}?v=61" defer></script>
    <script src="{{ asset('js/portal-photo-preview.js') }}?v=48" defer></script>
    <script src="{{ asset('js/image-retry.js') }}?v=57" defer></script>
</body>
</html>
