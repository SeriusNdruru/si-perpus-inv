@extends('layouts.app')

@section('title', 'Edit Pengguna')
@section('page-title', 'Edit Pengguna Sistem')

@section('content')
    <section class="panel form-panel">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Administrasi sistem</p>
                <h2>{{ $managedUser->full_name }}</h2>
            </div>
            <a href="{{ route('admin.users.password.edit', $managedUser) }}" class="button-secondary">Reset password</a>
        </div>

        <form method="POST" action="{{ route('admin.users.update', $managedUser) }}" class="data-form">
            @csrf
            @method('PUT')
            @include('admin.users._form', [
                'submitLabel' => 'Simpan perubahan',
            ])
        </form>
    </section>
@endsection
