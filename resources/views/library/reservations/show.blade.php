@extends('layouts.app')

@section('title', 'Detail Reservasi')
@section('page-title', 'Detail Reservasi Buku')

@section('content')
    @php
        $member = $reservation->member;
        $item = $reservation->item;
        $bookDetail = $item?->bookDetail;
        $authors = $item?->authors?->pluck('author_name')->join(', ');
    @endphp

    <div class="detail-heading">
        <div>
            <p class="eyebrow">{{ $reservation->reservation_code }}</p>
            <h2>{{ $item?->item_name }}</h2>
            <div class="detail-badges">
                <span class="badge {{ $reservation->statusBadgeClass() }}">{{ $reservation->statusLabel() }}</span>
                @if ($reservation->isActive())
                    <span class="badge badge-neutral">Antrean #{{ $reservation->queue_number ?? '-' }}</span>
                @endif
            </div>
        </div>
        <div class="detail-actions">
            <a href="{{ route('library.reservations.index') }}" class="button-secondary">Kembali</a>
            @if ($reservation->status === 'ready')
                <a
                    href="{{ route('library.loans.create', ['member_id' => $reservation->member_id, 'item_id' => $reservation->item_id, 'reservation_id' => $reservation->id]) }}"
                    class="button-primary button-link"
                >
                    Proses peminjaman
                </a>
            @endif
        </div>
    </div>

    @if ($reservation->status === 'ready')
        <div class="inline-notice reservation-ready-notice">
            Buku siap diambil. Reservasi berlaku sampai
            <strong>{{ $reservation->expires_at?->translatedFormat('d F Y H:i') }}</strong>.
            Setelah melewati waktu tersebut, sistem memindahkannya menjadi kedaluwarsa dan memberi kesempatan kepada antrean berikutnya.
        </div>
    @elseif ($reservation->status === 'waiting')
        <div class="inline-notice reservation-waiting-notice">
            Reservasi masih menunggu. Posisi antrean saat ini adalah <strong>#{{ $reservation->queue_number ?? '-' }}</strong>.
        </div>
    @endif

    <div class="detail-grid detail-grid-reservation">
        <section class="panel detail-card">
            <div class="panel-header"><h2>Informasi Reservasi</h2></div>
            <dl class="definition-list">
                <div><dt>Kode reservasi</dt><dd>{{ $reservation->reservation_code }}</dd></div>
                <div><dt>Tanggal reservasi</dt><dd>{{ $reservation->reservation_date?->translatedFormat('d F Y H:i') }}</dd></div>
                <div><dt>Status</dt><dd>{{ $reservation->statusLabel() }}</dd></div>
                <div><dt>Nomor antrean</dt><dd>{{ $reservation->isActive() ? '#'.($reservation->queue_number ?? '-') : '-' }}</dd></div>
                <div><dt>Batas pengambilan</dt><dd>{{ $reservation->expires_at?->translatedFormat('d F Y H:i') ?? '-' }}</dd></div>
                <div><dt>Petugas</dt><dd>{{ $reservation->processor?->full_name ?? '-' }}</dd></div>
                <div><dt>Catatan</dt><dd>{{ $reservation->notes ?: '-' }}</dd></div>
            </dl>
        </section>

        <section class="panel detail-card">
            <div class="panel-header"><h2>Anggota</h2></div>
            <dl class="definition-list">
                <div><dt>Nama</dt><dd>{{ $member?->member_name }}</dd></div>
                <div><dt>Kode anggota</dt><dd>{{ $member?->member_code }}</dd></div>
                <div><dt>Nomor identitas</dt><dd>{{ $member?->identity_number ?: '-' }}</dd></div>
                <div><dt>Kelas</dt><dd>{{ $member?->department ?: '-' }}</dd></div>
                <div><dt>Telepon</dt><dd>{{ $member?->phone ?: '-' }}</dd></div>
                <div><dt>Status anggota</dt><dd>{{ $member?->statusLabel() }}</dd></div>
            </dl>
            <div class="detail-card-actions">
                <a href="{{ route('library.members.show', $member) }}" class="action-link">Lihat detail anggota</a>
            </div>
        </section>

        <section class="panel detail-card">
            <div class="panel-header"><h2>Informasi Buku</h2></div>
            <dl class="definition-list">
                <div><dt>Kode buku</dt><dd>{{ $item?->item_code }}</dd></div>
                <div><dt>Judul</dt><dd>{{ $item?->item_name }}</dd></div>
                <div><dt>Penulis</dt><dd>{{ $authors ?: '-' }}</dd></div>
                <div><dt>ISBN</dt><dd>{{ $bookDetail?->isbn_13 ?: ($bookDetail?->isbn_10 ?: '-') }}</dd></div>
                <div><dt>Nomor panggil</dt><dd>{{ $bookDetail?->call_number ?: '-' }}</dd></div>
                <div><dt>Eksemplar tersedia</dt><dd>{{ number_format($availableCopies) }}</dd></div>
            </dl>
            <div class="detail-card-actions">
                <a href="{{ route('library.books.show', $item) }}" class="action-link">Lihat detail buku</a>
            </div>
        </section>

        <section class="panel detail-card reservation-queue-card">
            <div class="panel-header"><h2>Antrean Judul Ini</h2></div>
            <div class="reservation-queue-list">
                @forelse ($activeQueue as $queueItem)
                    <div class="reservation-queue-row {{ $queueItem->id === $reservation->id ? 'is-current' : '' }}">
                        <span>#{{ $queueItem->queue_number }}</span>
                        <div>
                            <strong>{{ $queueItem->reservation_code }}</strong>
                            <small>{{ $queueItem->status === 'ready' ? 'Siap diambil' : 'Menunggu' }}</small>
                        </div>
                        @if ($queueItem->id === $reservation->id)
                            <span class="badge badge-neutral">Reservasi ini</span>
                        @endif
                    </div>
                @empty
                    <div class="empty-state">Tidak ada antrean aktif untuk judul ini.</div>
                @endforelse
            </div>
        </section>
    </div>

    @if ($reservation->isActive())
        <section class="panel form-panel reservation-cancel-panel">
            <div class="panel-header panel-header-wrap">
                <div>
                    <p class="eyebrow">Pembatalan</p>
                    <h2>Batalkan Reservasi</h2>
                </div>
                <form method="POST" action="{{ route('library.reservations.cancel', $reservation) }}" onsubmit="return confirm('Batalkan reservasi ini? Antrean judul akan dihitung ulang.');">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="button-danger">Batalkan reservasi</button>
                </form>
            </div>
            <p class="panel-description">Pembatalan tidak menghapus data. Status dan jejak petugas tetap disimpan dalam audit log.</p>
        </section>
    @endif
@endsection
