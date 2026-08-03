@extends('layouts.member')

@section('title', 'Detail Peminjaman')
@section('page-title', 'Detail Peminjaman')

@section('content')
<div class="member-page-heading"><div><span class="member-kicker">{{ $loanRow->loan_code }}</span><h2>{{ ucfirst($loanRow->status) }}</h2><p>Jatuh tempo utama {{ \Illuminate\Support\Carbon::parse($loanRow->default_due_date)->format('d/m/Y') }}</p></div><a href="{{ route('member.history.loans') }}" class="member-button member-button-soft">Kembali</a></div>
<section class="member-panel">
    <div class="member-cart-list">
        @foreach ($items as $item)
            <article class="numbered-list-item" data-list-number="{{ $loop->iteration }}">
                <div class="member-mini-cover">@if ($item->cover_path)<img src="{{ route('media.thumbnail', ['path' => $item->cover_path, 'size' => 160]) }}" alt="" loading="lazy" decoding="async" fetchpriority="low" data-image-retry>@else<span>BK</span>@endif</div>
                <div>
                    <strong>{{ $item->item_name }}</strong>
                    <small>{{ $item->asset_code }} · Jatuh tempo {{ \Illuminate\Support\Carbon::parse($item->due_date)->format('d/m/Y') }}</small>
                    <small>
                        {{ $item->returned_at
                            ? 'Dikembalikan '.\Illuminate\Support\Carbon::parse($item->returned_at)->format('d/m/Y H:i')
                            : 'Belum dikembalikan' }}
                        · Kondisi: {{ $item->condition_in ?: ($item->condition_out ?: '-') }}
                    </small>
                </div>
                <span class="member-status member-status-{{ $item->return_status }}">{{ ucfirst($item->return_status) }}</span>
            </article>
        @endforeach
    </div>
</section>
@endsection
