@extends('layouts.app')

@section('title', 'Pengujian Sistem')
@section('page-title', 'Pengujian Sistem')

@section('content')
    @php
        $heroClass = match ($report['status']) {
            'ready' => 'readiness-hero-ready',
            'ready_with_notes' => 'readiness-hero-warning',
            default => 'readiness-hero-fail',
        };
    @endphp

    <section class="readiness-hero {{ $heroClass }}">
        <div>
            <h2>{{ $report['status_label'] }}</h2>
            <p>
                Pemeriksaan ini membaca konfigurasi, route, struktur database, konsistensi data, folder penyimpanan, dan backup. Tidak ada data transaksi yang diubah.
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
            <strong>Jalankan pemeriksaan setelah setiap perbaikan.</strong>
            <span>Nilai peringatan saat masih menggunakan localhost merupakan kondisi normal.</span>
        </div>
        <div class="detail-actions">
            <a href="{{ route('admin.system-readiness.index') }}" class="button-secondary">Jalankan ulang</a>
            <a href="{{ route('admin.system-readiness.download') }}" class="button-primary button-link">Unduh laporan</a>
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

    @foreach ($report['grouped_checks'] as $categoryKey => $category)
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

                            @if ($check['detail'])
                                <p class="readiness-detail">{{ $check['detail'] }}</p>
                            @endif

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
                <p class="eyebrow">Uji penerimaan pengguna</p>
                <h2>Checklist pengujian manual</h2>
                <p class="panel-description">
                    Pemeriksaan otomatis tidak menggantikan pengujian transaksi. Centang daftar ini pada salinan cetak atau laporan yang sudah diunduh.
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
        Status <strong>siap untuk hosting</strong> dicapai saat tidak ada pemeriksaan gagal. Peringatan untuk APP_ENV, APP_DEBUG, dan HTTPS harus diselesaikan ketika aplikasi benar-benar dipindahkan dari localhost.
    </div>
@endsection
