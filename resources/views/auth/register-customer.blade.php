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

    <title>Crear cuenta de cliente | DeUnapp</title>

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
                    Cuenta de cliente
                </p>

                <h1>Crear cuenta de cliente</h1>

                <p>
                    Regístrate para solicitar entregas,
                    consultar tus envíos y recibir
                    notificaciones.
                </p>
            </div>

            <p class="registration-alternative">
                ¿Quieres realizar entregas?

                <a href="{{ route('provider.register.page') }}">
                    Registrarme como proveedor
                </a>
            </p>
        </section>

        <section class="registration-form-section">
            <div class="registration-form-card">
                <header class="registration-form-header">
                    <p class="portal-eyebrow">
                        Información personal
                    </p>

                    <h2>Completa tus datos</h2>

                    <p>
                        Los campos marcados como obligatorios
                        deben completarse.
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
                    action="{{ route('register') }}"
                    class="portal-form"
                >
                    @csrf

                    <div class="portal-field">
                        <label for="name">
                            Nombre completo
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
                        <label for="customer_type">
                            Tipo de cliente
                        </label>

                        <select
                            id="customer_type"
                            name="customer_type"
                            class="registration-select"
                            required
                        >
                            <option value="">
                                Selecciona una opción
                            </option>

                            <option
                                value="INDIVIDUAL"
                                @selected(
                                    old('customer_type')
                                    === 'INDIVIDUAL'
                                )
                            >
                                Persona individual
                            </option>

                            <option
                                value="BUSINESS"
                                @selected(
                                    old('customer_type')
                                    === 'BUSINESS'
                                )
                            >
                                Empresa
                            </option>
                        </select>

                        @error('customer_type')
                            <span class="portal-field-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div
                        id="customer-company-field"
                        class="portal-field registration-conditional-field"
                    >
                        <label for="company_name">
                            Nombre de la empresa
                        </label>

                        <input
                            id="company_name"
                            name="company_name"
                            type="text"
                            value="{{ old('company_name') }}"
                            maxlength="150"
                        >

                        @error('company_name')
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
                        Crear cuenta
                    </button>
                </form>

                <p class="registration-login-link">
                    ¿Ya tienes una cuenta?

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
                'customer_type'
            );

            const companyContainer = document.getElementById(
                'customer-company-field'
            );

            const companyInput = document.getElementById(
                'company_name'
            );

            const updateCompanyField = () => {
                const companySelected =
                    typeField.value === 'BUSINESS';

                companyContainer.hidden =
                    ! companySelected;

                companyInput.required =
                    companySelected;

                if (! companySelected) {
                    companyInput.value = '';
                }
            };

            typeField.addEventListener(
                'change',
                updateCompanyField
            );

            updateCompanyField();
        });
    </script>
</body>
</html>