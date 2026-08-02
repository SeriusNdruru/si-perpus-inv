@extends('layouts.app')

@section('title', 'Buat Reservasi')
@section('page-title', 'Buat Reservasi Buku')

@section('content')
    <section class="panel form-panel form-panel-wide">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Antrean koleksi</p>
                <h2>Formulir Reservasi</h2>
            </div>
            <a href="{{ route('library.reservations.index') }}" class="button-secondary">Kembali</a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger form-errors">
                <strong>Reservasi belum dapat disimpan.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('library.reservations.store') }}" class="data-form">
            @csrf

            <div class="form-section">
                <div class="form-section-heading">
                    <span>1</span>
                    <div>
                        <h3>Pilih anggota</h3>
                        <p>Hanya anggota aktif dengan masa berlaku yang masih berjalan yang dapat membuat reservasi.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-field form-field-full">
                        <label for="member_id">Anggota <span>*</span></label>
                        <select id="member_id" name="member_id" required>
                            <option value="">Pilih anggota</option>
                            @foreach ($members as $member)
                                @php
                                    $activeReservations = (int) $member->active_reservation_count;
                                    $limitReached = $activeReservations >= $settings['max_active'];
                                @endphp
                                <option
                                    value="{{ $member->id }}"
                                    @selected((int) old('member_id', $preselectedMemberId) === $member->id)
                                    @disabled($limitReached)
                                >
                                    {{ $member->member_code }} · {{ $member->member_name }}
                                    ({{ $activeReservations }}/{{ $settings['max_active'] }} reservasi aktif{{ $limitReached ? ' · batas tercapai' : '' }})
                                </option>
                            @endforeach
                        </select>
                        <small>Batas awal sistem: {{ $settings['max_active'] }} reservasi aktif per anggota.</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-heading">
                    <span>2</span>
                    <div>
                        <h3>Pilih judul buku</h3>
                        <p>Reservasi dibuat pada tingkat judul. Sistem mengatur antrean berdasarkan waktu pendaftaran.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-field form-field-full">
                        <label for="item_id">Judul buku <span>*</span></label>
                        <select id="item_id" name="item_id" required>
                            <option value="">Pilih judul buku</option>
                            @foreach ($books as $book)
                                @php
                                    $authors = $book->authors->pluck('author_name')->join(', ');
                                    $isbn = $book->bookDetail?->isbn_13 ?: $book->bookDetail?->isbn_10;
                                @endphp
                                <option value="{{ $book->id }}" @selected((int) old('item_id', $preselectedItemId) === $book->id)>
                                    {{ $book->item_name }} · {{ $book->item_code }}
                                    · tersedia {{ (int) $book->available_copies }}
                                    · antrean {{ (int) $book->active_reservation_count }}
                                    @if ($authors) · {{ $authors }} @endif
                                    @if ($isbn) · ISBN {{ $isbn }} @endif
                                </option>
                            @endforeach
                        </select>
                        <small>
                            Jika eksemplar tersedia dan antrean sebelumnya sudah terpenuhi, status reservasi langsung menjadi siap diambil selama {{ $settings['hold_days'] }} hari.
                        </small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-heading">
                    <span>3</span>
                    <div>
                        <h3>Catatan reservasi</h3>
                        <p>Catatan bersifat opsional dan dapat digunakan untuk kebutuhan petugas.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-field form-field-full">
                        <label for="notes">Catatan</label>
                        <textarea id="notes" name="notes" rows="4" maxlength="2000" placeholder="Contoh: Siswa akan mengambil buku setelah jam pelajaran">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="reservation-policy-note">
                Satu anggota tidak dapat membuat reservasi ganda untuk judul yang sama. Reservasi juga tidak dapat dibuat jika anggota masih meminjam judul tersebut.
            </div>

            <div class="form-actions">
                <a href="{{ route('library.reservations.index') }}" class="button-secondary">Batal</a>
                <button type="submit" class="button-primary" {{ $members->isEmpty() || $books->isEmpty() ? 'disabled' : '' }}>Simpan reservasi</button>
            </div>
        </form>
    </section>
@endsection
