@extends('layouts.member')

@section('title', 'Detail Pengajuan')
@section('page-title', 'Detail Pengajuan')

@section('content')
<div class="member-page-heading">
    <div><span class="member-kicker">{{ $loanRequest->request_code }}</span><h2>{{ $loanRequest->statusLabel() }}</h2><p>Dikirim {{ $loanRequest->requested_at?->format('d/m/Y H:i') }}</p></div>
    <a href="{{ route('member.loan-requests.index') }}" class="member-button member-button-soft">Kembali</a>
</div>

<div class="member-detail-grid">
    <section class="member-panel">
        <div class="member-panel-heading"><h2>Buku yang diajukan</h2></div>
        <div class="member-cart-list">
            @foreach ($loanRequest->items as $requestItem)
                <article>
                    <div class="member-mini-cover">@if ($requestItem->item?->bookDetail?->cover_path)<img src="{{ asset('storage/'.$requestItem->item->bookDetail->cover_path) }}" alt="">@else<span>BK</span>@endif</div>
                    <div><strong>{{ $requestItem->item?->item_name }}</strong><small>{{ $requestItem->item?->authors?->pluck('author_name')->join(', ') ?: '-' }}</small></div>
                    <span>{{ $requestItem->asset?->asset_code ?: 'Eksemplar belum ditentukan' }}</span>
                </article>
            @endforeach
        </div>
    </section>

    <aside class="member-panel member-request-timeline">
        <h2>Proses pengajuan</h2>
        <div class="{{ in_array($loanRequest->status, ['submitted','approved','ready','collected'], true) ? 'done' : '' }}"><span>1</span><p><strong>Dikirim</strong><small>{{ $loanRequest->requested_at?->format('d/m/Y H:i') }}</small></p></div>
        <div class="{{ in_array($loanRequest->status, ['approved','ready','collected'], true) ? 'done' : '' }}"><span>2</span><p><strong>Disetujui</strong><small>{{ $loanRequest->approved_at?->format('d/m/Y H:i') ?? '-' }}</small></p></div>
        <div class="{{ in_array($loanRequest->status, ['ready','collected'], true) ? 'done' : '' }}"><span>3</span><p><strong>Siap diambil</strong><small>{{ $loanRequest->ready_at?->format('d/m/Y H:i') ?? '-' }}</small></p></div>
        <div class="{{ $loanRequest->status === 'collected' ? 'done' : '' }}"><span>4</span><p><strong>Diambil</strong><small>{{ $loanRequest->collected_at?->format('d/m/Y H:i') ?? '-' }}</small></p></div>

        @if ($loanRequest->pickup_expires_at)
            <div class="member-form-note">Batas pengambilan: {{ $loanRequest->pickup_expires_at->format('d/m/Y H:i') }}</div>
        @endif
        @if ($loanRequest->admin_notes)
            <div class="member-form-note"><strong>Catatan petugas:</strong> {{ $loanRequest->admin_notes }}</div>
        @endif
        @if ($loanRequest->status === 'submitted')
            <form method="POST" action="{{ route('member.loan-requests.cancel', $loanRequest) }}" onsubmit="return confirm('Batalkan pengajuan ini?');">@csrf @method('PATCH')<button class="member-button member-button-danger" type="submit">Batalkan pengajuan</button></form>
        @endif
    </aside>
</div>
@endsection
