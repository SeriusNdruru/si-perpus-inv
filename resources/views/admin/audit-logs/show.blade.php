@extends('layouts.app')

@section('title', 'Detail Aktivitas')
@section('page-title', 'Detail Aktivitas')

@section('content')
    @php
        $displayValue = static function (mixed $value): string {
            if ($value === null) {
                return '-';
            }

            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            if (is_array($value)) {
                return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
            }

            return (string) $value;
        };
    @endphp

    <div class="page-actions page-actions-between">
        <a href="{{ route('admin.audit-logs.index') }}" class="button-secondary">Kembali ke riwayat</a>
        <span class="badge {{ $auditLog->actionBadgeClass() }}">{{ $auditLog->actionLabel() }}</span>
    </div>

    <section class="audit-detail-hero">
        <div>
            <p class="eyebrow">Aktivitas #{{ $auditLog->id }}</p>
            <h2>{{ $auditLog->moduleLabel() }}</h2>
            <p>Dicatat pada {{ $auditLog->created_at?->translatedFormat('d F Y, H:i:s') }}.</p>
        </div>
        <div class="audit-actor-card">
            <span>Pelaku aktivitas</span>
            <strong>{{ $auditLog->actorLabel() }}</strong>
            <small>
                @if ($auditLog->user)
                    {{ '@'.$auditLog->user->username }} · {{ $auditLog->user->roles->pluck('role_name')->implode(', ') ?: 'Tanpa peran' }}
                @elseif ($auditLog->user_id)
                    ID pengguna {{ $auditLog->user_id }}
                @else
                    Sistem otomatis
                @endif
            </small>
        </div>
    </section>

    <div class="audit-meta-grid">
        <article class="audit-meta-card">
            <span>Modul</span>
            <strong>{{ $auditLog->moduleLabel() }}</strong>
            <small>{{ $auditLog->module_name }}</small>
        </article>
        <article class="audit-meta-card">
            <span>Tabel dan data</span>
            <strong>{{ $auditLog->table_name ?: '-' }}</strong>
            <small>{{ $auditLog->record_id ? 'ID '.$auditLog->record_id : 'Tanpa ID data khusus' }}</small>
        </article>
        <article class="audit-meta-card">
            <span>Alamat IP</span>
            <strong class="audit-ip">{{ $auditLog->ip_address ?: '-' }}</strong>
            <small>Alamat jaringan saat aktivitas dicatat</small>
        </article>
        <article class="audit-meta-card">
            <span>User agent</span>
            <strong class="audit-user-agent">{{ $auditLog->user_agent ?: '-' }}</strong>
            <small>Browser atau aplikasi yang digunakan</small>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Perbandingan data</p>
                <h2>Perubahan per Kolom</h2>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Kolom</th>
                        <th>Nilai sebelumnya</th>
                        <th>Nilai setelahnya</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($changes as $change)
                        <tr class="{{ $change['changed'] ? 'audit-row-changed' : '' }}"><td class="table-number">{{ (is_object($changes) && method_exists($changes, 'firstItem') && $changes->firstItem() !== null ? $changes->firstItem() : 1) + $loop->index }}</td>
                            <td><code>{{ $change['field'] }}</code></td>
                            <td><pre class="audit-inline-value">{{ $displayValue($change['old']) }}</pre></td>
                            <td><pre class="audit-inline-value">{{ $displayValue($change['new']) }}</pre></td>
                            <td>
                                @if ($change['changed'])
                                    <span class="badge badge-warning">Berubah</span>
                                @else
                                    <span class="badge badge-muted">Tetap</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">Aktivitas ini tidak memiliki snapshot perubahan data.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="audit-snapshot-grid">
        <section class="panel audit-snapshot-panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Snapshot awal</p>
                    <h2>Data Sebelumnya</h2>
                </div>
            </div>
            <pre class="audit-json">{{ $oldData !== [] ? json_encode($oldData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'Tidak ada data sebelumnya.' }}</pre>
        </section>

        <section class="panel audit-snapshot-panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Snapshot akhir</p>
                    <h2>Data Setelahnya</h2>
                </div>
            </div>
            <pre class="audit-json">{{ $newData !== [] ? json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'Tidak ada data setelahnya.' }}</pre>
        </section>
    </div>

    <div class="alert alert-info audit-security-note">
        Data dengan nama kolom sensitif seperti password, token, secret, cookie, dan API key otomatis disembunyikan pada halaman ini.
    </div>
@endsection
