@extends('layouts.app')

@section('title', 'Edit Kunjungan Siswa')
@section('page-title', 'Edit Kunjungan Siswa')

@section('content')
<section class="panel data-form-panel">
    <div class="panel-header panel-header-wrap">
        <div><p class="eyebrow">Kunjungan perpustakaan</p><h2>Perbarui catatan kunjungan</h2></div>
        <a href="{{ route('library.visits.index') }}" class="button-secondary button-link">Kembali</a>
    </div>

    <form method="POST" action="{{ route('library.visits.update', $visit) }}" class="data-form">
        @csrf
        @method('PUT')
        @include('library.visits._form')
        <div class="form-actions">
            <button type="submit" class="button-primary">Simpan perubahan</button>
            <a href="{{ route('library.visits.index') }}" class="button-secondary button-link">Batal</a>
        </div>
    </form>
</section>
@endsection
