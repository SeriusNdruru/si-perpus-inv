@extends('layouts.member')

@section('title', 'Notifikasi')
@section('page-title', 'Notifikasi')

@section('content')
<div class="member-page-heading">
    <div><span class="member-kicker">Peringatan otomatis</span><h2>Pesan pengajuan dan pengembalian</h2><p>Pengingat H-1 dibuat otomatis setiap hari pukul 07.00 saat scheduler aktif.</p></div>
    <form method="POST" action="{{ route('member.notifications.read-all') }}">@csrf @method('PATCH')<button class="member-button member-button-soft" type="submit">Baca semua</button></form>
</div>

<section class="member-panel">
    <div class="member-notification-list member-notification-list-full">
        @forelse ($notifications as $notification)
            <article class="{{ $notification->is_read ? '' : 'is-unread' }}">
                <span>●</span>
                <div><strong>{{ $notification->title }}</strong><p>{{ $notification->message }}</p><small>{{ $notification->created_at?->format('d/m/Y H:i') }}</small></div>
                @if (! $notification->is_read)
                    <form method="POST" action="{{ route('member.notifications.read', $notification) }}">@csrf @method('PATCH')<button type="submit">Tandai dibaca</button></form>
                @endif
            </article>
        @empty
            <div class="member-empty">Belum ada notifikasi.</div>
        @endforelse
    </div>
</section>
@endsection
