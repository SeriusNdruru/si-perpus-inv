<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Audit Inventaris') | Sistem Inventaris</title>
    @include('shared.favicon-links')
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v=116">
</head>
<body class="portal-page inventory-audit-page">
    <header class="portal-header inventory-audit-header">
        <div class="portal-container inventory-audit-nav">
            <a
                href="{{ \Illuminate\Support\Facades\Route::has('public.inventory.audit') ? route('public.inventory.audit') : url('/inventaris/audit') }}"
                class="portal-brand inventory-audit-brand"
            >
                <span class="portal-brand-mark">@include('shared.brand-logo', ['class' => 'brand-logo-image', 'alt' => 'audit inventaris'])</span>
                <div>
                    <strong>{{ $systemBrand['institution.name'] ?? 'Sekolah' }}</strong>
                    <small>Pemeriksaan kondisi dan lokasi setiap aset</small>
                </div>
            </a>

            <div class="inventory-audit-header-badge">
                Dashboard Audit
            </div>
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

    <footer class="portal-footer inventory-audit-footer">
        <div class="portal-container inventory-audit-footer-grid">
            <div>
                <div class="portal-brand portal-brand-footer inventory-audit-brand">
                    <span class="portal-brand-mark">@include('shared.brand-logo', ['class' => 'brand-logo-image', 'alt' => 'audit inventaris'])</span>
                    <div>
                        <strong>{{ $systemBrand['institution.name'] ?? 'Sekolah' }}</strong>
                        <small>Dashboard audit berdiri sendiri</small>
                    </div>
                </div>
                <p>{{ $systemBrand['institution.address'] ?? '-' }}</p>
            </div>

            <div class="inventory-audit-footer-info">
                <strong>Informasi sekolah</strong>
                <span>{{ $systemBrand['institution.phone'] ?: '-' }}</span>
                <span>{{ $systemBrand['institution.email'] ?? '-' }}</span>
            </div>
        </div>

        <div class="portal-container portal-footer-bottom">
            <span>© {{ date('Y') }} {{ $systemBrand['institution.name'] ?? 'Sekolah' }}</span>
            <span>Audit inventaris sekolah</span>
        </div>
    </footer>
    @include('shared.photo-preview-modal')
    <script src="{{ asset('js/portal-photo-preview.js') }}?v=67" defer></script>
    <script src="{{ asset('js/image-retry.js') }}?v=67" defer></script>
</body>
</html>
