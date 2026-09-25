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

            <a href="{{ route('inventario.bienes') }}" class="menu-item {{ request()->routeIs('inventario.bienes') ? 'active' : '' }}" @if (request()->routeIs('inventario.bienes')) aria-current="page" @endif>
                Inventario
            </a>

            <span class="menu-item menu-item-unavailable">
                Movimientos
            </span>

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
                    @if (request()->routeIs('inventario.bienes')) disabled title="Búsqueda disponible próximamente" @endif
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
                <span class="user-name">Admin</span>
            </div>

        </header>


        <section class="content">

            @yield('content')

        </section>

    </main>

</div>

</body>
</html>
