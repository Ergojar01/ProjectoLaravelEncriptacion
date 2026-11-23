@extends('layouts.guest')
@section('title', 'Recuperar contraseña')

@section('content')
    <h2 class="auth-title">¿Olvidaste tu contraseña?</h2>
    <p class="muted auth-sub">La recuperación la autoriza el administrador, pero la nueva contraseña la eliges tú.</p>

    @if(session('status')) <div class="alert alert-ok" role="status">{{ session('status') }}</div> @endif
    @if($errors->any()) <div class="alert alert-error" role="alert">{{ $errors->first() }}</div> @endif

    <ol class="recover-steps">
        <li>Escribe tu correo y envía la solicitud: el administrador la verá en su panel (cuentas de nivel 300 y 499).</li>
        <li>Si la autoriza, recibirás un <b>enlace de un solo uso</b> que vence en {{ config('security.reset.ttl_minutes') }} minutos.</li>
        <li>Con ese enlace llegas directo a la pantalla para crear tu contraseña nueva. El administrador nunca la conoce.</li>
    </ol>

    <form method="POST" action="{{ route('password.recover.store') }}" novalidate>
        @csrf
        <div class="field">
            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="nakama@grandline.com" autocomplete="username" required>
        </div>
        <button class="btn btn-primary btn-block">Solicitar restablecimiento</button>
    </form>

    <p class="muted small" style="margin-top:1rem">Si eres administrador (nivel 500), el responsable del sistema debe restablecer tu contraseña desde la consola.</p>

    <p class="auth-alt"><a href="{{ route('login') }}">← Volver a iniciar sesión</a></p>
@endsection
