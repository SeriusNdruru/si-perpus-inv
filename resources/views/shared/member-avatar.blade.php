@php
    $avatarMember = $member ?? null;
    $avatarClass = trim((string) ($class ?? 'member-avatar'));
    $avatarPath = $avatarMember?->profile_photo_path;
    $avatarName = $avatarMember?->member_name ?: 'Siswa';
    $avatarSize = max(96, (int) ($size ?? 240));
    $avatarPreview = (bool) ($preview ?? false) && filled($avatarPath);
    $avatarPreviewSize = max($avatarSize, (int) ($previewSize ?? 1200));
@endphp

<span
    class="{{ $avatarClass }} {{ $avatarPath ? 'has-photo' : 'is-placeholder' }} {{ $avatarPreview ? 'is-previewable' : '' }}"
    @if ($avatarPreview)
        role="button"
        tabindex="0"
        title="Klik untuk melihat foto lebih besar"
        aria-label="Lihat foto profil {{ $avatarName }}"
        data-member-photo-preview
        data-preview-src="{{ route('media.thumbnail', ['path' => $avatarPath, 'size' => $avatarPreviewSize]) }}"
    @endif
>
    @if ($avatarPath)
        <img
            src="{{ route('media.thumbnail', ['path' => $avatarPath, 'size' => $avatarSize]) }}"
            alt="Foto profil {{ $avatarName }}"
            loading="lazy"
            decoding="async"
            data-image-retry
            onerror="this.hidden=true;this.parentElement.classList.remove('has-photo', 'is-previewable');this.parentElement.classList.add('is-placeholder');this.parentElement.removeAttribute('data-member-photo-preview');this.parentElement.removeAttribute('data-preview-src');this.parentElement.removeAttribute('role');this.parentElement.removeAttribute('tabindex');"
        >
    @else
        <span class="member-avatar-person" aria-label="Foto profil belum ditambahkan" role="img"></span>
    @endif
</span>
