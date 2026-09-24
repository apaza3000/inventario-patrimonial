<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | Sistema de Inventario</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page" style="--login-background: url('{{ asset('images/login-aula.png') }}')">
    <div class="login-shell">
        <header class="login-brand">
            <img src="{{ asset('images/logo-instituto.png') }}" alt="Logo institucional" class="login-brand-logo">
        </header>

        <main class="login-card">
            <h1>Iniciar Sesión</h1>

            <form data-login-form data-dashboard-url="{{ route('dashboard') }}" novalidate>
                <div class="login-field">
                    <label for="correo">Correo institucional</label>
                    <div class="login-input-wrap">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm0 2c-4.7 0-8.5 2.4-8.5 5.4V21h17v-1.6c0-3-3.8-5.4-8.5-5.4Z"/>
                        </svg>
                        <input id="correo" name="correo" type="email" autocomplete="username" required autofocus>
                    </div>
                </div>

                <div class="login-field">
                    <label for="password">Contraseña</label>
                    <div class="login-input-wrap">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17 9V7a5 5 0 0 0-10 0v2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2h-2ZM9 7a3 3 0 0 1 6 0v2H9V7Z"/>
                        </svg>
                        <input id="password" name="password" type="password" autocomplete="current-password" required>
                        <button type="button" class="login-password-toggle" data-toggle-password aria-label="Mostrar contraseña" aria-pressed="false">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none">
                                <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z" stroke="currentColor" stroke-width="1.8"/>
                                <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.8"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <p class="login-status" data-login-status role="alert" aria-live="polite"></p>

                <button type="submit" class="login-submit">Iniciar Sesión</button>
            </form>
        </main>
    </div>
</body>
</html>
