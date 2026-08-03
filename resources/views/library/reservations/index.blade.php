@extends('layouts.app')

@section('title', 'Reservasi Buku')
@section('page-title', 'Reservasi Buku')

@section('content')
    <div class="stat-grid stat-grid-four">
        <article class="stat-card stat-warning">
            <span>Menunggu</span>
            <strong>{{ number_format($summary['waiting']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Siap diambil</span>
            <strong>{{ number_format($summary['ready']) }}</strong>
        </article>
        <article class="stat-card stat-warning">
            <span>Kedaluwarsa hari ini</span>
            <strong>{{ number_format($summary['expiring_today']) }}</strong>
        </article>
        <article class="stat-card">
            <span>Selesai hari ini</span>
            <strong>{{ number_format($summary['completed_today']) }}</strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Antrean koleksi</p>
                <h2>Daftar Reservasi</h2>
            </div>
            <a href="{{ route('library.reservations.create') }}" class="button-primary button-link">Buat reservasi</a>
        </div>

        <form method="GET" action="{{ route('library.reservations.index') }}" class="filter-bar filter-bar-reservations">
            <div class="filter-field filter-search">
                <label for="search">Pencarian</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Kode reservasi, anggota, judul, ISBN, atau penulis"
                >
            </div>
            <div class="filter-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua status</option>
                    <option value="waiting" @selected(request('status') === 'waiting')>Menunggu</option>
                    <option value="ready" @selected(request('status') === 'ready')>Siap diambil</option>
                    <option value="completed" @selected(request('status') === 'completed')>Selesai</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Dibatalkan</option>
                    <option value="expired" @selected(request('status') === 'expired')>Kedaluwarsa</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="button-primary">Terapkan</button>
                <a href="{{ route('library.reservations.index') }}" class="button-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Kode</th>
                        <th>Anggota</th>
                        <th>Judul buku</th>
                        <th>Tanggal reservasi</th>
                        <th>Antrean</th>
                        <th>Ketersediaan</th>
                        <th>Batas pengambilan</th>
                        <th>Status</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reservations as $reservation)
                        <tr><td class="table-number">{{ (is_object($reservations) && method_exists($reservations, 'firstItem') && $reservations->firstItem() !== null ? $reservations->firstItem() : 1) + $loop->index }}</td>
                            <td><strong>{{ $reservation->reservation_code }}</strong></td>
                            <td>
                                <div class="table-primary">{{ $reservation->member?->member_name }}</div>
                                <div class="table-secondary">{{ $reservation->member?->member_code }}</div>
                            </td>
                            <td>
                                <div class="table-primary">{{ $reservation->item?->item_name }}</div>
                                <div class="table-secondary">
                                    {{ $reservation->item?->item_code }}
                                    @if ($reservation->item?->authors?->isNotEmpty())
                                        · {{ $reservation->item->authors->pluck('author_name')->join(', ') }}
                                    @endif
                                </div>
                            </td>
                            <td>{{ $reservation->reservation_date?->translatedFormat('d F Y H:i') }}</td>
                            <td>
                                @if ($reservation->isActive())
                                    <span class="reservation-queue-number">#{{ $reservation->queue_number ?? '-' }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                <div class="table-primary">{{ number_format((int) $reservation->available_copies) }} tersedia</div>
                                <div class="table-secondary">eksemplar layak dan berada di rak aktif</div>
                            </td>
                            <td>
                                @if ($reservation->status === 'ready' && $reservation->expires_at)
                                    <div class="table-primary">{{ $reservation->expires_at->translatedFormat('d F Y H:i') }}</div>
                                    <div class="table-secondary {{ $reservation->expires_at->isToday() ? 'text-danger' : '' }}">
                                        {{ $reservation->expires_at->diffForHumans() }}
                                    </div>
                                @else
                                    -
                                @endif
                            </td>
                            <td><span class="badge {{ $reservation->statusBadgeClass() }}">{{ $reservation->statusLabel() }}</span></td>
                            <td><a href="{{ route('library.reservations.show', $reservation) }}" class="action-link">Detail</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="empty-state">Belum ada reservasi yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($reservations->hasPages())
            <div class="pagination-bar">
                <span>Menampilkan {{ $reservations->firstItem() }} sampai {{ $reservations->lastItem() }} dari {{ $reservations->total() }} reservasi</span>
                <div class="pagination-actions">
                    @if ($reservations->onFirstPage())
                        <span class="button-secondary is-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $reservations->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
                    @endif
                    <span class="page-indicator">Halaman {{ $reservations->currentPage() }} dari {{ $reservations->lastPage() }}</span>
                    @if ($reservations->hasMorePages())
                        <a href="{{ $reservations->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
                    @else
                        <span class="button-secondary is-disabled">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
