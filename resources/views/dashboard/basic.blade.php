@extends('layouts.app')
@section('title', 'Inicio')

@section('content')
    <div class="page-head">
        <div>
            <h1>¡Bienvenido, {{ $user->name }}!</h1>
            <p>Tu cuenta está activa. Explora las opciones en el menú lateral para comenzar a trabajar.</p>
        </div>
    </div>

    <!-- Nueva tarjeta para la imagen de bienvenida -->
    <div class="card" style="max-width: 560px; margin-bottom: 24px; text-align: center;">
        <div style="padding: 20px;">
            <!-- Asegúrate de colocar tu imagen en public/images/bienvenida.jpg -->
            <img src="{{ asset('images/bienvenida.gif') }}" alt="Bienvenido al sistema" style="max-width: 100%; height: auto; border-radius: 6px;">
        </div>
    </div>

    <div class="card" style="max-width: 560px">
        <div class="card-head"><h3>Mi cuenta</h3></div>
        <ul class="kv">
            <li><span>Nombre</span><b>{{ $user->name }}</b></li>
            <li><span>Correo</span><b>{{ $user->email }}</b></li>
            <li><span>Nivel de acceso</span><b><span class="badge {{ $user->role_class }}">{{ $user->role_level }} · {{ $user->role_name }}</span></b></li>
            <li><span>Permisos</span><b>Acceso mínimo al panel</b></li>
        </ul>
    </div>
@endsection