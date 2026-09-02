<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        @yield('title', 'DeUnapp System')
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>
<body class="portal-body">
    <div class="portal-shell">
        <header class="portal-header">
            <a
                href="{{ route('dashboard') }}"
                class="portal-brand"
            >
                <span class="portal-brand-mark">
                    DU
                </span>

                <span>
                    <strong>DeUnapp</strong>
                    <small>Sistema de entregas</small>
                </span>
            </a>

            <nav
                class="portal-navigation"
                aria-label="Navegación principal"
            >
                <a
                    href="{{ route('dashboard') }}"
                    class="portal-navigation-link"
                >
                    Inicio
                </a>

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf

                    <button
                        type="submit"
                        class="portal-logout-button"
                    >
                        Cerrar sesión
                    </button>
                </form>
            </nav>
        </header>

        @if (session('status'))
            <div
                class="portal-alert portal-alert-success"
                role="status"
            >
                {{ session('status') }}
            </div>
        @endif

        <main class="portal-main">
            @yield('content')
        </main>

        <footer class="portal-footer">
            <span>DeUnapp System</span>
            <span>{{ now()->year }}</span>
        </footer>
    </div>
</body>
</html>