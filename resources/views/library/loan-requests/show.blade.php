@extends('layouts.app')

@section('title', 'Detail Pengajuan Online')
@section('page-title', 'Detail Pengajuan Peminjaman')

@section('content')
<div class="detail-heading">
    <div>
        <p class="eyebrow">{{ $loanRequest->request_code }}</p>
        <h2>{{ $loanRequest->member?->member_name }}</h2>
        <div class="detail-badges"><span class="badge {{ $loanRequest->statusBadgeClass() }}">{{ $loanRequest->statusLabel() }}</span><span class="badge badge-neutral">{{ $loanRequest->items->count() }} buku</span></div>
    </div>
    <a href="{{ route('library.loan-requests.index') }}" class="button-secondary">Kembali</a>
</div>

<div class="detail-grid">
    <section class="panel detail-card">
        <div class="panel-header"><h2>Data anggota</h2></div>
        <dl class="definition-list">
            <div><dt>Kode anggota</dt><dd>{{ $loanRequest->member?->member_code }}</dd></div>
            <div><dt>Identitas</dt><dd>{{ $loanRequest->member?->identity_number ?: '-' }}</dd></div>
            <div><dt>Username</dt><dd>{{ $loanRequest->member?->user?->username ?: '-' }}</dd></div>
            <div><dt>Email</dt><dd>{{ $loanRequest->member?->user?->email ?: '-' }}</dd></div>
            <div><dt>Pengajuan</dt><dd>{{ $loanRequest->requested_at?->format('d/m/Y H:i') }}</dd></div>
            <div><dt>Batas pengambilan</dt><dd>{{ $loanRequest->pickup_expires_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
        </dl>
    </section>

    <section class="panel detail-card">
        <div class="panel-header"><h2>Catatan</h2></div>
        <div class="panel-body-form">
            <p><strong>Dari anggota</strong></p><p style="white-space:pre-line">{{ $loanRequest->member_notes ?: 'Tidak ada catatan.' }}</p>
            <p><strong>Dari petugas</strong></p><p style="white-space:pre-line">{{ $loanRequest->admin_notes ?: 'Belum ada catatan.' }}</p>
        </div>
    </section>
</div>

<section class="panel">
    <div class="panel-header"><div><p class="eyebrow">Daftar permintaan</p><h2>Buku dan eksemplar</h2></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Buku</th><th>Penulis</th><th>Eksemplar</th><th>Rak</th><th>Lokasi</th></tr></thead>
            <tbody>
                @foreach ($loanRequest->items as $requestItem)
                    <tr>
                        <td><strong>{{ $requestItem->item?->item_name }}</strong><div class="table-secondary">{{ $requestItem->item?->item_code }}</div></td>
                        <td>{{ $requestItem->item?->authors?->pluck('author_name')->join(', ') ?: '-' }}</td>
                        <td>{{ $requestItem->asset?->asset_code ?: 'Ditentukan saat persetujuan' }}</td>
                        <td>{{ $requestItem->asset?->shelf?->shelf_code ?: '-' }}</td>
                        <td>{{ $requestItem->asset?->location?->location_name ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

@if (in_array($loanRequest->status, ['submitted','approved','ready'], true))
<section class="panel">
    <div class="panel-header"><div><p class="eyebrow">Tindakan petugas</p><h2>Proses pengajuan</h2></div></div>
    <div class="panel-body-form">
        <div class="form-field"><label for="admin_notes">Catatan petugas</label><textarea id="admin_notes" form="action-form" name="admin_notes" rows="4" maxlength="3000">{{ old('admin_notes', $loanRequest->admin_notes) }}</textarea></div>
        <div class="form-actions">
            @if ($loanRequest->status === 'submitted')
                <form id="action-form" method="POST" action="{{ route('library.loan-requests.approve', $loanRequest) }}">@csrf @method('PATCH')<input type="hidden" name="admin_notes" value=""><button type="submit" class="button-primary" onclick="this.form.admin_notes.value=document.getElementById('admin_notes').value">Setujui dan pesan eksemplar</button></form>
            @elseif ($loanRequest->status === 'approved')
                <form id="action-form" method="POST" action="{{ route('library.loan-requests.ready', $loanRequest) }}">@csrf @method('PATCH')<input type="hidden" name="admin_notes" value=""><button type="submit" class="button-primary" onclick="this.form.admin_notes.value=document.getElementById('admin_notes').value">Tandai siap diambil</button></form>
            @elseif ($loanRequest->status === 'ready')
                <form id="action-form" method="POST" action="{{ route('library.loan-requests.collect', $loanRequest) }}" onsubmit="return confirm('Konfirmasi bahwa buku sudah diserahkan kepada anggota?');">@csrf @method('PATCH')<input type="hidden" name="admin_notes" value=""><button type="submit" class="button-primary" onclick="this.form.admin_notes.value=document.getElementById('admin_notes').value">Konfirmasi pengambilan</button></form>
            @endif

            <form method="POST" action="{{ route('library.loan-requests.reject', $loanRequest) }}" onsubmit="return confirm('Tolak pengajuan ini? Eksemplar yang dipesan akan dilepas.');">@csrf @method('PATCH')<input type="hidden" name="admin_notes" value=""><button type="submit" class="button-danger-soft" onclick="this.form.admin_notes.value=document.getElementById('admin_notes').value">Tolak pengajuan</button></form>
        </div>
    </div>
</section>
@endif
@endsection
