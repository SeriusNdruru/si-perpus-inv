<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard Anggota') | Sistem Perpustakaan</title>
    @include('shared.favicon-links')
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v=104">
</head>
<body class="member-page @yield('body-class')">
    @php
        $memberProfile = $member ?? auth()->user()?->member;
        $unreadCount = $memberProfile
            ? \Illuminate\Support\Facades\DB::table('member_notifications')->where('member_id', $memberProfile->id)->where('is_read', 0)->count()
            : 0;
        $cartCount = collect(session('member.loan_request_cart', []))->unique()->count();
    @endphp

    <div class="member-shell">
        <aside class="member-sidebar" data-member-sidebar>
            <div class="member-sidebar-head">
                <a href="{{ route('dashboard.member') }}" class="member-brand">
                    <span class="member-brand-mark">@include('shared.brand-logo', ['class' => 'brand-logo-image', 'alt' => 'dashboard siswa'])</span>
                    <div>
                        <strong>{{ $systemBrand['institution.name'] ?? 'Perpustakaan' }}</strong>
                        <small>Dashboard siswa</small>
                    </div>
                </a>

                <button
                    type="button"
                    class="member-menu-toggle"
                    aria-label="Buka menu siswa"
                    aria-controls="member-sidebar-menu"
                    aria-expanded="false"
                    data-member-menu-toggle
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>

            <div id="member-sidebar-menu" class="member-sidebar-menu" data-member-sidebar-menu>
                <nav class="member-nav">
                    <a href="{{ route('dashboard.member') }}" class="{{ request()->routeIs('dashboard.member') ? 'active' : '' }}">Dashboard</a>
                    <a href="{{ route('member.books.index') }}" class="{{ request()->routeIs('member.books.index') ? 'active' : '' }}">Katalog buku</a>
                    <a href="{{ route('member.books.cart') }}" class="{{ request()->routeIs('member.books.cart') ? 'active' : '' }}">
                        Keranjang pengajuan
                        @if ($cartCount > 0)<span>{{ $cartCount }}</span>@endif
                    </a>
                    <a href="{{ route('member.loan-requests.index') }}" class="{{ request()->routeIs('member.loan-requests.*') ? 'active' : '' }}">Pengajuan peminjaman</a>
                    <a href="{{ route('member.activity.index') }}" class="{{ request()->routeIs('member.activity.*', 'member.history.visits', 'member.history.books') ? 'active' : '' }}">Aktivitas saya</a>
                    <a href="{{ route('member.history.loans') }}" class="{{ request()->routeIs('member.history.loans', 'member.history.loan-detail') ? 'active' : '' }}">Riwayat peminjaman</a>
                    <a href="{{ route('member.history.fines') }}" class="{{ request()->routeIs('member.history.fines') ? 'active' : '' }}">Denda</a>
                    <a href="{{ route('member.notifications.index') }}" class="{{ request()->routeIs('member.notifications.*') ? 'active' : '' }}">
                        Notifikasi
                        @if ($unreadCount > 0)<span>{{ $unreadCount }}</span>@endif
                    </a>
                </nav>

                <div class="member-sidebar-footer">
                    <form method="POST" action="{{ route('student.logout') }}">
                        @csrf
                        <button type="submit">Keluar</button>
                    </form>
                </div>
            </div>
        </aside>

        <main class="member-main">
            <header class="member-topbar">
                <div>
                    <small>{{ now()->translatedFormat('l, d F Y') }}</small>
                    <h1>@yield('page-title', 'Dashboard Anggota')</h1>
                </div>
                <a href="{{ route('member.profile.show') }}" class="member-profile-chip" title="Buka profil saya">
                    @include('shared.member-avatar', [
                        'member' => $memberProfile,
                        'class' => 'member-profile-chip-avatar',
                        'size' => 128,
                    ])
                    <div>
                        <strong>{{ $memberProfile?->member_name ?? auth()->user()->full_name }}</strong>
                        <small>{{ $memberProfile?->member_code ?? 'Anggota' }}</small>
                    </div>
                </a>
            </header>

            <div class="member-content">
                @if (session('success'))
                    <div class="member-alert member-alert-success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="member-alert member-alert-error">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="member-alert member-alert-error">
                        <strong>Data belum dapat diproses.</strong>
                        <ul>
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
    <div
        id="member-photo-preview-modal"
        class="member-photo-preview-modal"
        role="dialog"
        aria-modal="true"
        aria-hidden="true"
        aria-labelledby="member-photo-preview-caption"
        hidden
    >
        <div class="member-photo-preview-dialog">
            <button
                type="button"
                class="member-photo-preview-close"
                aria-label="Tutup pratinjau foto"
                data-member-photo-preview-close
            >&times;</button>
            <img id="member-photo-preview-image" alt="Pratinjau foto profil">
            <p id="member-photo-preview-caption">Foto profil siswa</p>
        </div>
    </div>

    <script src="{{ asset('js/member-mobile-menu.js') }}?v=104" defer></script>
    <script src="{{ asset('js/image-retry.js') }}?v=74" defer></script>
    <script src="{{ asset('js/member-photo-preview.js') }}?v=92" defer></script>
</body>
</html>
