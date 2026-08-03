@extends('layouts.public')

@section('title', 'Tentang')

@section('content')
@php
    $videoUrl = trim((string) ($systemBrand['portal.about_video_url'] ?? ''));
    $embedUrl = null;
    $videoProvider = null;

    if ($videoUrl !== '') {
        $youtubePatterns = [
            '~(?:youtube\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,})~i',
            '~youtube\.com/watch\?.*?[?&]v=([A-Za-z0-9_-]{6,})~i',
        ];

        foreach ($youtubePatterns as $pattern) {
            if (preg_match($pattern, $videoUrl, $matches)) {
                $embedUrl = 'https://www.youtube-nocookie.com/embed/'.$matches[1].'?rel=0';
                $videoProvider = 'YouTube';
                break;
            }
        }

        if ($embedUrl === null && preg_match('~vimeo\.com/(?:video/)?(\d+)~i', $videoUrl, $matches)) {
            $embedUrl = 'https://player.vimeo.com/video/'.$matches[1];
            $videoProvider = 'Vimeo';
        }
    }

    $aboutContent = trim((string) ($systemBrand['portal.about_content'] ?? ''));
    $aboutParagraphs = $aboutContent === ''
        ? []
        : (preg_split('/(?:\r\n|\r|\n){2,}/', $aboutContent) ?: []);
@endphp

<section class="portal-page-hero">
    <div class="portal-container">
        <span class="portal-kicker">Profil layanan</span>
        <h1>{{ $systemBrand['portal.about_title'] ?? 'Tentang Perpustakaan' }}</h1>
        <p>{{ $systemBrand['institution.name'] ?? '' }}</p>
    </div>
</section>

<section class="portal-section">
    <div class="portal-container portal-about-grid">
        <article class="portal-prose">
            <div class="portal-about-copy">
                @forelse ($aboutParagraphs as $paragraph)
                    <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                @empty
                    <p>-</p>
                @endforelse
            </div>
        </article>

        <aside>
            @if ($embedUrl)
                <div class="portal-video">
                    <iframe
                        src="{{ $embedUrl }}"
                        title="Video tentang perpustakaan"
                        loading="lazy"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen
                    ></iframe>
                </div>
                <a class="portal-video-link" href="{{ $videoUrl }}" target="_blank" rel="noopener noreferrer">
                    Buka video di {{ $videoProvider ?? 'situs asal' }}
                </a>
            @else
                <div class="portal-video-placeholder">
                    <span>▶</span>
                    <strong>Video profil belum ditambahkan</strong>
                    <p>Super Admin dapat memasukkan tautan YouTube atau Vimeo melalui Pengaturan Sistem.</p>
                </div>
            @endif

            <div class="portal-info-card">
                <span>Jam layanan</span>
                <strong>{{ $systemBrand['portal.opening_hours'] ?? '-' }}</strong>
                <p>{{ $systemBrand['institution.address'] ?? '-' }}</p>
            </div>
        </aside>
    </div>
</section>
@endsection
