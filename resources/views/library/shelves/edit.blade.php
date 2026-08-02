@extends('layouts.app')

@section('title', 'Edit Rak')
@section('page-title', 'Edit Rak Perpustakaan')

@section('content')
    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">{{ $shelf->shelf_code }}</p>
                <h2>{{ $shelf->shelf_name }}</h2>
                <p class="panel-description">Perbarui lokasi, klasifikasi, kapasitas, dan status rak.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('library.shelves.update', $shelf) }}" class="data-form">
            @csrf
            @method('PUT')
            @include('library.shelves._form', [
                'submitLabel' => 'Simpan perubahan',
            ])
        </form>
    </section>
@endsection
