@extends('layouts.guest')
@section('title', 'Crear cuenta')

@section('content')
    <h2 class="auth-title">Crear cuenta</h2>
    <p class="muted auth-sub">Tu cuenta se crea con acceso básico (nivel 300).</p>

    @if($errors->any())
        <div class="alert alert-error">
            @foreach($errors->all() as $error) <div>{{ $error }}</div> @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('register.store') }}" novalidate>
        @csrf
        <div class="field">
            <label for="name">Nombre completo</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="Nombre y apellido" autocomplete="name" required autofocus>
        </div>
        <div class="field">
            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="usuario@empresa.com" autocomplete="username" required>
        </div>
        <div class="field">
            <label for="password">Contraseña</label>
            <div class="input-wrap">
                <input id="password" name="password" type="password" autocomplete="new-password" required>
                <button type="button" class="toggle" data-toggle-pass="password">Mostrar</button>
            </div>
        </div>
        <ul class="rules" data-pass-rules="password">
            <li data-rule="len">Mínimo 9 caracteres</li>
            <li data-rule="up">Una mayúscula</li>
            <li data-rule="low">Una minúscula</li>
            <li data-rule="num">Un número</li>
            <li data-rule="sym">Un símbolo</li>
        </ul>
        <div class="field">
            <label for="password_confirmation">Confirmar contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>
        <button class="btn btn-primary btn-block">Crear cuenta</button>
    </form>

    <p class="auth-alt">¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a></p>
@endsection
