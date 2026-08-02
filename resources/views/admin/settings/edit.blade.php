@extends('layouts.app')

@section('title', 'Pengaturan Sistem')
@section('page-title', 'Pengaturan Sistem')

@section('content')
    <section class="settings-hero">
        <div>
            <p class="eyebrow">Administrasi sistem</p>
            <h2>Identitas dan Aturan Operasional</h2>
            <p>Perubahan pada halaman ini langsung dipakai oleh modul login, inventaris, peminjaman, reservasi, dan perhitungan denda.</p>
        </div>
        <div class="settings-update-meta">
            <span>Terakhir diperbarui</span>
            <strong>{{ $latestUpdate?->updated_at ? \Illuminate\Support\Carbon::parse($latestUpdate->updated_at)->translatedFormat('d M Y, H:i') : 'Belum tersedia' }}</strong>
            <small>{{ $latestUpdate?->updater_name ?: 'Data awal sistem' }}</small>
        </div>
    </section>

    @if ($errors->any())
        <div class="alert alert-danger content-alert form-errors">
            <strong>Pengaturan belum dapat disimpan.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" class="settings-form">
        @csrf
        @method('PUT')

        <section class="panel settings-panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Bagian 1</p>
                    <h2>Identitas Aplikasi dan Instansi</h2>
                </div>
            </div>

            <div class="data-form">
                <div class="form-grid">
                    <div class="form-field">
                        <label for="application_name">Nama aplikasi <span>*</span></label>
                        <input id="application_name" name="application_name" type="text" maxlength="120" value="{{ old('application_name', $settings['application_name']) }}" required>
                        <small>Tampil pada judul browser dan halaman login.</small>
                    </div>

                    <div class="form-field">
                        <label for="application_short_name">Inisial aplikasi <span>*</span></label>
                        <input id="application_short_name" name="application_short_name" type="text" maxlength="4" value="{{ old('application_short_name', $settings['application_short_name']) }}" required>
                        <small>Gunakan 2 sampai 4 huruf kapital atau angka untuk logo sidebar.</small>
                    </div>

                    <div class="form-field form-field-full">
                        <label for="institution_name">Nama instansi <span>*</span></label>
                        <input id="institution_name" name="institution_name" type="text" maxlength="180" value="{{ old('institution_name', $settings['institution_name']) }}" required>
                    </div>

                    <div class="form-field form-field-full">
                        <label for="institution_address">Alamat instansi <span>*</span></label>
                        <textarea id="institution_address" name="institution_address" maxlength="1000" required>{{ old('institution_address', $settings['institution_address']) }}</textarea>
                    </div>

                    <div class="form-field">
                        <label for="institution_phone">Nomor telepon</label>
                        <input id="institution_phone" name="institution_phone" type="text" maxlength="30" value="{{ old('institution_phone', $settings['institution_phone']) }}" placeholder="Contoh: 0411-123456">
                    </div>

                    <div class="form-field">
                        <label for="institution_email">Email resmi</label>
                        <input id="institution_email" name="institution_email" type="email" maxlength="150" value="{{ old('institution_email', $settings['institution_email']) }}" placeholder="perpustakaan@example.com">
                    </div>
                </div>
            </div>
        </section>

        <section class="panel settings-panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Bagian 2</p>
                    <h2>Aturan Sirkulasi Perpustakaan</h2>
                </div>
            </div>

            <div class="data-form">
                <div class="settings-rule-grid">
                    <div class="settings-rule-card">
                        <label for="default_loan_days">Lama peminjaman</label>
                        <div class="settings-number-input">
                            <input id="default_loan_days" name="default_loan_days" type="number" min="1" max="365" value="{{ old('default_loan_days', $settings['default_loan_days']) }}" required>
                            <span>hari</span>
                        </div>
                        <small>Menjadi tanggal jatuh tempo awal saat transaksi dibuat.</small>
                    </div>

                    <div class="settings-rule-card">
                        <label for="max_active_loans">Maksimal pinjaman aktif</label>
                        <div class="settings-number-input">
                            <input id="max_active_loans" name="max_active_loans" type="number" min="1" max="50" value="{{ old('max_active_loans', $settings['max_active_loans']) }}" required>
                            <span>eksemplar</span>
                        </div>
                        <small>Dihitung dari seluruh buku yang belum dikembalikan anggota.</small>
                    </div>

                    <div class="settings-rule-card">
                        <label for="fine_per_day">Denda keterlambatan per hari</label>
                        <div class="settings-number-input settings-money-input">
                            <span>Rp</span>
                            <input id="fine_per_day" name="fine_per_day" type="number" min="0" max="10000000" step="100" value="{{ old('fine_per_day', $settings['fine_per_day']) }}" required>
                        </div>
                        <small>Dihitung untuk setiap eksemplar yang melewati jatuh tempo.</small>
                    </div>

                    <div class="settings-rule-card">
                        <label for="reservation_hold_days">Masa buku siap diambil</label>
                        <div class="settings-number-input">
                            <input id="reservation_hold_days" name="reservation_hold_days" type="number" min="1" max="30" value="{{ old('reservation_hold_days', $settings['reservation_hold_days']) }}" required>
                            <span>hari</span>
                        </div>
                        <small>Setelah lewat masa ini, reservasi siap diambil menjadi kedaluwarsa.</small>
                    </div>

                    <div class="settings-rule-card">
                        <label for="max_active_reservations">Maksimal reservasi aktif</label>
                        <div class="settings-number-input">
                            <input id="max_active_reservations" name="max_active_reservations" type="number" min="1" max="20" value="{{ old('max_active_reservations', $settings['max_active_reservations']) }}" required>
                            <span>reservasi</span>
                        </div>
                        <small>Mencakup status menunggu dan siap diambil.</small>
                    </div>

                    <div class="settings-rule-card">
                        <label for="loan_request_hold_days">Masa pengambilan pengajuan online</label>
                        <div class="settings-number-input">
                            <input id="loan_request_hold_days" name="loan_request_hold_days" type="number" min="1" max="14" value="{{ old('loan_request_hold_days', $settings['loan_request_hold_days']) }}" required>
                            <span>hari</span>
                        </div>
                        <small>Setelah lewat batas ini, buku yang dipesan dilepas otomatis.</small>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel settings-panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Bagian 3</p>
                    <h2>Format Kode Inventaris</h2>
                </div>
            </div>

            <div class="data-form">
                <div class="settings-format-grid">
                    <div class="form-field">
                        <label for="asset_code_separator">Pemisah kode aset <span>*</span></label>
                        <select id="asset_code_separator" name="asset_code_separator" required>
                            @foreach (['-' => 'Tanda hubung (-)', '/' => 'Garis miring (/)', '.' => 'Titik (.)', '_' => 'Garis bawah (_)'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('asset_code_separator', $settings['asset_code_separator']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small>Hanya berlaku untuk unit aset baru. Kode aset lama tidak diubah.</small>
                    </div>

                    <div class="settings-code-preview" aria-label="Contoh kode aset">
                        <span>Contoh hasil</span>
                        <strong>BK-0001{{ old('asset_code_separator', $settings['asset_code_separator']) }}001</strong>
                        <small>Kode barang + pemisah + nomor urut tiga digit.</small>
                    </div>
                </div>
            </div>
        </section>


        <section class="panel settings-panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Bagian 4</p>
                    <h2>Portal Publik Perpustakaan</h2>
                </div>
                <a href="{{ route('public.home') }}" target="_blank" class="button-secondary">Lihat portal</a>
            </div>

            <div class="data-form">
                <div class="form-grid">
                    <div class="form-field form-field-full">
                        <label for="portal_hero_title">Judul utama Home <span>*</span></label>
                        <input id="portal_hero_title" name="portal_hero_title" type="text" maxlength="180" value="{{ old('portal_hero_title', $settings['portal_hero_title']) }}" required>
                    </div>
                    <div class="form-field form-field-full">
                        <label for="portal_hero_subtitle">Subjudul Home <span>*</span></label>
                        <textarea id="portal_hero_subtitle" name="portal_hero_subtitle" rows="3" maxlength="500" required>{{ old('portal_hero_subtitle', $settings['portal_hero_subtitle']) }}</textarea>
                    </div>
                    <div class="form-field">
                        <label for="portal_about_title">Judul halaman Tentang <span>*</span></label>
                        <input id="portal_about_title" name="portal_about_title" type="text" maxlength="180" value="{{ old('portal_about_title', $settings['portal_about_title']) }}" required>
                    </div>
                    <div class="form-field">
                        <label for="portal_about_video_url">Link video YouTube/Vimeo</label>
                        <input id="portal_about_video_url" name="portal_about_video_url" type="url" maxlength="500" value="{{ old('portal_about_video_url', $settings['portal_about_video_url']) }}" placeholder="https://youtube.com/watch?v=...">
                    </div>
                    <div class="form-field form-field-full">
                        <label for="portal_about_content">Isi halaman Tentang <span>*</span></label>
                        <textarea id="portal_about_content" name="portal_about_content" rows="7" maxlength="5000" required>{{ old('portal_about_content', $settings['portal_about_content']) }}</textarea>
                    </div>
                    <div class="form-field form-field-full">
                        <label for="portal_contact_intro">Pengantar halaman Kontak <span>*</span></label>
                        <textarea id="portal_contact_intro" name="portal_contact_intro" rows="3" maxlength="1000" required>{{ old('portal_contact_intro', $settings['portal_contact_intro']) }}</textarea>
                    </div>
                    <div class="form-field">
                        <label for="portal_opening_hours">Jam layanan <span>*</span></label>
                        <input id="portal_opening_hours" name="portal_opening_hours" type="text" maxlength="300" value="{{ old('portal_opening_hours', $settings['portal_opening_hours']) }}" required>
                    </div>
                </div>
            </div>
        </section>
        <div class="settings-submit-bar">
            <p>Perubahan akan dicatat pada audit log dan berlaku pada transaksi berikutnya.</p>
            <button type="submit" class="button-primary">Simpan pengaturan</button>
        </div>
    </form>
@endsection
