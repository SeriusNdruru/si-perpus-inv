@extends('layouts.app')

@section('title', 'Tambah Pengguna')
@section('page-title', 'Tambah Pengguna Sistem')

@section('content')
    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Administrasi sistem</p>
                <h2>Informasi Akun</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}" class="data-form">
            @csrf
            @include('admin.users._form', [
                'managedUser' => null,
                'selectedRoleCode' => '',
                'submitLabel' => 'Simpan pengguna',
                'isOwnAccount' => false,
            ])
        </form>
    </section>
@endsection
