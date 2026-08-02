@extends('layouts.app')

@section('title', 'Uji Akses dan Alur')
@section('page-title', 'Uji Akses dan Alur Sistem')

@section('content')
    @php
        $heroClass = match ($report['status']) {
            'passed' => 'readiness-hero-ready',
            'passed_with_notes' => 'readiness-hero-warning',
            default => 'readiness-hero-fail',
        };
    @endphp

    <section class="readiness-hero {{ $heroClass }}">
        <div>
            <p class="eyebrow">Tahap 34</p>
            <h2>{{ $report['status_label'] }}</h2>
            <p>
                Pemeriksaan ini memvalidasi portal publik, pemisahan akses setiap role,
                keamanan login, akun siswa, alur pengajuan, status aset, badge notifikasi,
                dan pengiriman email. Tidak ada data yang diubah.
            </p>
            <small>Diperiksa pada {{ $report['generated_at']->format('d/m/Y H:i:s') }}</small>
        </div>

        <div class="readiness-score">
            <span>Skor pengujian</span>
            <strong>{{ $report['score'] }}</strong>
            <small>dari 100</small>
        </div>
    </section>

    <div class="readiness-toolbar no-print">
        <div>
            <strong>Jalankan setelah perubahan route, role, atau transaksi utama.</strong>
            <span>Peringatan email dalam mode log masih normal pada localhost.</span>
        </div>

        <div class="detail-actions">
            <a href="{{ route('admin.acceptance-tests.index') }}" class="button-secondary">Jalankan ulang</a>
            <a href="{{ route('admin.acceptance-tests.download') }}" class="button-primary button-link">Unduh laporan</a>
            <button type="button" class="button-secondary" onclick="window.print()">Cetak</button>
        </div>
    </div>

    <div class="stat-grid readiness-stat-grid">
        <article class="stat-card">
            <span>Total pemeriksaan</span>
            <strong>{{ number_format($report['total']) }}</strong>
        </article>
        <article class="stat-card readiness-stat-pass">
            <span>Lulus</span>
            <strong>{{ number_format($report['counts']['pass']) }}</strong>
        </article>
        <article class="stat-card readiness-stat-warning">
            <span>Peringatan</span>
            <strong>{{ number_format($report['counts']['warning']) }}</strong>
        </article>
        <article class="stat-card readiness-stat-fail">
            <span>Gagal</span>
            <strong>{{ number_format($report['counts']['fail']) }}</strong>
        </article>
    </div>

    @foreach ($report['grouped_checks'] as $category)
        <section class="panel readiness-category">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>
                    <h2>{{ $category['label'] }}</h2>
                </div>
                <span class="badge badge-neutral">{{ count($category['checks']) }} pemeriksaan</span>
            </div>

            <div class="readiness-check-list">
                @foreach ($category['checks'] as $check)
                    <article class="readiness-check readiness-check-{{ $check['status'] }}">
                        <div class="readiness-check-icon">
                            @if ($check['status'] === 'pass')
                                ✓
                            @elseif ($check['status'] === 'warning')
                                !
                            @else
                                ×
                            @endif
                        </div>

                        <div class="readiness-check-content">
                            <div class="readiness-check-heading">
                                <h3>{{ $check['title'] }}</h3>
                                <span class="badge {{ $check['status'] === 'pass' ? 'badge-success' : ($check['status'] === 'warning' ? 'badge-warning' : 'badge-danger') }}">
                                    {{ $check['status'] === 'pass' ? 'Lulus' : ($check['status'] === 'warning' ? 'Peringatan' : 'Gagal') }}
                                </span>
                            </div>

                            <p class="readiness-result">{{ $check['message'] }}</p>

                            @if ($check['recommendation'])
                                <div class="readiness-recommendation">
                                    <strong>Tindakan:</strong>
                                    <span>{{ $check['recommendation'] }}</span>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach

    <section class="panel readiness-manual-section">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Uji manual setiap role</p>
                <h2>Checklist penerimaan sistem</h2>
                <p class="panel-description">
                    Gunakan akun percobaan terpisah untuk setiap role. Setelah selesai,
                    hapus atau nonaktifkan data percobaan.
                </p>
            </div>
        </div>

        <div class="readiness-manual-grid">
            @foreach ($report['manual_checklist'] as $section)
                <article class="readiness-manual-card">
                    <h3>{{ $section['title'] }}</h3>
                    <ol>
                        @foreach ($section['items'] as $item)
                            <li>
                                <span class="readiness-checkbox"></span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ol>
                </article>
            @endforeach
        </div>
    </section>

    <div class="inline-notice">
        Tahap 34 dinyatakan selesai apabila tidak ada pemeriksaan gagal dan
        seluruh checklist manual telah diuji menggunakan akun setiap role.
    </div>
@endsection
