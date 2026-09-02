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

    <title>Recuperar contraseña | DeUnapp</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>
<body class="portal-auth-body">
    <main class="password-page">
        <section class="password-card">
            <a
                href="{{ route('login.page') }}"
                class="portal-auth-brand password-brand"
            >
                <span class="portal-brand-mark">
                    DU
                </span>

                <span>
                    <strong>DeUnapp</strong>
                    <small>Sistema de entregas</small>
                </span>
            </a>

            <header class="password-heading">
                <div class="password-icon">
                    ?
                </div>

                <p class="portal-eyebrow">
                    Recuperación de acceso
                </p>

                <h1>¿Olvidaste tu contraseña?</h1>

                <p>
                    Ingresa el correo de tu cuenta. Si la
                    cuenta está activa, enviaremos un enlace
                    para crear una contraseña nueva.
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
                        Revisa la información ingresada.
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
                action="{{ route('password.email') }}"
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
                        maxlength="255"
                        autocomplete="email"
                        required
                        autofocus
                    >

                    @error('email')
                        <span class="portal-field-error">
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    Enviar enlace
                </button>
            </form>

            <p class="password-footer-link">
                <a href="{{ route('login.page') }}">
                    ← Regresar al inicio de sesión
                </a>
            </p>
        </section>
    </main>
</body>
</html>