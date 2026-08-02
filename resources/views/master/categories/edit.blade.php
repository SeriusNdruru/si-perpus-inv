@extends('layouts.app')

@section('title', 'Edit Kategori')
@section('page-title', 'Edit Kategori')

@section('content')
    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">{{ $category->category_code }}</p>
                <h2>{{ $category->category_name }}</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('categories.update', $category) }}" class="data-form">
            @csrf
            @method('PUT')
            @include('master.categories._form', [
                'category' => $category,
                'submitLabel' => 'Simpan perubahan',
            ])
        </form>
    </section>
@endsection
