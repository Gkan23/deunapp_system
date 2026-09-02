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

    <title>Nueva contraseña | DeUnapp</title>

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
                    *
                </div>

                <p class="portal-eyebrow">
                    Seguridad de la cuenta
                </p>

                <h1>Crear nueva contraseña</h1>

                <p>
                    La contraseña debe contener al menos
                    ocho caracteres.
                </p>
            </header>

            @if ($errors->any())
                <div
                    class="portal-alert portal-alert-error"
                    role="alert"
                >
                    <strong>
                        No fue posible cambiar la contraseña.
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
                action="{{ route('password.store') }}"
                class="portal-form"
            >
                @csrf

                <input
                    name="token"
                    type="hidden"
                    value="{{ $token }}"
                >

                <div class="portal-field">
                    <label for="email">
                        Correo electrónico
                    </label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email', $email) }}"
                        maxlength="255"
                        autocomplete="email"
                        required
                    >

                    @error('email')
                        <span class="portal-field-error">
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <div class="portal-field">
                    <label for="password">
                        Nueva contraseña
                    </label>

                    <input
                        id="password"
                        name="password"
                        type="password"
                        minlength="8"
                        maxlength="255"
                        autocomplete="new-password"
                        required
                        autofocus
                    >

                    @error('password')
                        <span class="portal-field-error">
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <div class="portal-field">
                    <label for="password_confirmation">
                        Confirmar nueva contraseña
                    </label>

                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        minlength="8"
                        maxlength="255"
                        autocomplete="new-password"
                        required
                    >
                </div>

                @error('token')
                    <div class="portal-alert portal-alert-error">
                        {{ $message }}
                    </div>
                @enderror

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    Guardar nueva contraseña
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