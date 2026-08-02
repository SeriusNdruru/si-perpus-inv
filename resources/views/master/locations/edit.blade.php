@extends('layouts.app')

@section('title', 'Edit Lokasi')
@section('page-title', 'Edit Lokasi')

@section('content')
    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">{{ $location->location_code }}</p>
                <h2>{{ $location->location_name }}</h2>
                <p class="panel-description">Perbarui identitas, hubungan induk, jenis, dan status lokasi.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('locations.update', $location) }}">
            @csrf
            @method('PUT')
            @include('master.locations._form', [
                'submitLabel' => 'Simpan perubahan',
            ])
        </form>
    </section>
@endsection
