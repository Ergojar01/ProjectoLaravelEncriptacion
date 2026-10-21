@extends('layouts.app')
@section('title', 'Usuarios')

@section('content')
    <div class="page-head">
        <div>
            <h1>Gestión de usuarios</h1>
            <p>{{ $users->count() }} registros en el sistema.</p>
        </div>
        @if($canManage)
            <button class="btn btn-primary" data-open="modal-new" type="button">+ Nuevo usuario</button>
        @endif
    </div>

    @unless($canManage)
        <div class="notice">Modo solo lectura: tu nivel (Visualizador) no permite modificar usuarios.</div>
    @endunless

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>ID</th><th>Usuario</th><th>Nivel / Rol</th><th>Hash de credencial</th><th>Clave</th>
                    @if($canManage)<th class="right">Acciones</th>@endif
                </tr>
                </thead>
                <tbody>
                @foreach($users as $u)
                    <tr>
                        <td class="mono">#{{ $u->id }}</td>
                        <td>
                            <div class="user-cell">
                                <span class="avatar sm">{{ $u->initials }}</span>
                                <div>
                                    <strong>{{ $u->name }}</strong>
                                    @if($u->id === auth()->id()) <span class="badge b-you">Tú</span> @endif
                                    <div class="muted small">{{ $u->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($canManage && $u->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.level', $u) }}" class="inline-form">
                                    @csrf @method('PATCH')
                                    <select name="role_level" aria-label="Nivel de acceso">
                                        @foreach([500 => '500 · Administrador', 499 => '499 · Visualizador', 300 => '300 · Usuario básico'] as $v => $label)
                                            <option value="{{ $v }}" @selected($u->role_level === $v)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-ghost btn-sm">Guardar</button>
                                </form>
                            @else
                                <span class="badge {{ $u->role_class }}">{{ $u->role_level }} · {{ $u->role_name }}</span>
                            @endif
                        </td>
                        <td class="mono hash" title="Hash protegido">{{ Str::limit($u->password, 24, '…') }}</td>
                        <td>
                            @if($u->crypto_version >= $currentVersion)
                                <span class="badge b-ok">Actualizada</span>
                            @else
                                <span class="badge b-warn">Desactualizada</span>
                            @endif
                        </td>
                        @if($canManage)
                            <td class="right">
                                @if($u->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}" data-confirm="¿Eliminar a {{ $u->name }}? Esta acción no se puede deshacer.">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger btn-sm">Eliminar</button>
                                    </form>
                                @else
                                    <span class="muted small">Cuenta actual</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($canManage)
        <div class="modal {{ $errors->any() && old('form') === 'create' ? 'open' : '' }}" id="modal-new">
            <div class="modal-box">
                <div class="modal-head">
                    <h3>Registrar nuevo usuario</h3>
                    <button class="x" data-close type="button" aria-label="Cerrar">×</button>
                </div>
                <div class="modal-body">
                    @if($errors->any() && old('form') === 'create')
                        <div class="alert alert-error">
                            @foreach($errors->all() as $error) <div>{{ $error }}</div> @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.users.store') }}" novalidate>
                        @csrf
                        <input type="hidden" name="form" value="create">
                        <div class="field">
                            <label for="n_name">Nombre completo</label>
                            <input id="n_name" name="name" type="text" value="{{ old('name') }}" required>
                        </div>
                        <div class="field">
                            <label for="n_email">Correo electrónico</label>
                            <input id="n_email" name="email" type="email" value="{{ old('email') }}" required>
                        </div>
                        <div class="field">
                            <label for="n_pass">Contraseña</label>
                            <div class="input-wrap">
                                <input id="n_pass" name="password" type="password" autocomplete="new-password" required>
                                <button type="button" class="toggle" data-toggle-pass="n_pass">Mostrar</button>
                            </div>
                        </div>
                        <ul class="rules" data-pass-rules="n_pass">
                            <li data-rule="len">Mínimo 9 caracteres</li>
                            <li data-rule="up">Una mayúscula</li>
                            <li data-rule="low">Una minúscula</li>
                            <li data-rule="num">Un número</li>
                            <li data-rule="sym">Un símbolo</li>
                        </ul>
                        <div class="field">
                            <label for="n_conf">Confirmar contraseña</label>
                            <input id="n_conf" name="password_confirmation" type="password" autocomplete="new-password" required>
                        </div>
                        <div class="field">
                            <label for="n_level">Nivel de acceso</label>
                            <select id="n_level" name="role_level">
                                <option value="300">300 · Usuario básico</option>
                                <option value="499">499 · Visualizador</option>
                                <option value="500">500 · Administrador</option>
                            </select>
                        </div>
                        <button class="btn btn-primary btn-block">Registrar usuario</button>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
