@extends('layouts.app')

@section('title', 'Email dan Notifikasi')
@section('page-title', 'Email dan Notifikasi')

@section('content')
<div class="email-stat-grid">
    <article><span>Total pengiriman</span><strong>{{ number_format($statistics['total']) }}</strong></article>
    <article><span>Berhasil</span><strong>{{ number_format($statistics['sent']) }}</strong></article>
    <article><span>Gagal</span><strong>{{ number_format($statistics['failed']) }}</strong></article>
    <article><span>Hari ini</span><strong>{{ number_format($statistics['today']) }}</strong></article>
</div>

<div class="email-admin-grid">
    <section class="panel email-config-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Konfigurasi aktif</p>
                <h2>SMTP dan pengirim</h2>
            </div>
        </div>

        <dl class="email-config-list">
            <div><dt>Mailer</dt><dd>{{ $configuration['mailer'] ?: '-' }}</dd></div>
            <div><dt>Host SMTP</dt><dd>{{ $configuration['host'] ?: '-' }}</dd></div>
            <div><dt>Port</dt><dd>{{ $configuration['port'] ?: '-' }}</dd></div>
            <div><dt>Email pengirim</dt><dd>{{ $configuration['from_address'] ?: '-' }}</dd></div>
            <div><dt>Nama pengirim</dt><dd>{{ $configuration['from_name'] ?: '-' }}</dd></div>
        </dl>

        @if ($configuration['mailer'] === 'log')
            <div class="inline-notice email-warning-notice">
                MAIL_MAILER masih menggunakan <strong>log</strong>. Email hanya ditulis ke file log dan belum dikirim ke kotak masuk.
            </div>
        @endif
    </section>

    <section class="panel email-test-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Pengujian</p>
                <h2>Kirim email percobaan</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.email-notifications.test') }}" class="data-form">
            @csrf
            <div class="form-field">
                <label for="recipient_email">Email tujuan <span>*</span></label>
                <input
                    id="recipient_email"
                    name="recipient_email"
                    type="email"
                    maxlength="150"
                    value="{{ old('recipient_email', auth()->user()->email) }}"
                    placeholder="contoh@gmail.com"
                    required
                >
                <small>Gunakan email yang dapat diperiksa saat ini.</small>
            </div>
            <button type="submit" class="button-primary">Kirim email pengujian</button>
        </form>
    </section>
</div>

<section class="panel email-log-panel">
    <div class="panel-header">
        <div>
            <p class="eyebrow">Riwayat pengiriman</p>
            <h2>Log email sistem</h2>
        </div>
    </div>

    <form method="GET" class="filter-bar email-log-filter">
        <div class="form-field">
            <label for="q">Pencarian</label>
            <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Email tujuan atau subjek">
        </div>
        <div class="form-field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Semua status</option>
                <option value="sent" @selected($status === 'sent')>Terkirim</option>
                <option value="failed" @selected($status === 'failed')>Gagal</option>
            </select>
        </div>
        <div class="form-field">
            <label for="type">Jenis email</label>
            <select id="type" name="type">
                <option value="">Semua jenis</option>
                @foreach ($mailTypes as $mailType)
                    <option value="{{ $mailType }}" @selected($type === $mailType)>{{ str($mailType)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="button-primary">Terapkan</button>
            <a href="{{ route('admin.email-notifications.index') }}" class="button-secondary">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th class="table-number-heading">No.</th>
                    <th>Waktu</th>
                    <th>Penerima</th>
                    <th>Jenis</th>
                    <th>Subjek</th>
                    <th>Status</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr><td class="table-number">{{ (is_object($logs) && method_exists($logs, 'firstItem') && $logs->firstItem() !== null ? $logs->firstItem() : 1) + $loop->index }}</td>
                        <td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $log->recipient_email }}</td>
                        <td>{{ str($log->mail_type)->replace('_', ' ')->title() }}</td>
                        <td>{{ $log->subject }}</td>
                        <td>
                            <span class="badge {{ $log->delivery_status === 'sent' ? 'badge-success' : 'badge-danger' }}">
                                {{ $log->statusLabel() }}
                            </span>
                        </td>
                        <td class="email-error-cell">{{ $log->error_message ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">Belum ada riwayat pengiriman email.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</section>
@endsection
