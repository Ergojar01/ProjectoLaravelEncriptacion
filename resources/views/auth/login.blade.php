@extends('layouts.guest')
@section('title', 'Iniciar sesión')

@section('content')
    <!-- SCRIPT DE ESCAPE DEL IFRAME -->
    <script>
        if (window.self !== window.top) {
            window.top.location.href = '{{ route("login") }}';
        }
    </script>

    <h2 class="auth-title">Bienvenido de nuevo</h2>
    <p class="muted auth-sub">Ingresa tus credenciales para acceder al sistema.</p>

    @if($errors->any()) <div class="alert alert-error">{{ $errors->first() }}</div> @endif

    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <div class="field">
            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="usuario@empresa.com" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label for="password">Contraseña</label>
            <div class="input-wrap">
                <input id="password" name="password" type="password" placeholder="••••••••••" autocomplete="current-password" required>
                <button type="button" class="toggle" data-toggle-pass="password">Mostrar</button>
            </div>
        </div>
        <label class="check"><input type="checkbox" name="remember"> Mantener sesión iniciada</label>
        <button class="btn btn-primary btn-block">Iniciar sesión</button>
    </form>

    <p class="auth-alt">¿No tienes cuenta? <a href="{{ route('register') }}">Regístrate</a></p>
@endsection