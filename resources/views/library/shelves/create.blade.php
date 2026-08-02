@extends('layouts.app')

@section('title', 'Tambah Rak')
@section('page-title', 'Tambah Rak Perpustakaan')

@section('content')
    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Master perpustakaan</p>
                <h2>Rak Baru</h2>
                <p class="panel-description">Buat rak yang nantinya digunakan untuk menempatkan setiap eksemplar buku.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('library.shelves.store') }}" class="data-form">
            @csrf
            @include('library.shelves._form', [
                'shelf' => null,
                'submitLabel' => 'Simpan rak',
            ])
        </form>
    </section>
@endsection
