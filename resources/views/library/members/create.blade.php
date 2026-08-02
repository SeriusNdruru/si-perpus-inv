@extends('layouts.app')

@section('title', 'Tambah Anggota')
@section('page-title', 'Tambah Anggota Perpustakaan')

@section('content')
    <section class="panel form-panel form-panel-wide">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Keanggotaan baru</p>
                <h2>Formulir Anggota</h2>
            </div>
        </div>
        <form method="POST" action="{{ route('library.members.store') }}" class="data-form">
            @csrf
            @include('library.members._form', ['member' => null, 'submitLabel' => 'Simpan anggota'])
        </form>
    </section>
@endsection
