@extends('layouts.app')

@section('title', 'Tambah Satuan')
@section('page-title', 'Tambah Satuan')

@section('content')
    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Master data</p>
                <h2>Informasi Satuan</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('units.store') }}" class="data-form">
            @csrf
            @include('master.units._form', [
                'unit' => null,
                'submitLabel' => 'Simpan satuan',
            ])
        </form>
    </section>
@endsection
