@php
    $reportUser = auth()->user();
    $canInventoryReports = $reportUser->hasAnyRole(['SUPER_ADMIN', 'INVENTORY_ADMIN', 'MANAGER']);
    $canLibraryReports = $reportUser->hasAnyRole(['SUPER_ADMIN', 'LIBRARY_ADMIN', 'LIBRARY_OFFICER', 'MANAGER']);
@endphp

<nav class="report-tabs" aria-label="Jenis laporan">
    <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.index') ? 'active' : '' }}">Ringkasan</a>
    @if ($canInventoryReports)
        <a href="{{ route('reports.inventory') }}" class="{{ request()->routeIs('reports.inventory*') ? 'active' : '' }}">Inventaris</a>
    @endif
    @if ($canLibraryReports)
        <a href="{{ route('reports.collection') }}" class="{{ request()->routeIs('reports.collection*') ? 'active' : '' }}">Koleksi Buku</a>
        <a href="{{ route('reports.loans') }}" class="{{ request()->routeIs('reports.loans*') ? 'active' : '' }}">Peminjaman</a>
        <a href="{{ route('reports.loan-records') }}" class="{{ request()->routeIs('reports.loan-records*') ? 'active' : '' }}">Riwayat Peminjaman</a>
        <a href="{{ route('reports.library-visits') }}" class="{{ request()->routeIs('reports.library-visits*') ? 'active' : '' }}">Riwayat Kunjungan</a>
        <a href="{{ route('reports.frequent-visitors') }}" class="{{ request()->routeIs('reports.frequent-visitors*') ? 'active' : '' }}">Peringkat Kunjungan</a>
        <a href="{{ route('reports.fines') }}" class="{{ request()->routeIs('reports.fines*') ? 'active' : '' }}">Denda</a>
        <a href="{{ route('reports.members') }}" class="{{ request()->routeIs('reports.members*') ? 'active' : '' }}">Anggota</a>
        <a href="{{ route('reports.reservations') }}" class="{{ request()->routeIs('reports.reservations*') ? 'active' : '' }}">Reservasi</a>
    @endif
</nav>
