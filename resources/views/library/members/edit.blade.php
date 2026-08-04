@extends('layouts.app')

@section('title', 'Edit Anggota')
@section('page-title', 'Edit Anggota Perpustakaan')

@section('content')
    <section class="panel form-panel form-panel-wide member-form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">{{ $member->member_code }}</p>
                <h2>{{ $member->member_name }}</h2>
            </div>
        </div>
        <form method="POST" action="{{ route('library.members.update', $member) }}" class="data-form member-account-form">
            @csrf
            @method('PUT')
            @include('library.members._form', ['submitLabel' => 'Simpan perubahan'])
        </form>
    </section>
@endsection
