@if ($errors->any())
    <div class="alert alert-danger form-errors">
        <strong>Data belum dapat disimpan.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="form-section">
    <div class="form-section-heading">
        <span>1</span>
        <div>
            <h3>Identitas anggota</h3>
            <p>Masukkan identitas utama yang digunakan saat pelayanan perpustakaan.</p>
        </div>
    </div>

    <div class="form-grid">
        <div class="form-field">
            <label for="member_code">Kode anggota {{ $member ? '' : '(opsional)' }}</label>
            <input
                id="member_code"
                name="member_code"
                type="text"
                maxlength="60"
                value="{{ old('member_code', $member?->member_code) }}"
                placeholder="Kosongkan untuk kode otomatis"
                {{ $member ? 'required' : '' }}
            >
            <small>Kode otomatis menggunakan format AGT-TAHUN-00001.</small>
        </div>

        <div class="form-field">
            <label for="member_name">Nama lengkap <span>*</span></label>
            <input
                id="member_name"
                name="member_name"
                type="text"
                maxlength="180"
                value="{{ old('member_name', $member?->member_name) }}"
                placeholder="Nama lengkap anggota"
                required
            >
        </div>

        <div class="form-field">
            <label for="member_type">Jenis anggota <span>*</span></label>
            <select id="member_type" name="member_type" required>
                @foreach (\App\Models\Member::typeOptions() as $typeValue => $typeLabel)
                    <option
                        value="{{ $typeValue }}"
                        @selected(old('member_type', $member?->member_type ?? 'student') === $typeValue)
                    >
                        {{ $typeLabel }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-field">
            <label for="identity_number">Nomor identitas</label>
            <input
                id="identity_number"
                name="identity_number"
                type="text"
                maxlength="80"
                value="{{ old('identity_number', $member?->identity_number) }}"
                placeholder="NIS, NIP, atau nomor identitas sekolah"
            >
        </div>

        <div class="form-field form-field-full">
            <label for="department">Kelas siswa</label>
            @php($schoolClassOptions = app(\App\Services\SchoolClassOptionsService::class)->options())
            <select id="department" name="department">
                <option value="">Pilih kelas siswa</option>
                @foreach ($schoolClassOptions as $schoolClassOption)
                    <option
                        value="{{ $schoolClassOption }}"
                        @selected(old('department', $member?->department) === $schoolClassOption)
                    >
                        {{ $schoolClassOption }}
                    </option>
                @endforeach
            </select>
            <small>Pilih Kelas 1 sampai Kelas 6 untuk siswa. Kosongkan untuk guru, staf sekolah, atau tamu sekolah.</small>
        </div>
    </div>
</div>

<div class="form-section">
    <div class="form-section-heading">
        <span>2</span>
        <div>
            <h3>Kontak</h3>
            <p>Data ini digunakan untuk menghubungi anggota terkait peminjaman atau keterlambatan.</p>
        </div>
    </div>

    <div class="form-grid">
        <div class="form-field">
            <label for="phone">Nomor telepon</label>
            <input
                id="phone"
                name="phone"
                type="text"
                maxlength="30"
                value="{{ old('phone', $member?->phone) }}"
                placeholder="Contoh: 081234567890"
            >
        </div>

        <div class="form-field">
            <label for="email">Email login siswa <span>*</span></label>
            <input
                id="email"
                name="email"
                type="email"
                maxlength="150"
                value="{{ old('email', $member?->email) }}"
                placeholder="anggota@example.com"
                required
            >
            <small>Email ini digunakan siswa untuk login dan pemulihan password.</small>
        </div>

        <div class="form-field form-field-full">
            <label for="address">Alamat</label>
            <textarea id="address" name="address" rows="4" maxlength="2000" placeholder="Alamat lengkap anggota">{{ old('address', $member?->address) }}</textarea>
        </div>
    </div>
</div>

<div class="form-section">
    <div class="form-section-heading">
        <span>3</span>
        <div>
            <h3>Akun login anggota</h3>
            <p>Akun dibuat oleh admin perpustakaan. Pendaftaran mandiri dari portal publik sudah dinonaktifkan.</p>
        </div>
    </div>

    <div class="form-grid">
        <div class="form-field form-field-full">
            <label for="account_username">Username akun <span>*</span></label>
            <input
                id="account_username"
                name="account_username"
                type="text"
                minlength="4"
                maxlength="60"
                value="{{ old('account_username', $member?->user?->username) }}"
                placeholder="Contoh: siswa.nama"
                autocomplete="off"
                required
            >
            <small>Gunakan huruf kecil, angka, titik, garis bawah, atau tanda hubung. Siswa tetap login memakai email.</small>
        </div>

        <div class="form-field">
            <label for="account_password">{{ $member?->user ? 'Password baru (opsional)' : 'Password awal' }} @if (! $member?->user)<span>*</span>@endif</label>
            <input
                id="account_password"
                name="account_password"
                type="password"
                minlength="8"
                maxlength="255"
                autocomplete="new-password"
                @required(! $member?->user)
            >
            <small>{{ $member?->user ? 'Kosongkan jika password tidak diubah.' : 'Minimal 8 karakter serta memuat huruf dan angka.' }}</small>
        </div>

        <div class="form-field">
            <label for="account_password_confirmation">Konfirmasi password @if (! $member?->user)<span>*</span>@endif</label>
            <input
                id="account_password_confirmation"
                name="account_password_confirmation"
                type="password"
                minlength="8"
                maxlength="255"
                autocomplete="new-password"
                @required(! $member?->user)
            >
        </div>
    </div>
</div>

<div class="form-section">
    <div class="form-section-heading">
        <span>4</span>
        <div>
            <h3>Masa keanggotaan</h3>
            <p>Status aktif hanya dapat digunakan selama masa berlaku belum berakhir.</p>
        </div>
    </div>

    <div class="form-grid">
        <div class="form-field">
            <label for="join_date">Tanggal bergabung <span>*</span></label>
            <input
                id="join_date"
                name="join_date"
                type="date"
                value="{{ old('join_date', $member?->join_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                required
            >
        </div>

        <div class="form-field">
            <label for="expiry_date">Tanggal berakhir</label>
            <input
                id="expiry_date"
                name="expiry_date"
                type="date"
                value="{{ old('expiry_date', $member?->expiry_date?->format('Y-m-d') ?? now()->addYear()->format('Y-m-d')) }}"
            >
            <small>Kosongkan jika keanggotaan tidak memiliki batas waktu.</small>
        </div>

        <div class="form-field form-field-full">
            <label for="status">Status <span>*</span></label>
            <select id="status" name="status" required>
                <option value="active" @selected(old('status', $member?->status ?? 'active') === 'active')>Aktif</option>
                <option value="suspended" @selected(old('status', $member?->status) === 'suspended')>Ditangguhkan</option>
                <option value="inactive" @selected(old('status', $member?->status) === 'inactive')>Tidak aktif</option>
                <option value="expired" @selected(old('status', $member?->status) === 'expired')>Kedaluwarsa</option>
            </select>
            <small>Status ditangguhkan memblokir peminjaman baru tanpa menghapus riwayat anggota.</small>
        </div>
    </div>
</div>

<div class="form-actions">
    <a href="{{ $member ? route('library.members.show', $member) : route('library.members.index') }}" class="button-secondary">Batal</a>
    <button type="submit" class="button-primary">{{ $submitLabel }}</button>
</div>
