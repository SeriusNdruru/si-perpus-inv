@extends('layouts.app')

@section('title', 'Catat Kunjungan Siswa')
@section('page-title', 'Catat Kunjungan Siswa')

@section('content')
<section class="panel data-form-panel">
    <div class="panel-header panel-header-wrap">
        <div><p class="eyebrow">Kunjungan perpustakaan</p><h2>Pilih siswa yang datang membaca</h2></div>
        <a href="{{ route('library.visits.index') }}" class="button-secondary button-link">Kembali</a>
    </div>

    <form method="POST" action="{{ route('library.visits.store') }}" class="data-form">
        @csrf
        @include('library.visits._form')
        <div class="form-actions">
            <button type="submit" class="button-primary">Simpan kunjungan</button>
            <a href="{{ route('library.visits.index') }}" class="button-secondary button-link">Batal</a>
        </div>
    </form>
</section>
@endsection
