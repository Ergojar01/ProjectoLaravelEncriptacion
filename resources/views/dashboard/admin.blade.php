@extends('layouts.app')
@section('title', 'Inicio')

@section('content')
    <div class="page-head">
        <div>
            <h1>Hola, {{ $user->name }}</h1>
            <p>Tienes control total del sistema. ¡Bienvenido!</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-primary">Gestionar usuarios</a>
    </div>

    <!-- Tarjeta para la imagen de bienvenida del Administrador -->
    <div class="card" style="margin-bottom: 24px; text-align: center;">
        <div style="padding: 20px;">
            <img src="{{ asset('images/bienvenida.gif') }}" alt="Bienvenido al sistema" style="max-width: 100%; height: auto; border-radius: 6px;">
        </div>
    </div>

    @include('dashboard._overview')
@endsection