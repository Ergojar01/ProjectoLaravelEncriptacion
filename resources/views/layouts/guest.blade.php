<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts._head')
    <title>@yield('title') · One Piece Haki Encryption</title>
</head>
<body class="guest">
<main class="panel">
    <div class="auth-card">
        <div class="brand auth-brand">
            <x-op-logo class="logo" />
            <span>One Piece <b>Haki Encryption</b></span>
        </div>
        @yield('content')
    </div>
</main>
<script src="{{ asset('js/ui.js') }}" defer></script>
</body>
</html>
