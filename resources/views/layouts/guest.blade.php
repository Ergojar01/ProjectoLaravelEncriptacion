<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · SecureAccess</title>
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}">
</head>
<body class="guest">
<div class="split">
    <section class="hero">
        <div>
            <div class="brand">
                <svg viewBox="0 0 24 24"><path d="M12 2l8 3v6c0 5-3.5 9.5-8 11-4.5-1.5-8-6-8-11V5l8-3z"/><path d="M9 12l2 2 4-4"/></svg>
                <span>Secure<b>Access</b></span>
            </div>
            <h1>Acceso seguro.<br>Datos protegidos.</h1>
            <p>Plataforma de gestión de usuarios con control de acceso por niveles y credenciales protegidas criptográficamente.</p>
            <ul class="feat">
                <li>SHA-512 con sal aleatoria única de 256 bits por usuario</li>
                <li>Pimienta secreta almacenada fuera de la base de datos</li>
                <li>Control de acceso por niveles de permiso: 500 · 499 · 300</li>
            </ul>
        </div>
        <small>© {{ date('Y') }} SecureAccess. Todos los derechos reservados.</small>
    </section>

    <section class="panel">
        <div class="auth-card">@yield('content')</div>
    </section>
</div>
<script src="{{ asset('js/ui.js') }}" defer></script>
</body>
</html>
