@extends('layouts.app')

@section('title', 'Reset Password')
@section('page-title', 'Reset Password Pengguna')

@section('content')
    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Administrasi sistem</p>
                <h2>{{ $managedUser->full_name }}</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.users.password.update', $managedUser) }}" class="data-form">
            @csrf
            @method('PATCH')

            @if ($errors->any())
                <div class="alert alert-danger form-errors">
                    <strong>Password belum dapat diperbarui.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="form-grid">
                <div class="form-field form-field-full">
                    <label>Identitas akun</label>
                    <input type="text" value="{{ $managedUser->username }}" disabled>
                    <small>Password lama tidak ditampilkan dan tidak diperlukan saat Super Admin melakukan reset.</small>
                </div>

                <div class="form-field">
                    <label for="password">Password baru <span>*</span></label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required>
                    <small>Minimal 8 karakter dan mengandung huruf serta angka.</small>
                </div>

                <div class="form-field">
                    <label for="password_confirmation">Konfirmasi password baru <span>*</span></label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                    >
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('admin.users.index') }}" class="button-secondary">Batal</a>
                <button type="submit" class="button-primary">Perbarui password</button>
            </div>
        </form>
    </section>
@endsection
