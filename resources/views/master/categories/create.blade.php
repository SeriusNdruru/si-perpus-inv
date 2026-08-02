@extends('layouts.app')

@section('title', 'Tambah Kategori')
@section('page-title', 'Tambah Kategori')

@section('content')
    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Master data</p>
                <h2>Informasi Kategori</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('categories.store') }}" class="data-form">
            @csrf
            @include('master.categories._form', [
                'category' => null,
                'submitLabel' => 'Simpan kategori',
            ])
        </form>
    </section>
@endsection
