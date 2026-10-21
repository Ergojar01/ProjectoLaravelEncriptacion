<div class="grid-4">
    <div class="stat"><span class="stat-label">Usuarios totales</span><strong>{{ $stats['total'] }}</strong></div>
    <div class="stat s2"><span class="stat-label">Administradores</span><strong>{{ $stats['admins'] }}</strong></div>
    <div class="stat s3"><span class="stat-label">Visualizadores</span><strong>{{ $stats['viewers'] }}</strong></div>
    <div class="stat s4"><span class="stat-label">Usuarios básicos</span><strong>{{ $stats['basic'] }}</strong></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head">
            <h3>Registros recientes</h3>
            <a href="{{ route('admin.users.index') }}">Ver todos →</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Usuario</th><th>Nivel</th><th>Alta</th></tr></thead>
                <tbody>
                @foreach($recent as $u)
                    <tr>
                        <td>
                            <div class="user-cell">
                                <span class="avatar sm">{{ $u->initials }}</span>
                                <div><strong>{{ $u->name }}</strong><div class="muted small">{{ $u->email }}</div></div>
                            </div>
                        </td>
                        <td><span class="badge {{ $u->role_class }}">{{ $u->role_level }} · {{ $u->role_name }}</span></td>
                        <td class="muted">{{ $u->created_at?->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Protección criptográfica</h3></div>
        <ul class="kv">
            <li><span>Hash</span><b>SHA-512 (PBKDF2 + HMAC)</b></li>
            <li><span>Sal</span><b>256 bits por usuario</b></li>
            <li><span>Iteraciones</span><b>{{ number_format(config('crypto.iterations')) }}</b></li>
            <li><span>Pimienta</span>
                <b>@if(strlen((string) config('crypto.pepper')) >= 64)<span class="badge b-ok">Configurada</span>@else<span class="badge b-warn">Falta</span>@endif</b>
            </li>
            <li><span>Combinación</span><b>XOR + sello HMAC</b></li>
            <li><span>Versión de clave</span><b>v{{ config('crypto.version') }}</b></li>
        </ul>
    </div>
</div>
