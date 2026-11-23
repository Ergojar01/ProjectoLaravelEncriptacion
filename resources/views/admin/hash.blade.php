@extends('layouts.app')
@section('title', 'Hash almacenado')

@section('content')
    <div class="page-head">
        <div>
            <h1>Hash almacenado</h1>
            <p>{{ $target->name }} · {{ $target->email }}</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.users.index') }}">← Volver a usuarios</a>
    </div>

    <div class="notice">
        Función excepcional y exclusivamente académica: solo lectura, protegida con tu contraseña y registrada en la bitácora.
        No se puede exportar y nunca muestra la contraseña original (no existe: solo se guarda el hash).
    </div>

    @if($hash)
        <div class="card">
            <div class="card-head"><h3>Hash de {{ $target->name }}</h3></div>
            <div class="card-body">
                <p class="muted small">Algoritmo: {{ $target->crypto_version >= 2 ? 'Argon2id (la sal y los parámetros de costo van dentro del propio hash)' : 'esquema anterior v1 (PBKDF2-SHA512 + XOR + HMAC)' }}.</p>
                <code class="hash-box mono">{{ $hash }}</code>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-head"><h3>Confirma tu identidad</h3></div>
            <form class="card-body" method="POST" action="{{ route('admin.users.hash.show', $target) }}" novalidate>
                @csrf
                @if($errors->any())
                    <div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
                @endif
                <div class="field">
                    <label for="admin_password">Tu contraseña de administrador</label>
                    <div class="input-wrap">
                        <input id="admin_password" name="admin_password" type="password" autocomplete="current-password" required autofocus>
                        <button type="button" class="toggle" data-toggle-pass="admin_password">Mostrar</button>
                    </div>
                </div>
                <button class="btn btn-primary">Ver hash</button>
            </form>
        </div>
    @endif
@endsection
