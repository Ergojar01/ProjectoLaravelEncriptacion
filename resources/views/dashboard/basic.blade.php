@extends('layouts.app')
@section('title', 'Inicio')

@section('content')
    <div class="page-head">
        <div>
            <h1>Bienvenido, {{ $user->name }}</h1>
            <p>Tu cuenta está activa.</p>
        </div>
    </div>

    <div class="card" style="max-width:560px">
        <div class="card-head"><h3>Mi cuenta</h3></div>
        <ul class="kv">
            <li><span>Nombre</span><b>{{ $user->name }}</b></li>
            <li><span>Correo</span><b>{{ $user->email }}</b></li>
            <li><span>Nivel de acceso</span><b><span class="badge {{ $user->role_class }}">{{ $user->role_level }} · {{ $user->role_name }}</span></b></li>
            <li><span>Permisos</span><b>Acceso mínimo al panel</b></li>
        </ul>
    </div>
@endsection
