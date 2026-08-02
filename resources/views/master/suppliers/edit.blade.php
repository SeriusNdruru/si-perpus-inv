@extends('layouts.app')

@section('title', 'Edit Supplier')
@section('page-title', 'Edit Supplier')

@section('content')
    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">{{ $supplier->supplier_code }}</p>
                <h2>{{ $supplier->supplier_name }}</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('suppliers.update', $supplier) }}" class="data-form">
            @csrf
            @method('PUT')
            @include('master.suppliers._form', [
                'supplier' => $supplier,
                'submitLabel' => 'Simpan perubahan',
            ])
        </form>
    </section>
@endsection
