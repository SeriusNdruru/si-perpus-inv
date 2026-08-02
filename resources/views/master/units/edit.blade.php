@extends('layouts.app')

@section('title', 'Edit Satuan')
@section('page-title', 'Edit Satuan')

@section('content')
    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">{{ $unit->unit_code }}</p>
                <h2>{{ $unit->unit_name }}</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('units.update', $unit) }}" class="data-form">
            @csrf
            @method('PUT')
            @include('master.units._form', [
                'unit' => $unit,
                'submitLabel' => 'Simpan perubahan',
            ])
        </form>
    </section>
@endsection
