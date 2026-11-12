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

            @if (in_array(auth('web')->user()?->rol?->nombre, ['superadmin', 'administrador', 'director', 'coordinador'], true))
                <a href="{{ route('mantenimientos.index') }}" class="menu-item {{ request()->routeIs('mantenimientos.*') ? 'active' : '' }}" @if (request()->routeIs('mantenimientos.*')) aria-current="page" @endif>Mantenimiento</a>
            @else
                <span class="menu-item menu-item-unavailable">Mantenimiento</span>
            @endif

            @if (in_array(auth('web')->user()?->rol?->nombre, ['superadmin', 'administrador', 'director', 'coordinador', 'asistente'], true))
                <a href="{{ route('reportes.index') }}" class="menu-item {{ request()->routeIs('reportes.*') ? 'active' : '' }}" @if (request()->routeIs('reportes.*')) aria-current="page" @endif>Reportes</a>
            @else
                <span class="menu-item menu-item-unavailable">Reportes</span>
            @endif

            @if (in_array(auth('web')->user()?->rol?->nombre, ['superadmin', 'administrador', 'director'], true))
                <a href="{{ route('configuracion.index') }}" class="menu-item {{ request()->routeIs('configuracion.*') ? 'active' : '' }}" @if (request()->routeIs('configuracion.*')) aria-current="page" @endif>Configuración</a>
            @else
                <span class="menu-item menu-item-unavailable">Configuración</span>
            @endif

        </nav>

    </aside>


    <main class="main-content">

        <header class="topbar">

            <div class="global-search" data-global-search data-search-url="{{ url('/api/busqueda/bienes') }}" data-login-url="{{ route('login') }}">
                <form class="search-container" data-global-search-form role="search" aria-label="Búsqueda global de bienes">

                <input
                    class="search-input"
                    type="search"
                    data-global-search-input
                    maxlength="150"
                    autocomplete="off"
                    placeholder="Buscar por CBI, serie o descripción"
                    aria-label="Buscar bienes por CBI, serie o descripción"
                    aria-controls="global-search-results"
                    aria-expanded="false"
                    aria-describedby="global-search-status"
                    @unless (in_array(auth('web')->user()?->rol?->nombre, ['superadmin', 'director', 'administrador', 'coordinador', 'asistente'], true)) disabled @endunless
                >

                <button class="global-search-submit" type="submit" aria-label="Buscar bienes"><svg class="search-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="10.8" cy="10.8" r="6.3" stroke="currentColor" stroke-width="2"></circle>
                    <path d="m15.5 15.5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                </svg></button>
                </form>
                <section id="global-search-results" class="global-search-results" data-global-search-panel hidden aria-label="Resultados de búsqueda">
                    <p id="global-search-status" class="global-search-status" data-global-search-status role="status" aria-live="polite"></p>
                    <ul class="global-search-list" data-global-search-list></ul>
                    <div class="global-search-pagination" data-global-search-pagination hidden><button type="button" data-global-search-previous>Anterior</button><span data-global-search-page></span><button type="button" data-global-search-next>Siguiente</button></div>
                </section>

            </div>

            <div class="user-info">
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

<div data-global-detail-root>
    @include('components.bien-detail', ['detailPrefix' => 'global-bien-detail', 'detailContext' => 'Consulta de bienes'])
</div>
</body>
</html>
