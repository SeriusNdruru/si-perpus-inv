@extends('layouts.app')

@section('title', 'Tambah Supplier')
@section('page-title', 'Tambah Supplier')

@section('content')
    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Master data</p>
                <h2>Informasi Supplier</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('suppliers.store') }}" class="data-form">
            @csrf
            @include('master.suppliers._form', [
                'supplier' => null,
                'submitLabel' => 'Simpan supplier',
            ])
        </form>
    </section>
@endsection
