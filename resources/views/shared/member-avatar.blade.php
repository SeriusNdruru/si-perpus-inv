@php
    $avatarMember = $member ?? null;
    $avatarClass = trim((string) ($class ?? 'member-avatar'));
    $avatarPath = $avatarMember?->profile_photo_path;
    $avatarName = $avatarMember?->member_name ?: 'Siswa';
    $avatarSize = max(96, (int) ($size ?? 240));
@endphp

<span class="{{ $avatarClass }} {{ $avatarPath ? 'has-photo' : 'is-placeholder' }}">
    @if ($avatarPath)
        <img
            src="{{ route('media.thumbnail', ['path' => $avatarPath, 'size' => $avatarSize]) }}"
            alt="Foto profil {{ $avatarName }}"
            loading="lazy"
            decoding="async"
            data-image-retry
            onerror="this.hidden=true;this.parentElement.classList.remove('has-photo');this.parentElement.classList.add('is-placeholder');"
        >
    @else
        <span class="member-avatar-person" aria-label="Foto profil belum ditambahkan" role="img"></span>
    @endif
</span>
