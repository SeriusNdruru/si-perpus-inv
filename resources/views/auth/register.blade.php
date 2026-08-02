@extends('layouts.public')

@section('title', 'Daftar Akun Siswa')

@section('content')
<section class="portal-page-hero portal-page-hero-register">
    <div class="portal-container">
        <span class="portal-kicker">Pendaftaran siswa SD</span>
        <h1>Buat akun siswa</h1>
        <p>Satu akun dapat digunakan untuk mengajukan buku, memantau pengembalian, melihat denda, dan menerima notifikasi.</p>
    </div>
</section>

<section class="portal-section">
    <div class="portal-container portal-register-grid">
        <aside class="portal-register-benefits">
            <h2>Setelah mendaftar</h2>
            <div><span>01</span><p>Pilih beberapa buku dalam satu keranjang pengajuan.</p></div>
            <div><span>02</span><p>Pantau status persetujuan dan kesiapan buku.</p></div>
            <div><span>03</span><p>Lihat riwayat pinjaman, pengembalian, dan denda.</p></div>
            <div><span>04</span><p>Terima pesan H-1 sebelum tanggal pengembalian.</p></div>
            <a href="{{ \Illuminate\Support\Facades\Route::has('student.login') ? route('student.login') : url('/siswa/login') }}">Sudah memiliki akun siswa? Login →</a>
        </aside>

        <form method="POST" action="{{ route('register.store') }}" class="portal-form">
            @csrf

            <div class="portal-form-grid">
                <label class="portal-field-full">
                    <span>Nama lengkap *</span>
                    <input
                        name="full_name"
                        type="text"
                        maxlength="150"
                        value="{{ old('full_name') }}"
                        required
                    >
                </label>

                <label>
                    <span>Username *</span>
                    <input
                        name="username"
                        type="text"
                        maxlength="60"
                        value="{{ old('username') }}"
                        required
                    >
                </label>

                <label>
                    <span>Email aktif *</span>
                    <input
                        name="email"
                        type="email"
                        maxlength="150"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        placeholder="nama@sekolah.sch.id"
                        required
                    >
                    <small>Email digunakan untuk login siswa dan dipersiapkan untuk notifikasi email.</small>
                </label>

                <label>
                    <span>NIS *</span>
                    <input
                        name="identity_number"
                        type="text"
                        maxlength="80"
                        value="{{ old('identity_number') }}"
                        required
                    >
                </label>

                <label>
                    <span>Kelas *</span>
                    <select id="department" name="department" required>
                        <option value="" disabled @selected(old('department') === null || old('department') === '')>
                            Pilih kelas
                        </option>
                        @foreach (['Kelas 1', 'Kelas 2', 'Kelas 3', 'Kelas 4', 'Kelas 5', 'Kelas 6'] as $classOption)
                            <option value="{{ $classOption }}" @selected(old('department') === $classOption)>
                                {{ $classOption }}
                            </option>
                        @endforeach
                    </select>
                    <small>Pilih kelas siswa dari Kelas 1 sampai Kelas 6.</small>
                </label>

                <label>
                    <span>Nomor telepon</span>
                    <input
                        name="phone"
                        type="text"
                        maxlength="30"
                        value="{{ old('phone') }}"
                    >
                </label>

                <label class="portal-field-full">
                    <span>Alamat</span>
                    <textarea name="address" rows="3" maxlength="1000">{{ old('address') }}</textarea>
                </label>

                <label>
                    <span>Password *</span>
                    <input
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        required
                    >
                </label>

                <label>
                    <span>Konfirmasi password *</span>
                    <input
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                    >
                </label>

                <label class="portal-field-full portal-check">
                    <input name="terms" type="checkbox" value="1" required>
                    <span>Saya menyatakan data yang diisi benar dan bersedia mengikuti aturan perpustakaan sekolah.</span>
                </label>

                <input
                    name="website"
                    type="text"
                    tabindex="-1"
                    autocomplete="off"
                    class="portal-honeypot"
                >
            </div>

            <button type="submit" class="portal-button portal-button-primary">
                Buat akun dan masuk
            </button>
        </form>
    </div>
</section>
@endsection
