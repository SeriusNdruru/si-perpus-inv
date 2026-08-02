@extends('layouts.app')

@section('title', 'Buat Peminjaman')
@section('page-title', 'Buat Peminjaman Buku')

@section('content')
    <section class="panel form-panel form-panel-wide">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Transaksi baru</p>
                <h2>Formulir Peminjaman</h2>
            </div>
            <a href="{{ route('library.loans.index') }}" class="button-secondary">Kembali</a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger form-errors">
                <strong>Transaksi belum dapat disimpan.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('library.loans.store') }}" class="data-form" id="loan-form">
            @csrf

            <div class="form-section">
                <div class="form-section-heading">
                    <span>1</span>
                    <div>
                        <h3>Pilih anggota</h3>
                        <p>Hanya anggota aktif tanpa keterlambatan yang dapat membuat peminjaman baru.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-field form-field-full">
                        <label for="member_id">Anggota <span>*</span></label>
                        <select id="member_id" name="member_id" required>
                            <option value="">Pilih anggota</option>
                            @foreach ($members as $member)
                                @php
                                    $selectedMember = (int) old('member_id', $preselectedMemberId) === $member->id;
                                    $isBlocked = (int) $member->overdue_item_count > 0
                                        || (int) $member->active_item_count >= $settings['max_active_loans'];
                                @endphp
                                <option
                                    value="{{ $member->id }}"
                                    data-active-count="{{ (int) $member->active_item_count }}"
                                    data-overdue-count="{{ (int) $member->overdue_item_count }}"
                                    @selected($selectedMember)
                                    @disabled($isBlocked)
                                >
                                    {{ $member->member_code }} · {{ $member->member_name }}
                                    ({{ (int) $member->active_item_count }}/{{ $settings['max_active_loans'] }} aktif{{ $isBlocked ? ' · tidak dapat meminjam' : '' }})
                                </option>
                            @endforeach
                        </select>
                        <small id="member-capacity-message">Batas peminjaman aktif: {{ $settings['max_active_loans'] }} eksemplar per anggota.</small>
                    </div>

                    <div class="form-field">
                        <label for="due_date">Tanggal jatuh tempo <span>*</span></label>
                        <input
                            id="due_date"
                            name="due_date"
                            type="date"
                            value="{{ old('due_date', $defaultDueDate->format('Y-m-d')) }}"
                            min="{{ today()->format('Y-m-d') }}"
                            max="{{ today()->addYear()->format('Y-m-d') }}"
                            required
                        >
                        <small>Standar sistem: {{ $settings['default_days'] }} hari.</small>
                    </div>

                    <div class="form-field">
                        <label>Perkiraan denda</label>
                        <div class="readonly-field">Rp{{ number_format($settings['fine_per_day'], 0, ',', '.') }} per hari per eksemplar</div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-heading">
                    <span>2</span>
                    <div>
                        <h3>Pilih eksemplar buku</h3>
                        <p>Daftar hanya memuat buku yang tersedia, katalog lengkap, kondisi layak, dan sudah berada di rak aktif.</p>
                    </div>
                </div>

                <div class="loan-selection-toolbar">
                    <div class="form-field">
                        <label for="asset-search">Cari buku atau barcode</label>
                        <input id="asset-search" type="search" placeholder="Judul, kode aset, barcode, ISBN, penulis, atau rak">
                    </div>
                    <div class="loan-selection-counter">
                        <span>Dipilih</span>
                        <strong><span id="selected-asset-count">0</span> eksemplar</strong>
                        <small id="selected-asset-limit">Pilih anggota untuk melihat sisa kuota.</small>
                    </div>
                </div>

                @if ($assets->isEmpty())
                    <div class="empty-state loan-empty-state">
                        Belum ada eksemplar yang siap dipinjam. Lengkapi katalog dan penempatan rak terlebih dahulu.
                    </div>
                @else
                    <div class="asset-picker" id="asset-picker">
                        @foreach ($assets as $asset)
                            @php
                                $bookDetail = $asset->item?->bookDetail;
                                $authors = $asset->item?->authors?->pluck('author_name')->join(', ');
                                $searchText = strtolower(collect([
                                    $asset->asset_code,
                                    $asset->barcode,
                                    $asset->item?->item_code,
                                    $asset->item?->item_name,
                                    $bookDetail?->isbn_10,
                                    $bookDetail?->isbn_13,
                                    $authors,
                                    $asset->shelf?->shelf_code,
                                    $asset->shelf?->shelf_name,
                                ])->filter()->join(' '));
                            @endphp
                            <label class="asset-choice" data-search="{{ $searchText }}">
                                <input
                                    type="checkbox"
                                    name="asset_ids[]"
                                    value="{{ $asset->id }}"
                                    @checked(in_array($asset->id, old('asset_ids', []), false) || ((int) old('member_id', $preselectedMemberId) === (int) $preselectedMemberId && (int) $preselectedAssetId === $asset->id))
                                >
                                <span class="asset-choice-check" aria-hidden="true"></span>
                                <span class="asset-choice-body">
                                    <span class="asset-choice-title">{{ $asset->item?->item_name }}</span>
                                    <span class="asset-choice-meta">
                                        {{ $asset->asset_code }} · Barcode {{ $asset->barcode }} · Rak {{ $asset->shelf?->shelf_code }}
                                    </span>
                                    <span class="asset-choice-meta">
                                        {{ $authors ?: 'Penulis belum tercantum' }}
                                        @if ($bookDetail?->isbn_13 || $bookDetail?->isbn_10)
                                            · ISBN {{ $bookDetail?->isbn_13 ?: $bookDetail?->isbn_10 }}
                                        @endif
                                    </span>
                                </span>
                                <span class="badge {{ $asset->condition_status === 'good' ? 'badge-success' : 'badge-warning' }}">
                                    {{ $asset->condition_status === 'good' ? 'Baik' : 'Cukup' }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="asset-picker-no-result" id="asset-picker-no-result" hidden>Tidak ada eksemplar yang cocok dengan pencarian.</p>
                @endif
            </div>

            <div class="form-section">
                <div class="form-section-heading">
                    <span>3</span>
                    <div>
                        <h3>Catatan transaksi</h3>
                        <p>Catatan bersifat opsional dan tersimpan bersama transaksi peminjaman.</p>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-field form-field-full">
                        <label for="notes">Catatan</label>
                        <textarea id="notes" name="notes" rows="4" maxlength="2000" placeholder="Contoh: Peminjaman untuk kegiatan kelas">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('library.loans.index') }}" class="button-secondary">Batal</a>
                <button type="submit" class="button-primary" id="loan-submit-button" {{ $assets->isEmpty() ? 'disabled' : '' }}>Simpan peminjaman</button>
            </div>
        </form>
    </section>

    <script>
        window.loanFormConfig = @json([
            'maxActiveLoans' => $settings['max_active_loans'],
        ]);
    </script>
    <script src="{{ asset('js/loan-form.js') }}" defer></script>
@endsection
