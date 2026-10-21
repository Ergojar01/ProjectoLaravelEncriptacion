<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · SecureAccess</title>
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}">
</head>
<body class="app">
<aside class="sidebar">
    <div class="brand">
        <svg viewBox="0 0 24 24"><path d="M12 2l8 3v6c0 5-3.5 9.5-8 11-4.5-1.5-8-6-8-11V5l8-3z"/><path d="M9 12l2 2 4-4"/></svg>
        <span>Secure<b>Access</b></span>
    </div>

    <nav class="nav">
        <span class="nav-label">General</span>
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24"><path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/></svg> Inicio
        </a>

        @if(auth()->user()->role_level >= 499)
            <span class="nav-label">Administración</span>
            <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> Usuarios
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
        <button class="menu-btn" data-menu type="button" aria-label="Menú">☰</button>
        <div class="crumb">Panel / <b>@yield('title')</b></div>
        <div class="top-right">
            <span class="badge {{ auth()->user()->role_class }}">Nivel {{ auth()->user()->role_level }} · {{ auth()->user()->role_name }}</span>
        </div>
    </header>

    <main class="content">
        @if(session('status')) <div class="alert alert-ok">{{ session('status') }}</div> @endif
        @if(session('error'))  <div class="alert alert-error">{{ session('error') }}</div> @endif
        @yield('content')
    </main>
</div>
<script src="{{ asset('js/ui.js') }}" defer></script>
</body>
</html>
