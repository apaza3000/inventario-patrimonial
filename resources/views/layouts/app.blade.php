<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Sistema de Inventario')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>

<div class="app-container">

    <aside class="sidebar">

        <div class="sidebar-header">
            <div class="logo-box">

                <img
                    src="{{ asset('images/logo-instituto.png') }}"
                    alt="Logo IESTP Lurín"
                    class="logo-image"
                >

                <span class="logo-text">IESTP LURIN</span>

            </div>
        </div>

        <nav class="sidebar-menu">

            <a href="#" class="menu-item">
                Dashboard
            </a>

            <a href="#" class="menu-item active">
                Inventario
            </a>

            <a href="#" class="menu-item">
                Movimientos
            </a>

            <a href="#" class="menu-item">
                Mantenimiento
            </a>

            <a href="#" class="menu-item">
                Reportes
            </a>

            <a href="#" class="menu-item">
                Configuración
            </a>

        </nav>

    </aside>


    <main class="main-content">

        <header class="topbar">

            <div class="search-container">

                <span class="search-icon">⌕</span>

                <input
                    type="text"
                    class="search-input"
                    placeholder="Buscar por código/serie"
                >

            </div>

            <div class="user-info">
    <span class="notification-icon">🔔</span>
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