<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Sistema de Inventario')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="app-body">

<div class="app-container">

    <aside class="sidebar" aria-label="Navegación principal">

        <div class="sidebar-header">
            <div class="logo-box">

                <img
                    src="{{ asset('images/logo-instituto.png') }}"
                    alt="Logo IESTP Lurín"
                    class="logo-image"
                >

                <span class="logo-text">IESTP LURÍN</span>

            </div>
        </div>

        <nav class="sidebar-menu" aria-label="Secciones">

            <a href="{{ route('dashboard') }}" class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                Dashboard
            </a>

            @if (in_array(auth('web')->user()?->rol?->nombre, ['superadmin', 'administrador', 'director', 'asistente'], true))
                <a href="{{ route(auth('web')->user()?->rol?->nombre === 'asistente' ? 'inventario.toma-inventario' : 'inventario.bienes') }}" class="menu-item {{ request()->routeIs('inventario.*') ? 'active' : '' }}" @if (request()->routeIs('inventario.*')) aria-current="page" @endif>
                    Inventario
                </a>
            @else
                <span class="menu-item menu-item-unavailable">Inventario</span>
            @endif

            @if (in_array(auth('web')->user()?->rol?->nombre, ['superadmin', 'administrador', 'director', 'asistente'], true))
                <a href="{{ route('movimientos.index') }}" class="menu-item {{ request()->routeIs('movimientos.*') ? 'active' : '' }}" @if (request()->routeIs('movimientos.*')) aria-current="page" @endif>Movimientos</a>
            @else
                <span class="menu-item menu-item-unavailable">Movimientos</span>
            @endif

            <span class="menu-item menu-item-unavailable">
                Mantenimiento
            </span>

            <span class="menu-item menu-item-unavailable">
                Reportes
            </span>

            <span class="menu-item menu-item-unavailable">
                Configuración
            </span>

        </nav>

    </aside>


    <main class="main-content">

        <header class="topbar">

            <div class="search-container">

                <input
                    type="text"
                    class="search-input"
                    placeholder="Buscar por código/serie"
                    aria-label="Buscar por código o serie"
                    @if (request()->routeIs('inventario.*', 'movimientos.*')) disabled title="Búsqueda disponible próximamente" @endif
                >

                <svg class="search-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="10.8" cy="10.8" r="6.3" stroke="currentColor" stroke-width="2"></circle>
                    <path d="m15.5 15.5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                </svg>

            </div>

            <div class="user-info">
                <svg class="notification-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z" fill="currentColor"></path>
                    <path d="M10 20a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                </svg>
                @php
                    $usuario = auth('web')->user();
                    $nombreUsuario = trim(($usuario?->nombres ?? '') . ' ' . ($usuario?->apellidos ?? ''));
                @endphp
                <div class="user-menu" data-user-menu data-csrf-url="{{ url('/sanctum/csrf-cookie') }}" data-logout-url="{{ url('/api/logout') }}" data-login-url="{{ route('login') }}">
                    <button type="button" class="user-menu-toggle" data-user-menu-toggle aria-expanded="false" aria-controls="user-menu-panel" title="{{ $nombreUsuario ?: 'Usuario' }}">
                        <span class="user-name">{{ $nombreUsuario ?: 'Usuario' }}</span>
                    </button>
                    <div id="user-menu-panel" class="user-menu-panel" data-user-menu-panel hidden>
                        <button type="button" class="user-logout-button" data-user-logout>Cerrar sesión</button>
                        <p class="user-menu-status" data-user-menu-status role="alert" aria-live="polite"></p>
                    </div>
                </div>
            </div>

        </header>


        <section class="content">

            @yield('content')

        </section>

    </main>

</div>

</body>
</html>
