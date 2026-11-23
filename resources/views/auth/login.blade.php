@extends('layouts.guest')
@section('title', 'Iniciar sesión')

@section('content')
    <h2 class="auth-title">Abordar el barco</h2>
    <p class="muted auth-sub">Ingresa tus credenciales para entrar al sistema.</p>

    @if(session('status')) <div class="alert alert-ok" role="status">{{ session('status') }}</div> @endif
    @if($errors->any()) <div class="alert alert-error" role="alert">{{ $errors->first() }}</div> @endif
    @if(session('error')) <div class="alert alert-error" role="alert">{{ session('error') }}</div> @endif

    @if(session('lock_seconds'))
        @php($lockTotal = (int) config('security.login.lock_seconds'))
        <div class="timer" data-countdown="{{ (int) session('lock_seconds') }}" data-total="{{ $lockTotal }}" data-on-end="unlock">
            <div>
                <small data-countdown-label>Acceso bloqueado · podrás intentar de nuevo en</small>
                <div class="timer-bar"><i></i></div>
            </div>
            <span class="timer-count" data-countdown-text>{{ sprintf('%02d:%02d', intdiv($lockTotal, 60), $lockTotal % 60) }}</span>
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
        <button class="btn btn-primary btn-block">Iniciar sesión</button>
    </form>

    @if(config('security.registration.enabled'))
        <p class="auth-alt"><a href="{{ route('register') }}">Crear cuenta</a></p>
    @else
        <p class="auth-alt muted small">Las cuentas las crea el administrador del sistema.</p>
    @endif
    <p class="auth-alt"><a href="{{ route('password.recover') }}">¿Olvidaste tu contraseña?</a></p>
@endsection
