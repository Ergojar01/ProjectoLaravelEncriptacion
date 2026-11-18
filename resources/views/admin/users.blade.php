@extends('layouts.app')
@section('title', 'Usuarios')

@section('content')
    @php($pendientes = $users->whereNotNull('temp_requested_at'))
    @if($pendientes->isNotEmpty())
        <div class="alert alert-error" role="alert">
            {{ $pendientes->count() }} solicitud(es) de clave temporal pendiente(s):
            <b>{{ $pendientes->pluck('name')->join(', ') }}</b>. Usa «Clave temporal» en su fila.
        </div>
    @endif

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
                    <th>ID</th><th>Usuario</th><th>Nivel / Rol</th><th>Clave</th>
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
                                    @if($u->temp_requested_at)
                                        <div><span class="badge b-warn">Solicitó clave temporal · {{ $u->temp_requested_at->locale('es')->diffForHumans() }}</span></div>
                                    @endif
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
                                    <div class="row-actions">
                                        @if($u->role_level < 500)
                                            <button data-open="modal-temp-{{ $u->id }}" type="button" class="btn {{ $u->temp_requested_at ? 'btn-primary' : 'btn-ghost' }} btn-sm">Clave temporal</button>
                                        @endif
                                        <form method="POST" action="{{ route('admin.users.destroy', $u) }}" data-confirm="¿Eliminar a {{ $u->name }}? Esta acción no se puede deshacer.">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-sm">Eliminar</button>
                                        </form>
                                    </div>
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
                        @include('components.password-rules', ['input' => 'n_pass'])
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
        @foreach($users->where('role_level', '<', 500) as $u)
            <div class="modal {{ $errors->any() && old('form') === 'temp-'.$u->id ? 'open' : '' }}" id="modal-temp-{{ $u->id }}">
                <div class="modal-box">
                    <div class="modal-head">
                        <h3>Clave temporal para {{ $u->name }}</h3>
                        <button class="x" data-close type="button" aria-label="Cerrar">×</button>
                    </div>
                    <div class="modal-body">
                        @if($errors->any() && old('form') === 'temp-'.$u->id)
                            <div class="alert alert-error" role="alert">
                                @foreach($errors->all() as $error) <div>{{ $error }}</div> @endforeach
                            </div>
                        @endif
                        <form method="POST" action="{{ route('admin.users.temp', $u) }}" novalidate>
                            @csrf
                            <input type="hidden" name="form" value="temp-{{ $u->id }}">
                            <div class="field">
                                <label for="temp-current-{{ $u->id }}">Tu contraseña actual de administrador</label>
                                <input id="temp-current-{{ $u->id }}" name="current_password" type="password" autocomplete="current-password" required>
                            </div>
                            <div class="field">
                                <label for="temp-password-{{ $u->id }}">Nueva clave temporal</label>
                                <input id="temp-password-{{ $u->id }}" name="password" type="password" autocomplete="new-password" required>
                            </div>
                            @include('components.password-rules', ['input' => 'temp-password-'.$u->id])
                            <div class="field">
                                <label for="temp-confirm-{{ $u->id }}">Confirmar clave temporal</label>
                                <input id="temp-confirm-{{ $u->id }}" name="password_confirmation" type="password" autocomplete="new-password" required>
                            </div>
                            <p class="muted small">Vence en 24 horas y permite un solo ingreso. Entrégala a la persona por un canal seguro.</p>
                            <button class="btn btn-primary btn-block">Asignar clave temporal</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
