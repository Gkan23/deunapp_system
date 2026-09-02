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

    <title>Registro de proveedor | DeUnapp</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>
<body class="portal-auth-body">
    <main class="registration-container">
        <section class="registration-heading">
            <a
                href="{{ route('login.page') }}"
                class="portal-auth-brand"
            >
                <span class="portal-brand-mark">
                    DU
                </span>

                <span>
                    <strong>DeUnapp</strong>
                    <small>Sistema de entregas</small>
                </span>
            </a>

            <div>
                <p class="portal-eyebrow">
                    Proveedores de entrega
                </p>

                <h1>Registro de proveedor</h1>

                <p>
                    Envía tu solicitud para administrar
                    repartidores, vehículos, rutas y viajes
                    desde DeUnapp.
                </p>
            </div>

            <div class="registration-information">
                <strong>
                    Aprobación requerida
                </strong>

                <p>
                    La cuenta deberá verificar su correo y
                    ser aprobada antes de iniciar sesión.
                </p>
            </div>

            <p class="registration-alternative">
                ¿Necesitas solicitar una entrega?

                <a href="{{ route('register.page') }}">
                    Registrarme como cliente
                </a>
            </p>
        </section>

        <section class="registration-form-section">
            <div class="registration-form-card">
                <header class="registration-form-header">
                    <p class="portal-eyebrow">
                        Solicitud de proveedor
                    </p>

                    <h2>Completa tus datos</h2>

                    <p>
                        Utiliza información verdadera para
                        facilitar la revisión de tu cuenta.
                    </p>
                </header>

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
                    action="{{ route('provider.register') }}"
                    class="portal-form"
                >
                    @csrf

                    <div class="portal-field">
                        <label for="name">
                            Nombre del responsable
                        </label>

                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            maxlength="255"
                            autocomplete="name"
                            required
                            autofocus
                        >

                        @error('name')
                            <span class="portal-field-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

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
                        >

                        @error('email')
                            <span class="portal-field-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div class="registration-form-grid">
                        <div class="portal-field">
                            <label for="password">
                                Contraseña
                            </label>

                            <input
                                id="password"
                                name="password"
                                type="password"
                                minlength="8"
                                maxlength="255"
                                autocomplete="new-password"
                                required
                            >

                            @error('password')
                                <span class="portal-field-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="portal-field">
                            <label for="password_confirmation">
                                Confirmar contraseña
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
                    </div>

                    <div class="portal-field">
                        <label for="provider_type">
                            Tipo de proveedor
                        </label>

                        <select
                            id="provider_type"
                            name="provider_type"
                            class="registration-select"
                            required
                        >
                            <option value="">
                                Selecciona una opción
                            </option>

                            <option
                                value="INDEPENDENT"
                                @selected(
                                    old('provider_type')
                                    === 'INDEPENDENT'
                                )
                            >
                                Proveedor independiente
                            </option>

                            <option
                                value="COMPANY"
                                @selected(
                                    old('provider_type')
                                    === 'COMPANY'
                                )
                            >
                                Empresa
                            </option>
                        </select>

                        @error('provider_type')
                            <span class="portal-field-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div
                        id="provider-business-field"
                        class="portal-field registration-conditional-field"
                    >
                        <label for="business_name">
                            Nombre comercial
                        </label>

                        <input
                            id="business_name"
                            name="business_name"
                            type="text"
                            value="{{ old('business_name') }}"
                            maxlength="150"
                        >

                        @error('business_name')
                            <span class="portal-field-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div class="registration-form-grid">
                        <div class="portal-field">
                            <label for="identity_number">
                                Número de identificación
                            </label>

                            <input
                                id="identity_number"
                                name="identity_number"
                                type="text"
                                value="{{ old('identity_number') }}"
                                maxlength="30"
                                required
                            >

                            @error('identity_number')
                                <span class="portal-field-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="portal-field">
                            <label for="phone">
                                Teléfono
                            </label>

                            <input
                                id="phone"
                                name="phone"
                                type="text"
                                value="{{ old('phone') }}"
                                maxlength="30"
                                autocomplete="tel"
                                required
                            >

                            @error('phone')
                                <span class="portal-field-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>
                    </div>

                    @error('registration')
                        <div class="portal-alert portal-alert-error">
                            {{ $message }}
                        </div>
                    @enderror

                    <button
                        type="submit"
                        class="portal-primary-button"
                    >
                        Enviar solicitud
                    </button>
                </form>

                <p class="registration-login-link">
                    ¿Ya tienes una cuenta aprobada?

                    <a href="{{ route('login.page') }}">
                        Iniciar sesión
                    </a>
                </p>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const typeField = document.getElementById(
                'provider_type'
            );

            const businessContainer =
                document.getElementById(
                    'provider-business-field'
                );

            const businessInput = document.getElementById(
                'business_name'
            );

            const updateBusinessField = () => {
                const companySelected =
                    typeField.value === 'COMPANY';

                businessContainer.hidden =
                    ! companySelected;

                businessInput.required =
                    companySelected;

                if (! companySelected) {
                    businessInput.value = '';
                }
            };

            typeField.addEventListener(
                'change',
                updateBusinessField
            );

            updateBusinessField();
        });
    </script>
</body>
</html>