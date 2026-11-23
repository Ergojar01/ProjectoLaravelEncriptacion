@extends('layouts.guest')
@section('title', 'Nueva contraseña')

@section('content')
    @if(! $valid)
        <h2 class="auth-title">Enlace no válido</h2>
        <p class="muted auth-sub">Este enlace ya se usó, venció o no existe. Los enlaces sirven una sola vez.</p>
        <p class="auth-alt"><a href="{{ route('password.recover') }}">Solicitar uno nuevo</a></p>
        <p class="auth-alt"><a href="{{ route('login') }}">← Volver a iniciar sesión</a></p>
    @else
        <h2 class="auth-title">Crea tu contraseña nueva</h2>
        <p class="muted auth-sub">El administrador autorizó el restablecimiento. Solo tú conocerás la contraseña.</p>

        @if($errors->any())
            <div class="alert alert-error" role="alert">
                @foreach($errors->all() as $error) <div>{{ $error }}</div> @endforeach
            </div>
        @endif

        <div class="timer" data-countdown="{{ $seconds }}" data-total="{{ $total }}" data-on-end="reload">
            <div>
                <small data-countdown-label>El enlace vence en</small>
                <div class="timer-bar"><i></i></div>
            </div>
            <span class="timer-count" data-countdown-text>{{ sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60) }}</span>
        </div>

        <form method="POST" action="{{ route('password.reset.store', ['token' => $token]) }}" novalidate>
            @csrf
            <div class="field">
                <label for="new_password">Contraseña nueva</label>
                <div class="input-wrap">
                    <input id="new_password" name="password" aria-describedby="new_password_rules" type="password" autocomplete="new-password" required autofocus>
                    <button type="button" class="toggle" data-toggle-pass="new_password">Mostrar</button>
                </div>
            </div>
            @include('components.password-rules', ['input' => 'new_password'])
            <div class="field">
                <label for="new_password_confirmation">Confirmar contraseña nueva</label>
                <input id="new_password_confirmation" name="password_confirmation" aria-describedby="new_password_rules" type="password" autocomplete="new-password" required>
            </div>
            <button class="btn btn-primary btn-block">Guardar contraseña</button>
        </form>
    @endif
@endsection
