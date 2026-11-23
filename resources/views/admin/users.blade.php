@extends('layouts.app')
@section('title', 'Usuarios')

@section('content')
    @if($canManage)
        @php($pendientes = $users->whereNotNull('temp_requested_at')->where('is_active', true))
        @if($pendientes->isNotEmpty())
            <div class="alert alert-error" role="alert">
                {{ $pendientes->count() }} solicitud(es) de restablecimiento pendiente(s):
                <b>{{ $pendientes->pluck('name')->join(', ') }}</b>. Usa «Autorizar restablecimiento» en su fila.
            </div>
        @endif

        @if(session('reset_link'))
            <div class="alert alert-ok" role="status">
                Enlace de un solo uso (se muestra solo ahora, vence en {{ session('reset_ttl') }} min). Entrégaselo a la persona:
                <strong class="mono link-box">{{ session('reset_link') }}</strong>
                Tú no conocerás su contraseña: ella la crea con este enlace, que deja de servir al usarse.
            </div>
        @endif

        @if($errors->any() && old('form') === 'edit')
            <div class="alert alert-error" role="alert">
                @foreach($errors->all() as $error) <div>{{ $error }}</div> @endforeach
            </div>
        @endif
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
        <div class="notice">Modo solo lectura: tu nivel (Visualizador) puede consultar usuarios, niveles y estados, pero no modificarlos.</div>
    @endunless

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>ID</th><th>Usuario</th><th>Nivel / Rol</th><th>Estado</th><th>Cifrado</th>
                    @if($canManage)<th class="right">Acciones</th>@endif
                </tr>
                </thead>
                <tbody>
                @foreach($users as $u)
                    <tr class="{{ $u->is_active ? '' : 'is-off' }}">
                        <td class="mono">#{{ $u->id }}</td>
                        <td>
                            <div class="user-cell">
                                <span class="avatar sm">{{ $u->initials }}</span>
                                <div>
                                    <strong>{{ $u->name }}</strong>
                                    @if($u->id === auth()->id()) <span class="badge b-you">Tú</span> @endif
                                    <div class="muted small">{{ $u->email }}</div>
                                    @if($canManage && $u->temp_requested_at && $u->is_active)
                                        <div><span class="badge b-warn">Solicitó restablecimiento · {{ $u->temp_requested_at->locale('es')->diffForHumans() }}</span></div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($canManage && $u->id !== auth()->id() && $u->role_level < 500)
                                <form method="POST" action="{{ route('admin.users.level', $u) }}" class="inline-form">
                                    @csrf @method('PATCH')
                                    <select name="role_level" aria-label="Nivel de acceso">
                                        @foreach([499 => '499 · Visualizador', 300 => '300 · Usuario básico'] as $v => $label)
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
                            @if($u->is_active)
                                <span class="badge b-ok">Activa</span>
                            @else
                                <span class="badge b-warn">Desactivada</span>
                            @endif
                        </td>
                        <td>
                            @if($u->crypto_version >= $currentVersion)
                                <span class="badge b-ok">Argon2id</span>
                            @else
                                <span class="badge b-warn" title="Pasará a Argon2id en su próximo inicio de sesión">Esquema anterior</span>
                            @endif
                        </td>
                        @if($canManage)
                            <td class="right">
                                <div class="row-actions">
                                    <button class="btn btn-ghost btn-sm" type="button" data-open="modal-edit"
                                            data-action="{{ route('admin.users.update', $u) }}"
                                            data-name="{{ $u->name }}" data-email="{{ $u->email }}">Editar</button>

                                    @if($u->id !== auth()->id())
                                        @if($u->role_level < 500 && $u->is_active)
                                            <form method="POST" action="{{ route('admin.users.reset', $u) }}" data-confirm="¿Autorizar el restablecimiento de {{ $u->name }}? Se cerrarán sus sesiones y se generará un enlace de un solo uso.">
                                                @csrf
                                                <button class="btn {{ $u->temp_requested_at ? 'btn-primary' : 'btn-ghost' }} btn-sm">Autorizar restablecimiento</button>
                                            </form>
                                        @endif

                                        <a class="btn btn-ghost btn-sm" href="{{ route('admin.users.hash.form', $u) }}">Ver hash</a>

                                        <form method="POST" action="{{ route('admin.users.status', $u) }}" data-confirm="{{ $u->is_active ? '¿Desactivar a '.$u->name.'? Perderá el acceso de inmediato; su historial se conserva.' : '¿Reactivar a '.$u->name.'?' }}">
                                            @csrf @method('PATCH')
                                            <button class="btn {{ $u->is_active ? 'btn-danger' : 'btn-primary' }} btn-sm">{{ $u->is_active ? 'Desactivar' : 'Reactivar' }}</button>
                                        </form>
                                    @else
                                        <span class="muted small">Cuenta actual</span>
                                    @endif
                                </div>
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
                            <label for="n_email">Correo electrónico (será su usuario)</label>
                            <input id="n_email" name="email" type="email" value="{{ old('email') }}" required>
                        </div>
                        <div class="field">
                            <label for="n_pass">Contraseña inicial</label>
                            <div class="input-wrap">
                                <input id="n_pass" name="password" type="password" autocomplete="new-password" required>
                                <button type="button" class="toggle" data-toggle-pass="n_pass">Mostrar</button>
                            </div>
                        </div>
                        <ul class="rules" data-pass-rules="n_pass">
                            <li data-rule="len">Mínimo 12 caracteres</li>
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
                            </select>
                        </div>
                        <button class="btn btn-primary btn-block">Registrar usuario</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal" id="modal-edit">
            <div class="modal-box">
                <div class="modal-head">
                    <h3>Modificar datos del usuario</h3>
                    <button class="x" data-close type="button" aria-label="Cerrar">×</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="#" novalidate>
                        @csrf @method('PATCH')
                        <input type="hidden" name="form" value="edit">
                        <div class="field">
                            <label for="e_name">Nombre completo</label>
                            <input id="e_name" name="name" type="text" required>
                        </div>
                        <div class="field">
                            <label for="e_email">Correo electrónico</label>
                            <input id="e_email" name="email" type="email" required>
                        </div>
                        <p class="muted small">La contraseña no se puede ver ni definir desde aquí: para restablecerla usa «Autorizar restablecimiento».</p>
                        <button class="btn btn-primary btn-block">Guardar cambios</button>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
