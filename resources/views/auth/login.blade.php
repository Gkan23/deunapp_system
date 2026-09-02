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

    <title>Iniciar sesión | DeUnapp</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>
<body class="portal-auth-body">
    <main class="portal-auth-container">
        <section class="portal-auth-presentation">
            <div class="portal-auth-brand">
                <span class="portal-brand-mark">
                    DU
                </span>

                <span>
                    <strong>DeUnapp</strong>
                    <small>Sistema de entregas</small>
                </span>
            </div>

            <div class="portal-auth-copy">
                <p class="portal-eyebrow">
                    Entregas organizadas
                </p>

                <h1>
                    Controla tus envíos desde un solo lugar.
                </h1>

                <p>
                    Consulta rutas, entregas, incidentes,
                    notificaciones y servicios de soporte
                    mediante una cuenta segura.
                </p>
            </div>

            <div
                class="portal-auth-decoration"
                aria-hidden="true"
            >
                <span></span>
                <span></span>
                <span></span>
            </div>
        </section>

        <section class="portal-auth-form-section">
            <div class="portal-auth-form-card">
                <header class="portal-auth-form-header">
                    <p class="portal-eyebrow">
                        Bienvenido
                    </p>

                    <h2>Iniciar sesión</h2>

                    <p>
                        Ingresa las credenciales de tu cuenta.
                    </p>
                </header>

                @if (session('status'))
                    <div
                        class="portal-alert portal-alert-success"
                        role="status"
                    >
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div
                        class="portal-alert portal-alert-error"
                        role="alert"
                    >
                        <strong>
                            No fue posible iniciar sesión.
                        </strong>

                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>
                                    {{ $error }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ route('login') }}"
                    class="portal-form"
                >
                    @csrf

                    <div class="portal-field">
                        <label for="email">
                            Correo electrónico
                        </label>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            maxlength="255"
                            required
                            autofocus
                        >

                        @error('email')
                            <span class="portal-field-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div class="portal-field">
                        <label for="password">
                            Contraseña
                        </label>

                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                        >

                        @error('password')
                            <span class="portal-field-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <label class="portal-checkbox">
                        <input
                            name="remember"
                            type="checkbox"
                            value="1"
                            @checked(old('remember'))
                        >

                        <span>
                            Mantener la sesión iniciada
                        </span>
                    </label>

                    <button
                        type="submit"
                        class="portal-primary-button"
                    >
                        Iniciar sesión
                    </button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>