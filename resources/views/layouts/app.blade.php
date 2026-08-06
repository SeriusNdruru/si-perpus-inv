<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $browserSystemName = 'Sistem Inventaris dan Perpustakaan';
        $browserTitleUser = auth()->user();

        if ($browserTitleUser && ! $browserTitleUser->hasRole('SUPER_ADMIN')) {
            if ($browserTitleUser->hasRole('INVENTORY_ADMIN')) {
                $browserSystemName = 'Sistem Inventaris';
            } elseif ($browserTitleUser->hasAnyRole(['LIBRARY_ADMIN', 'LIBRARY_OFFICER'])) {
                $browserSystemName = 'Sistem Perpustakaan';
            }
        }
    @endphp
    <title>@yield('title', 'Dashboard') | {{ $browserSystemName }}</title>
    @include('shared.favicon-links')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=108">
</head>
<body class="app-page">
    @php
        $currentUser = auth()->user();
        $defaultDashboardRoute = $currentUser->dashboardRouteName();
        $isSuperAdmin = $currentUser->hasRole('SUPER_ADMIN');
        $isInventoryAdmin = $currentUser->hasRole('INVENTORY_ADMIN');
        $isLibraryAdmin = $currentUser->hasAnyRole(['LIBRARY_ADMIN', 'LIBRARY_OFFICER']);
        $isManager = $currentUser->hasRole('MANAGER');

        $superAdminArea = 'system';

        if ($isSuperAdmin) {
            $storedSuperAdminArea = session('super_admin_area', 'system');
            $superAdminArea = in_array($storedSuperAdminArea, ['system', 'inventory', 'library'], true)
                ? $storedSuperAdminArea
                : 'system';

            if (request()->routeIs('dashboard.super-admin', 'admin.*')) {
                $superAdminArea = 'system';
            } elseif (request()->routeIs(
                'dashboard.inventory',
                'categories.*',
                'units.*',
                'suppliers.*',
                'locations.*',
                'inventory.*',
                'reports.inventory',
                'reports.inventory.*'
            )) {
                $superAdminArea = 'inventory';
            } elseif (request()->routeIs(
                'dashboard.library',
                'library.*',
                'reports.collection',
                'reports.collection.*',
                'reports.loans',
                'reports.loans.*',
                'reports.fines',
                'reports.fines.*',
                'reports.members',
                'reports.members.*',
                'reports.reservations',
                'reports.reservations.*',
                'reports.library-visits',
                'reports.library-visits.*',
                'reports.frequent-visitors',
                'reports.frequent-visitors.*',
                'reports.loan-records',
                'reports.loan-records.*'
            )) {
                $superAdminArea = 'library';
            }
        }

        $isSystemArea = $isSuperAdmin && $superAdminArea === 'system';
        $isInventoryArea = $isInventoryAdmin || ($isSuperAdmin && $superAdminArea === 'inventory');
        $isLibraryArea = $isLibraryAdmin || ($isSuperAdmin && $superAdminArea === 'library');

        $dashboardRoute = match (true) {
            $isSuperAdmin && $superAdminArea === 'inventory' => 'dashboard.inventory',
            $isSuperAdmin && $superAdminArea === 'library' => 'dashboard.library',
            default => $defaultDashboardRoute,
        };

        $sidebarContextLabel = match (true) {
            $isSuperAdmin && $superAdminArea === 'inventory' => 'Super Admin · Area Inventaris',
            $isSuperAdmin && $superAdminArea === 'library' => 'Super Admin · Area Perpustakaan',
            default => $currentUser->primaryRoleLabel(),
        };
    @endphp

    <div class="app-shell">
        <aside class="sidebar" data-admin-sidebar>
            <div class="sidebar-mobile-head">
                <a href="{{ route($dashboardRoute) }}" class="sidebar-brand">
                <span class="sidebar-logo">@include('shared.brand-logo', ['class' => 'brand-logo-image', 'alt' => 'sidebar'])</span>
                <span>
                    <strong>{{ $systemBrand['institution.name'] ?? 'Rius Library' }}</strong>
                    <small>{{ $sidebarContextLabel }}</small>
                </span>
                </a>

                <button
                    type="button"
                    class="admin-menu-toggle"
                    aria-label="Buka menu admin"
                    aria-controls="admin-sidebar-menu"
                    aria-expanded="false"
                    data-admin-menu-toggle
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>

            <div id="admin-sidebar-menu" class="sidebar-menu" data-admin-sidebar-menu>
                <nav class="sidebar-nav" aria-label="Menu utama">
                <a href="{{ route($dashboardRoute) }}" class="{{ request()->routeIs($dashboardRoute) ? 'active' : '' }}">Dashboard</a>

                @if ($isSuperAdmin && ! $isSystemArea)
                    <p class="sidebar-section">Mode Super Admin</p>
                    <a href="{{ route('dashboard.super-admin') }}">Kembali ke Super Admin</a>
                @endif

                @if ($isSystemArea)
                    <p class="sidebar-section">Administrasi</p>
                    <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Pengguna Sistem</a>
                    <a href="{{ route('admin.settings.edit') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">Pengaturan Sistem</a>
                    <a href="{{ route('admin.email-notifications.index') }}" class="{{ request()->routeIs('admin.email-notifications.*') ? 'active' : '' }}">Email & Notifikasi</a>
                    <a href="{{ route('admin.audit-logs.index') }}" class="{{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">Riwayat Aktivitas</a>
                    <a href="{{ route('admin.database-backups.index') }}" class="{{ request()->routeIs('admin.database-backups.*') ? 'active' : '' }}">Backup Database</a>
                    <a href="{{ route('admin.system-readiness.index') }}" class="{{ request()->routeIs('admin.system-readiness.*') ? 'active' : '' }}">Pengujian Sistem</a>
                    <a href="{{ route('admin.acceptance-tests.index') }}" class="{{ request()->routeIs('admin.acceptance-tests.*') ? 'active' : '' }}">Uji Akses & Alur</a>
                @endif

                @if ($isInventoryArea)
                    <p class="sidebar-section">Master Inventaris</p>
                    <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories.*') ? 'active' : '' }}">Kategori</a>
                    <a href="{{ route('units.index') }}" class="{{ request()->routeIs('units.*') ? 'active' : '' }}">Satuan</a>
                    <a href="{{ route('suppliers.index') }}" class="{{ request()->routeIs('suppliers.*') ? 'active' : '' }}">Supplier</a>
                    <a href="{{ route('locations.index') }}" class="{{ request()->routeIs('locations.*') ? 'active' : '' }}">Lokasi</a>

                    <p class="sidebar-section">Inventaris</p>
                    <a href="{{ route('inventory.items.index') }}" class="{{ request()->routeIs('inventory.items.*') ? 'active' : '' }}">Data Barang</a>
                    <a href="{{ route('inventory.deleted-items.index') }}" class="{{ request()->routeIs('inventory.deleted-items.*') ? 'active' : '' }}">Daftar Hapus</a>
                    <a href="{{ route('inventory.stock-opnames.index') }}" class="{{ request()->routeIs('inventory.stock-opnames.*') ? 'active' : '' }}">Stock Opname</a>
                    <a href="{{ route('inventory.maintenance-records.index') }}" class="{{ request()->routeIs('inventory.maintenance-records.*') ? 'active' : '' }}">Pemeliharaan Aset</a>
                    <a href="{{ route('inventory.disposals.index') }}" class="{{ request()->routeIs('inventory.disposals.*') ? 'active' : '' }}">Penghapusan Aset</a>
                    <a
                        href="{{ route('inventory.public-damage-reports.index') }}"
                        class="sidebar-link-with-badge {{ request()->routeIs('inventory.public-damage-reports.*') ? 'active' : '' }}"
                    >
                        <span>Laporan Kerusakan Publik</span>
                        @if (($newPublicDamageReportCount ?? 0) > 0)
                            <span
                                class="sidebar-notification-badge"
                                title="{{ number_format($newPublicDamageReportCount) }} laporan baru"
                                aria-label="{{ number_format($newPublicDamageReportCount) }} laporan kerusakan baru"
                            >
                                {{ $newPublicDamageReportCount > 99 ? '99+' : $newPublicDamageReportCount }}
                            </span>
                        @endif
                    </a>
                @endif

                @if ($isLibraryArea)
                    <p class="sidebar-section">Perpustakaan</p>
                    <a href="{{ route('library.books.index') }}" class="{{ request()->routeIs('library.books.*') ? 'active' : '' }}">Buku Baru & Katalog</a>
                    <a href="{{ route('library.shelves.index') }}" class="{{ request()->routeIs('library.shelves.*') ? 'active' : '' }}">Rak Perpustakaan</a>
                    <a href="{{ route('library.shelf-assignments.index') }}" class="{{ request()->routeIs('library.shelf-assignments.*') ? 'active' : '' }}">Penempatan Buku</a>
                    <a href="{{ route('library.members.index') }}" class="{{ request()->routeIs('library.members.*') ? 'active' : '' }}">Anggota</a>
                    <a href="{{ route('library.visits.index') }}" class="{{ request()->routeIs('library.visits.*') ? 'active' : '' }}">Kunjungan Siswa</a>
                    <a href="{{ route('library.loans.index') }}" class="{{ request()->routeIs('library.loans.*') ? 'active' : '' }}">Peminjaman</a>
                    <a href="{{ route('library.returns.index') }}" class="{{ request()->routeIs('library.returns.*') ? 'active' : '' }}">Pengembalian</a>
                    <a href="{{ route('library.fines.index') }}" class="{{ request()->routeIs('library.fines.*') ? 'active' : '' }}">Denda</a>
                    <a href="{{ route('library.reservations.index') }}" class="{{ request()->routeIs('library.reservations.*') ? 'active' : '' }}">Reservasi</a>
                    <a
                        href="{{ route('library.loan-requests.index') }}"
                        class="sidebar-link-with-badge {{ request()->routeIs('library.loan-requests.*') ? 'active' : '' }}"
                    >
                        <span>Pengajuan Online</span>
                        @if (($newOnlineLoanRequestCount ?? 0) > 0)
                            <span
                                class="sidebar-notification-badge"
                                title="{{ number_format($newOnlineLoanRequestCount) }} pengajuan baru"
                                aria-label="{{ number_format($newOnlineLoanRequestCount) }} pengajuan online baru"
                            >
                                {{ $newOnlineLoanRequestCount > 99 ? '99+' : $newOnlineLoanRequestCount }}
                            </span>
                        @endif
                    </a>
                    <a href="{{ route('library.contact-messages.index') }}" class="{{ request()->routeIs('library.contact-messages.*') ? 'active' : '' }}">Pesan Kontak</a>
                @endif

                @if ($isInventoryArea || $isLibraryArea || $isManager)
                    <p class="sidebar-section">Informasi</p>
                    <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">Laporan Terpadu</a>
                @endif
                </nav>

                <div class="sidebar-user">
                    <span class="avatar">@include('shared.brand-logo', ['class' => 'brand-logo-image', 'width' => 38, 'height' => 38, 'alt' => 'profil admin'])</span>
                    <div>
                        <strong>{{ $currentUser->full_name }}</strong>
                        <small>{{ $currentUser->primaryRoleLabel() }}</small>
                    </div>
                </div>
            </div>
        </aside>

        <main class="app-main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">{{ now()->translatedFormat('l, d F Y') }}</p>
                    <h1>@yield('page-title', 'Dashboard')</h1>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="button-secondary">Keluar</button>
                </form>
            </header>

            <section class="content-area">
                @if (session('success'))
                    <div class="alert alert-success content-alert">{{ session('success') }}</div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger content-alert">{{ session('error') }}</div>
                @endif

                @yield('content')
            </section>
        </main>
    </div>
    @include('shared.photo-preview-modal')
    <script src="{{ asset('js/admin-mobile-menu.js') }}?v=108" defer></script>
    <script src="{{ asset('js/portal-photo-preview.js') }}?v=93" defer></script>
    <script src="{{ asset('js/image-retry.js') }}?v=67" defer></script>
</body>
</html>
