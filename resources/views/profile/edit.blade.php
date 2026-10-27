@extends('layouts.app')
@section('title', 'Mi perfil')

@section('content')
    <div class="page-head">
        <div>
            <h1>Mi perfil</h1>
            <p>Administra el acceso a tu cuenta.</p>
        </div>
    </div>

    <div class="prof-grid">
        <div class="card">
            <div class="card-head"><h3>Tu cuenta</h3></div>
            <ul class="kv">
                <li><span>Nombre</span><b>{{ $user->name }}</b></li>
                <li><span>Correo</span><b>{{ $user->email }}</b></li>
                <li><span>Nivel de acceso</span><b><span class="badge {{ $user->role_class }}">{{ $user->role_level }} · {{ $user->role_name }}</span></b></li>
            </ul>
        </div>

        <div class="card">
            <div class="card-head"><h3>Cambiar contraseña</h3></div>
            <form class="card-body" method="POST" action="{{ route('profile.password') }}" novalidate>
                @csrf
                @method('PUT')

                @if($errors->any())
                    <div class="alert alert-error" role="alert">
                        @foreach($errors->all() as $error) <div>{{ $error }}</div> @endforeach
                    </div>
                @endif

                <div class="field">
                    <label for="current_password">Contraseña actual</label>
                    <div class="input-wrap">
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                        <button type="button" class="toggle" data-toggle-pass="current_password">Mostrar</button>
                    </div>
                </div>

                <div class="field">
                    <label for="new_password">Contraseña nueva</label>
                    <div class="input-wrap">
                        <input id="new_password" name="password" type="password" autocomplete="new-password" required>
                        <button type="button" class="toggle" data-toggle-pass="new_password">Mostrar</button>
                    </div>
                </div>
                <ul class="rules" data-pass-rules="new_password">
                    <li data-rule="len">Mínimo 9 caracteres</li>
                    <li data-rule="up">Una mayúscula</li>
                    <li data-rule="low">Una minúscula</li>
                    <li data-rule="num">Un número</li>
                    <li data-rule="sym">Un símbolo</li>
                </ul>

                <div class="field">
                    <label for="new_password_confirmation">Confirmar contraseña nueva</label>
                    <input id="new_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                </div>

                <button class="btn btn-primary">Guardar contraseña</button>
                <p class="muted small note">Al guardar se genera una sal nueva de 256 bits y la contraseña se sella otra vez con PBKDF2-SHA512, XOR y HMAC.</p>
            </form>
        </div>
    </div>
@endsection
