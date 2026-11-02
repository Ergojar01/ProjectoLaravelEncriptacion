@extends('layouts.guest')
@section('title', 'Recuperar contraseña')

@section('content')
    <h2 class="auth-title">¿Olvidaste tu contraseña?</h2>
    <p class="muted auth-sub">La recuperación la gestiona el administrador del sistema.</p>

    @if(session('status')) <div class="alert alert-ok" role="status">{{ session('status') }}</div> @endif
    @if($errors->any()) <div class="alert alert-error" role="alert">{{ $errors->first() }}</div> @endif

    <ol class="recover-steps">
        <li>Escribe tu correo y solicita la clave: el administrador verá tu solicitud en su panel (cuentas de nivel 300 y 499).</li>
        <li>El administrador te entrega una <b>clave temporal</b>. Sirve una sola vez y vence en 24 horas.</li>
        <li>Inicia sesión con esa clave: irás directo a la pantalla para crear tu contraseña nueva, no al panel.</li>
        <li>Tendrás <b>5 minutos</b> para cambiarla, con un contador a la vista. Si se acaba el tiempo, se cierra tu sesión y tu cuenta queda bloqueada 5 minutos. El administrador deberá darte una clave nueva.</li>
    </ol>

    <form method="POST" action="{{ route('password.recover.store') }}" novalidate>
        @csrf
        <div class="field">
            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="nakama@grandline.com" autocomplete="username" required>
        </div>
        <button class="btn btn-primary btn-block">Solicitar clave temporal</button>
    </form>

    <p class="muted small" style="margin-top:1rem">Si eres administrador (nivel 500), otro administrador o el responsable del sistema debe restablecer tu contraseña desde la consola.</p>

    <p class="auth-alt"><a href="{{ route('login') }}">← Volver a iniciar sesión</a></p>
@endsection
