@extends('layouts.app')

@section('title', $title)
@section('page-title', $title)

@section('content')
    <div class="panel placeholder-panel">
        <span class="placeholder-icon">01</span>
        <h2>{{ $title }} sudah terhubung ke struktur proyek</h2>
        <p>{{ $description }}</p>
        <a href="{{ route('dashboard') }}" class="button-primary button-link">Kembali ke dashboard</a>
    </div>
@endsection
