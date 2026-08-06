@extends('layouts.app')

@section('title', auth()->user()->hasRole('SUPER_ADMIN') ? 'Area Admin Perpustakaan' : 'Dashboard Perpustakaan')
@section('page-title', auth()->user()->hasRole('SUPER_ADMIN') ? 'Area Admin Perpustakaan' : 'Dashboard Admin Perpustakaan')

@section('content')
    <div class="role-banner">
        <div>
            @if (auth()->user()->hasRole('SUPER_ADMIN'))
                <p class="eyebrow">Mode area Super Admin</p>
                <h2>Pengelolaan perpustakaan</h2>
                <p>Super Admin sedang membuka area kerja Admin Perpustakaan. Akun, peran, dan hak akses tetap sebagai Super Admin.</p>
            @else
                <p class="eyebrow">Area kerja khusus</p>
                <h2>Pengelolaan perpustakaan</h2>
                <p>Akun ini mengelola katalog, rak, anggota, dan sirkulasi buku. Data barang umum tetap dikelola oleh Admin Inventaris.</p>
            @endif
        </div>
        <span class="role-pill">{{ auth()->user()->hasRole('SUPER_ADMIN') ? 'SUPER ADMIN' : 'ADMIN PERPUSTAKAAN' }}</span>
    </div>

    <div class="stat-grid stat-grid-four">
        <article class="stat-card"><span>Judul buku</span><strong>{{ number_format($statistics['book_titles']) }}</strong></article>
        <article class="stat-card"><span>Total eksemplar</span><strong>{{ number_format($statistics['book_copies']) }}</strong></article>
        <article class="stat-card"><span>Buku tersedia</span><strong>{{ number_format($statistics['available_books']) }}</strong></article>
        <article class="stat-card"><span>Buku dipinjam</span><strong>{{ number_format($statistics['borrowed_books']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Belum diproses</span><strong>{{ number_format($statistics['unprocessed_books']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Katalog belum lengkap</span><strong>{{ number_format($statistics['incomplete_catalogs']) }}</strong></article>
        <article class="stat-card"><span>Anggota aktif</span><strong>{{ number_format($statistics['active_members']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Terlambat</span><strong>{{ number_format($statistics['overdue_loans']) }}</strong></article>
        <article class="stat-card stat-warning"><span>Reservasi menunggu</span><strong>{{ number_format($statistics['waiting_reservations']) }}</strong></article>
        <article class="stat-card"><span>Siap diambil</span><strong>{{ number_format($statistics['ready_reservations']) }}</strong></article>
        <article class="stat-card {{ $statistics['online_requests'] > 0 ? 'stat-warning' : '' }}"><span>Pengajuan online aktif</span><strong>{{ number_format($statistics['online_requests']) }}</strong></article>
        <article class="stat-card {{ $statistics['unread_contact_messages'] > 0 ? 'stat-warning' : '' }}"><span>Pesan kontak baru</span><strong>{{ number_format($statistics['unread_contact_messages']) }}</strong></article>
    </div>

    <div class="quick-grid">
        <a class="quick-card" href="{{ route('library.books.index', ['status' => 'incomplete']) }}">
            <span>KB</span>
            <div>
                <strong>Lengkapi katalog</strong>
                <small>Proses buku baru yang otomatis masuk dari inventaris.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('library.books.index') }}">
            <span>BK</span>
            <div>
                <strong>Daftar buku</strong>
                <small>Lihat seluruh judul dan status eksemplar buku.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('library.shelves.index') }}">
            <span>RK</span>
            <div>
                <strong>Master rak</strong>
                <small>Atur lokasi, klasifikasi, dan kapasitas rak perpustakaan.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('library.shelf-assignments.index', ['assignment' => 'without_shelf']) }}">
            <span>PR</span>
            <div>
                <strong>Penempatan buku</strong>
                <small>Tempatkan eksemplar baru ke rak dan aktifkan status tersedia.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('library.loans.create') }}">
            <span>PJ</span>
            <div>
                <strong>Buat peminjaman</strong>
                <small>Pilih anggota dan eksemplar buku yang tersedia.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('library.loans.index') }}">
            <span>TR</span>
            <div>
                <strong>Transaksi peminjaman</strong>
                <small>Pantau pinjaman aktif, selesai, dan terlambat.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('library.returns.index') }}">
            <span>KB</span>
            <div>
                <strong>Proses pengembalian</strong>
                <small>Terima buku, periksa kondisi, dan hitung denda final.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('library.reservations.index') }}">
            <span>RS</span>
            <div>
                <strong>Reservasi buku</strong>
                <small>Kelola antrean dan buku yang sudah siap diambil anggota.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('library.loan-requests.index') }}">
            <span>ON</span>
            <div>
                <strong>Pengajuan online</strong>
                <small>Setujui permintaan siswa, siapkan buku, dan konfirmasi pengambilan.</small>
            </div>
        </a>
        <a class="quick-card" href="{{ route('library.contact-messages.index') }}">
            <span>PS</span>
            <div>
                <strong>Pesan portal</strong>
                <small>Periksa pertanyaan dan masukan yang dikirim dari halaman kontak.</small>
            </div>
        </a>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Sinkron dari inventaris</p>
                <h2>Buku baru masuk</h2>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Kode</th>
                        <th>Judul</th>
                        <th>Status katalog</th>
                        <th>Tanggal masuk</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($newBooks as $book)
                        <tr><td class="table-number">{{ (is_object($newBooks) && method_exists($newBooks, 'firstItem') && $newBooks->firstItem() !== null ? $newBooks->firstItem() : 1) + $loop->index }}</td>
                            <td>{{ $book->item_code }}</td>
                            <td class="table-primary">{{ $book->item_name }}</td>
                            <td><span class="badge {{ ($book->completion_status ?? 'incomplete') === 'incomplete' ? 'badge-warning' : 'badge-success' }}">{{ $book->completion_status ?? 'incomplete' }}</span></td>
                            <td>{{ \Illuminate\Support\Carbon::parse($book->created_at)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Belum ada buku yang diinput dari inventaris.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
