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

<div class="form-grid">
    <div class="form-field">
        <label for="full_name">Nama lengkap <span>*</span></label>
        <input
            id="full_name"
            name="full_name"
            type="text"
            maxlength="150"
            value="{{ old('full_name', $managedUser?->full_name) }}"
            placeholder="Contoh: Rina Pratama"
            autocomplete="name"
            required
        >
    </div>

    <div class="form-field">
        <label for="username">Username <span>*</span></label>
        <input
            id="username"
            name="username"
            type="text"
            maxlength="60"
            value="{{ old('username', $managedUser?->username) }}"
            placeholder="Contoh: rina.admin"
            autocomplete="username"
            required
        >
        <small>Gunakan huruf kecil, angka, titik, garis bawah, atau tanda hubung.</small>
    </div>

    <div class="form-field">
        <label for="email">Email</label>
        <input
            id="email"
            name="email"
            type="email"
            maxlength="150"
            value="{{ old('email', $managedUser?->email) }}"
            placeholder="nama@example.com"
            autocomplete="email"
        >
    </div>

    <div class="form-field">
        <label for="phone">Nomor telepon</label>
        <input
            id="phone"
            name="phone"
            type="text"
            maxlength="30"
            value="{{ old('phone', $managedUser?->phone) }}"
            placeholder="Contoh: 081234567890"
            autocomplete="tel"
        >
    </div>

    <div class="form-field">
        <label for="role_code">Peran <span>*</span></label>
        <select id="role_code" name="role_code" required @disabled($isOwnAccount ?? false)>
            <option value="">Pilih peran</option>
            @foreach ($roleOptions as $roleOption)
                <option
                    value="{{ $roleOption->role_code }}"
                    @selected(old('role_code', $selectedRoleCode ?? '') === $roleOption->role_code)
                >
                    {{ $roleOption->role_name }}
                </option>
            @endforeach
        </select>
        @if ($isOwnAccount ?? false)
            <input type="hidden" name="role_code" value="{{ $selectedRoleCode }}">
            <small>Peran akun yang sedang digunakan tidak dapat diubah.</small>
        @else
            <small>Setiap akun dikelola dengan satu peran utama agar hak akses tidak tumpang tindih.</small>
        @endif
    </div>

    <div class="form-field">
        <label for="status">Status <span>*</span></label>
        <select id="status" name="status" required @disabled($isOwnAccount ?? false)>
            <option value="active" @selected(old('status', $managedUser?->status ?? 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected(old('status', $managedUser?->status ?? 'active') === 'inactive')>Tidak aktif</option>
            @if ($managedUser)
                <option value="locked" @selected(old('status', $managedUser?->status) === 'locked')>Terkunci</option>
            @endif
        </select>
        @if ($isOwnAccount ?? false)
            <input type="hidden" name="status" value="active">
            <small>Status akun yang sedang digunakan harus tetap aktif.</small>
        @else
            <small>Akun aktif dapat masuk ke sistem. Akun tidak aktif atau terkunci akan ditolak.</small>
        @endif
    </div>

    @if (! $managedUser)
        <div class="form-field">
            <label for="password">Password <span>*</span></label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="new-password"
                required
            >
            <small>Minimal 8 karakter dan mengandung huruf serta angka.</small>
        </div>

        <div class="form-field">
            <label for="password_confirmation">Konfirmasi password <span>*</span></label>
            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                required
            >
        </div>
    @endif
</div>

<div class="form-actions">
    <a href="{{ route('admin.users.index') }}" class="button-secondary">Batal</a>
    <button type="submit" class="button-primary">{{ $submitLabel }}</button>
</div>
