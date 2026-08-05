@extends('layouts.app')

@section('title', 'Tambah Lokasi')
@section('page-title', 'Tambah Lokasi')

@section('content')
    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Master data</p>
                <h2>Lokasi Baru</h2>
                <p class="panel-description">Buat gedung, lantai, ruangan, gudang, atau lemari untuk penempatan aset.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('locations.store') }}" class="data-form">
            @csrf
            @include('master.locations._form', [
                'location' => null,
                'submitLabel' => 'Simpan lokasi',
            ])
        </form>
    </section>
@endsection
