@extends('layouts.app')

@section('title', 'Tambah Barang')
@section('page-title', 'Tambah Barang Inventaris')

@section('content')
    <section class="panel form-panel form-panel-wide">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Inventaris</p>
                <h2>Data Barang dan Stok Awal</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('inventory.items.store') }}" enctype="multipart/form-data" class="data-form" id="item-form">
            @csrf
            @include('inventory.items._create-form')
        </form>
    </section>

    <script src="{{ asset('js/item-form.js') }}?v=121" defer></script>
    <script src="{{ asset('js/item-image-preview.js') }}" defer></script>
@endsection
