<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts._head')
    <title>@yield('title', 'Panel') · One Piece Haki Encryption</title>
</head>
<body class="app">
<aside class="sidebar">
    <div class="brand">
        <x-op-logo class="logo" />
        <span>One Piece <b>Haki Encryption</b></span>
    </div>

    <nav class="nav" aria-label="Principal">
        <span class="nav-label">Cubierta</span>
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24"><path d="M3 11l9-8 9 8v10a1 1 0 01-1 1h-5v-7H9v7H4a1 1 0 01-1-1z"/></svg> Inicio
        </a>
        <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z"/></svg> Mi perfil
        </a>

        @if(auth()->user()->role_level >= 500)
            <span class="nav-label">Capitanía</span>
            <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> Tripulación
            </a>
        @endif
    </nav>

    <div class="sidebar-foot">
        <span class="avatar">{{ auth()->user()->initials }}</span>
        <div class="who">
            <strong>{{ auth()->user()->name }}</strong>
            <span>{{ auth()->user()->role_name }}</span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="logout" title="Cerrar sesión" aria-label="Cerrar sesión">
                <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            </button>
        </form>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <button class="menu-btn" data-menu type="button" aria-label="Abrir menú">☰</button>
        <div class="crumb">@yield('title')</div>
        <div class="top-right">
            <span class="badge {{ auth()->user()->role_class }}">Nivel {{ auth()->user()->role_level }}</span>
        </div>
    </header>

    <main class="content">
        @if(session('status')) <div class="alert alert-ok" role="status">{{ session('status') }}</div> @endif
        @if(session('error'))  <div class="alert alert-error" role="alert">{{ session('error') }}</div> @endif
        @yield('content')
    </main>
</div>
<script src="{{ asset('js/ui.js') }}" defer></script>
</body>
</html>
