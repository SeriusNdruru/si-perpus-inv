@extends('layouts.app')

@section('title', 'Detail Pesan Kontak')
@section('page-title', 'Detail Pesan Kontak')

@section('content')
<div class="detail-heading"><div><p class="eyebrow">{{ $contactMessage->created_at?->format('d/m/Y H:i') }}</p><h2>{{ $contactMessage->subject }}</h2><p>{{ $contactMessage->sender_name }} · {{ $contactMessage->sender_email ?: ($contactMessage->sender_phone ?: '-') }}</p></div><a href="{{ route('library.contact-messages.index') }}" class="button-secondary">Kembali</a></div>
<section class="panel detail-card"><div class="panel-header"><h2>Isi pesan</h2></div><div class="panel-body-form"><p style="white-space:pre-line">{{ $contactMessage->message }}</p></div></section>
<section class="panel"><div class="panel-header"><h2>Status penanganan</h2></div><form method="POST" action="{{ route('library.contact-messages.update', $contactMessage) }}" class="data-form">@csrf @method('PATCH')<div class="form-grid"><div class="form-field"><label>Status</label><select name="status">@foreach (['unread'=>'Belum dibaca','read'=>'Dibaca','replied'=>'Sudah dibalas','closed'=>'Ditutup'] as $value=>$label)<option value="{{ $value }}" @selected($contactMessage->status===$value)>{{ $label }}</option>@endforeach</select></div></div><div class="form-actions"><button class="button-primary">Simpan status</button></div></form></section>
@endsection
