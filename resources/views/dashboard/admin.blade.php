@extends('layouts.app')
@section('title', 'Inicio')

@section('content')
    <div class="page-head">
        <div>
            <h1>Hola, {{ $user->name }}</h1>
            <p>Tienes control total del sistema.</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-primary">Gestionar usuarios</a>
    </div>
    @include('dashboard._overview')
@endsection
