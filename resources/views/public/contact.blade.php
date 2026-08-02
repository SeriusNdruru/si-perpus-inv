@extends('layouts.public')

@section('title', 'Kontak')

@section('content')
<section class="portal-page-hero">
    <div class="portal-container">
        <span class="portal-kicker">Kontak pengelola</span>
        <h1>Kirim pertanyaan atau masukan</h1>
        <p>{{ $systemBrand['portal.contact_intro'] ?? '' }}</p>
    </div>
</section>

<section class="portal-section">
    <div class="portal-container portal-contact-grid">
        <aside class="portal-contact-info">
            <article><span>Alamat</span><strong>{{ $systemBrand['institution.address'] ?? '-' }}</strong></article>
            <article><span>Telepon</span><strong>{{ $systemBrand['institution.phone'] ?: '-' }}</strong></article>
            <article><span>Email</span><strong>{{ $systemBrand['institution.email'] ?: '-' }}</strong></article>
            <article><span>Jam layanan</span><strong>{{ $systemBrand['portal.opening_hours'] ?? '-' }}</strong></article>
        </aside>

        <form method="POST" action="{{ route('public.contact.store') }}" class="portal-form">
            @csrf
            <div class="portal-form-grid">
                <label>
                    <span>Nama *</span>
                    <input name="sender_name" type="text" maxlength="180" value="{{ old('sender_name') }}" required>
                </label>
                <label>
                    <span>Email</span>
                    <input name="sender_email" type="email" maxlength="150" value="{{ old('sender_email') }}">
                </label>
                <label>
                    <span>Nomor telepon</span>
                    <input name="sender_phone" type="text" maxlength="50" value="{{ old('sender_phone') }}">
                </label>
                <label class="portal-field-full">
                    <span>Subjek *</span>
                    <input name="subject" type="text" maxlength="220" value="{{ old('subject') }}" required>
                </label>
                <label class="portal-field-full">
                    <span>Pesan *</span>
                    <textarea name="message" rows="7" maxlength="5000" required>{{ old('message') }}</textarea>
                </label>
                <input name="website" type="text" tabindex="-1" autocomplete="off" class="portal-honeypot">
            </div>
            <button type="submit" class="portal-button portal-button-primary">Kirim pesan</button>
        </form>
    </div>
</section>
@endsection
