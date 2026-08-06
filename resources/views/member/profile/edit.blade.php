@extends('layouts.member')

@section('title', 'Edit Profil')
@section('page-title', 'Edit Profil')

@section('content')
<section class="member-profile-edit-grid">
    <form
        method="POST"
        action="{{ route('member.profile.update') }}"
        enctype="multipart/form-data"
        class="member-panel member-profile-edit-form"
    >
        @csrf
        @method('PATCH')

        <div class="member-panel-heading">
            <div>
                <small>Profil siswa</small>
                <h2>Foto dan informasi kontak</h2>
            </div>
        </div>

        <div class="member-profile-edit-body">
            <div class="member-profile-photo-editor">
                <div
                    id="member-profile-photo-preview"
                    class="member-profile-edit-avatar {{ $member->profile_photo_path ? 'has-photo is-previewable' : 'is-placeholder' }}"
                    @if ($member->profile_photo_path)
                        role="button"
                        tabindex="0"
                        title="Klik untuk melihat foto lebih besar"
                        aria-label="Lihat foto profil {{ $member->member_name }}"
                        data-member-photo-preview
                        data-preview-src="{{ route('media.thumbnail', ['path' => $member->profile_photo_path, 'size' => 1400]) }}"
                    @endif
                >
                    @if ($member->profile_photo_path)
                        <img
                            id="member-profile-photo-image"
                            src="{{ route('media.thumbnail', ['path' => $member->profile_photo_path, 'size' => 480]) }}"
                            alt="Foto profil {{ $member->member_name }}"
                            decoding="async"
                            data-image-retry
                        >
                    @else
                        <img id="member-profile-photo-image" src="" alt="Pratinjau foto profil" hidden>
                    @endif
                    <span class="member-avatar-person" aria-hidden="true"></span>
                </div>

                <div class="member-profile-photo-copy">
                    <strong>Foto profil</strong>
                    <p>Gunakan foto JPG, PNG, atau WebP. Ukuran maksimal 2 MB. Klik foto untuk melihat pratinjau lebih besar.</p>

                    <label class="member-profile-file-button" for="profile_photo">Pilih foto</label>
                    <input
                        id="profile_photo"
                        name="profile_photo"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    >

                    @if ($member->profile_photo_path)
                        <label class="member-profile-remove-photo">
                            <input id="remove_profile_photo" name="remove_profile_photo" type="checkbox" value="1">
                            <span>Hapus foto dan gunakan gambar orang kosong</span>
                        </label>
                    @endif
                </div>
            </div>

            <div class="member-profile-form-grid">
                <div class="member-form-field">
                    <label for="phone">Nomor telepon</label>
                    <input
                        id="phone"
                        name="phone"
                        type="text"
                        value="{{ old('phone', $member->phone ?: $user->phone) }}"
                        maxlength="30"
                        inputmode="tel"
                        placeholder="Contoh: 081234567890"
                    >
                    @error('phone')<small class="member-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="member-form-field member-form-field-wide">
                    <label for="address">Alamat</label>
                    <textarea
                        id="address"
                        name="address"
                        rows="5"
                        maxlength="2000"
                        placeholder="Masukkan alamat tempat tinggal"
                    >{{ old('address', $member->address) }}</textarea>
                    @error('address')<small class="member-field-error">{{ $message }}</small>@enderror
                </div>
            </div>

            @error('profile_photo')<div class="member-field-error member-profile-photo-error">{{ $message }}</div>@enderror

            <div class="member-profile-form-actions">
                <a href="{{ route('member.profile.show') }}" class="member-button member-button-soft">Batal</a>
                <button type="submit" class="member-button">Simpan perubahan</button>
            </div>
        </div>
    </form>

    <aside class="member-panel member-profile-locked-panel">
        <div class="member-panel-heading">
            <div>
                <small>Data resmi siswa</small>
                <h2>Tidak dapat diubah sendiri</h2>
            </div>
        </div>

        <dl class="member-profile-list">
            <div>
                <dt>Nama lengkap</dt>
                <dd>{{ $member->member_name }}</dd>
            </div>
            <div>
                <dt>NIS/NISN</dt>
                <dd>{{ $member->identity_number ?: '-' }}</dd>
            </div>
            <div>
                <dt>Kelas</dt>
                <dd>{{ $member->department ?: '-' }}</dd>
            </div>
            <div>
                <dt>Email login</dt>
                <dd>{{ $user->email ?: '-' }}</dd>
            </div>
            <div>
                <dt>Kode anggota</dt>
                <dd>{{ $member->member_code }}</dd>
            </div>
        </dl>

        <div class="member-profile-locked-note">
            Hubungi Admin Perpustakaan jika data resmi siswa perlu diperbaiki.
        </div>
    </aside>
</section>

<script>
(() => {
    const input = document.getElementById('profile_photo');
    const preview = document.getElementById('member-profile-photo-preview');
    const image = document.getElementById('member-profile-photo-image');
    const removeCheckbox = document.getElementById('remove_profile_photo');
    const originalImageSource = image ? (image.getAttribute('src') || '') : '';
    const originalPreviewSource = preview ? (preview.getAttribute('data-preview-src') || originalImageSource) : originalImageSource;

    if (!input || !preview || !image) {
        return;
    }

    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (!file) {
            return;
        }

        const reader = new FileReader();
        reader.addEventListener('load', () => {
            image.src = String(reader.result || '');
            image.hidden = false;
            preview.classList.add('has-photo', 'is-previewable');
            preview.classList.remove('is-placeholder');
            preview.setAttribute('role', 'button');
            preview.setAttribute('tabindex', '0');
            preview.setAttribute('title', 'Klik untuk melihat foto lebih besar');
            preview.setAttribute('aria-label', 'Lihat pratinjau foto profil');
            preview.setAttribute('data-member-photo-preview', '');
            preview.setAttribute('data-preview-src', String(reader.result || ''));
            if (removeCheckbox) {
                removeCheckbox.checked = false;
            }
        });
        reader.readAsDataURL(file);
    });

    if (removeCheckbox) {
        removeCheckbox.addEventListener('change', () => {
            if (removeCheckbox.checked) {
                input.value = '';
                image.hidden = true;
                image.removeAttribute('src');
                preview.classList.remove('has-photo', 'is-previewable');
                preview.classList.add('is-placeholder');
                preview.removeAttribute('role');
                preview.removeAttribute('tabindex');
                preview.removeAttribute('title');
                preview.removeAttribute('aria-label');
                preview.removeAttribute('data-member-photo-preview');
                preview.removeAttribute('data-preview-src');
                return;
            }

            if (originalImageSource !== '') {
                image.src = originalImageSource;
                image.hidden = false;
                preview.classList.add('has-photo', 'is-previewable');
                preview.classList.remove('is-placeholder');
                preview.setAttribute('role', 'button');
                preview.setAttribute('tabindex', '0');
                preview.setAttribute('title', 'Klik untuk melihat foto lebih besar');
                preview.setAttribute('aria-label', 'Lihat foto profil');
                preview.setAttribute('data-member-photo-preview', '');
                preview.setAttribute('data-preview-src', originalPreviewSource);
            }
        });
    }
})();
</script>
@endsection
