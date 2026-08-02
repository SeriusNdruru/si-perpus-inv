@extends('layouts.app')

@section('title', 'Pesan Kontak Publik')
@section('page-title', 'Pesan Kontak Publik')

@section('content')
<div class="detail-heading"><div><p class="eyebrow">Portal umum</p><h2>Pertanyaan dan masukan pengunjung</h2></div><a href="{{ route('public.contact') }}" target="_blank" class="button-secondary">Buka halaman kontak</a></div>
<section class="panel">
    <form method="GET" class="filter-bar"><div class="form-field"><label>Status</label><select name="status"><option value="">Semua</option>@foreach (['unread'=>'Belum dibaca','read'=>'Dibaca','replied'=>'Sudah dibalas','closed'=>'Ditutup'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div><div class="filter-actions"><button class="button-primary">Terapkan</button></div></form>
    <div class="table-wrap"><table><thead><tr><th>Pengirim</th><th>Subjek</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead><tbody>
        @forelse ($messages as $message)
            <tr><td><strong>{{ $message->sender_name }}</strong><div class="table-secondary">{{ $message->sender_email ?: ($message->sender_phone ?: '-') }}</div></td><td>{{ $message->subject }}</td><td><span class="badge {{ $message->status === 'unread' ? 'badge-warning' : 'badge-neutral' }}">{{ ucfirst($message->status) }}</span></td><td>{{ $message->created_at?->format('d/m/Y H:i') }}</td><td><a class="action-link" href="{{ route('library.contact-messages.show', $message) }}">Buka</a></td></tr>
        @empty<tr><td colspan="5" class="empty-state">Belum ada pesan.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection
