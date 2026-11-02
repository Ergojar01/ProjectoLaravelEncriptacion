@extends('layouts.guest')
@section('title', 'Iniciar sesión')

@section('content')
    <!-- Si la sesión caduca dentro del portal, sale del iframe y vuelve al login -->
    <script>
        if (window.self !== window.top) {
            window.top.location.href = '{{ route("login") }}';
        }
    </script>

    <h2 class="auth-title">Abordar el barco</h2>
    <p class="muted auth-sub">Ingresa tus credenciales para entrar al sistema.</p>

    @if($errors->any()) <div class="alert alert-error" role="alert">{{ $errors->first() }}</div> @endif
    @if(session('error')) <div class="alert alert-error" role="alert">{{ session('error') }}</div> @endif

    @if(session('lock_seconds'))
        <div class="timer" data-countdown="{{ session('lock_seconds') }}" data-total="300" data-on-end="unlock">
            <div>
                <small data-countdown-label>Cuenta bloqueada · podrás intentar de nuevo en</small>
                <div class="timer-bar"><i></i></div>
            </div>
            <span class="timer-count" data-countdown-text>05:00</span>
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <div class="field">
            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="nakama@grandline.com" autocomplete="username" required autofocus>
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

    <p class="auth-alt">¿Aún no tienes cuenta? <a href="{{ route('register') }}">Súmate a la tripulación</a></p>
    <p class="auth-alt"><a href="{{ route('password.recover') }}">¿Olvidaste tu contraseña?</a></p>
@endsection
