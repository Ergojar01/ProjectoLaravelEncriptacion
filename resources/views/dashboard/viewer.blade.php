@extends('layouts.app')
@section('title', 'Panel de Lectura')

@section('content')
    <div class="page-head">
        <div>
            <h1>Hola, {{ $user->name }}</h1>
            <p>Tienes acceso de solo lectura al sistema. ¡Bienvenido!</p>
        </div>
    </div>

    <!-- Tarjeta de bienvenida universal -->
    <div class="card" style="max-width: 560px; margin-bottom: 24px; text-align: center;">
        <div style="padding: 20px;">
            <img src="{{ asset('images/bienvenida.gif') }}" alt="Bienvenido al sistema" style="max-width: 100%; height: auto; border-radius: 6px;">
        </div>
    </div>

    <div class="card" style="max-width: 560px">
        <div class="card-head"><h3>Resumen del Sistema</h3></div>
        <ul class="kv">
            <li><span>Usuarios Registrados</span><b>{{ $stats['total'] }}</b></li>
            <li><span>Nivel de acceso</span><b><span class="badge">{{ $user->role_level }}</span></b></li>
            <li><span>Permisos</span><b>Visualización de datos</b></li>
        </ul>
    </div>
@endsection