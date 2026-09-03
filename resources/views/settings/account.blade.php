@extends('layouts.portal')

@section('title', 'Configuración de cuenta | DeUnapp')

@section('content')
    <section class="account-settings-heading">
        <div>
            <p class="portal-eyebrow">
                Configuración
            </p>

            <h1>Mi cuenta</h1>

            <p>
                Consulta tus datos y administra las
                credenciales de acceso.
            </p>
        </div>

        <div class="account-settings-user">
            <span class="portal-user-avatar">
                {{ mb_strtoupper(
                    mb_substr($user->name, 0, 1)
                ) }}
            </span>

            <div>
                <strong>
                    {{ $user->name }}
                </strong>

                <span>
                    {{ $roleLabel }}
                </span>

                <small>
                    {{ $user->email }}
                </small>
            </div>
        </div>
    </section>

    <section class="account-settings-summary">
        <article>
            <span>Nombre</span>

            <strong>
                {{ $user->name }}
            </strong>
        </article>

        <article>
            <span>Rol</span>

            <strong>
                {{ $roleLabel }}
            </strong>
        </article>

        <article>
            <span>Estado</span>

            <strong>
                {{ $user->accountStatus?->status_name }}
            </strong>
        </article>

        <article>
            <span>Correo verificado</span>

            <strong
                class="{{ $user->hasVerifiedEmail()
                    ? 'account-status-positive'
                    : 'account-status-warning' }}"
            >
                {{ $user->hasVerifiedEmail()
                    ? 'Sí'
                    : 'Pendiente' }}
            </strong>
        </article>
    </section>

    @if (! $user->hasVerifiedEmail())
        <div
            class="portal-alert portal-alert-error"
            role="alert"
        >
            Tu correo todavía no está verificado.
            Revisa tu bandeja de entrada antes de
            utilizar las funciones operativas.
        </div>
    @endif

    @php
        $profileRouteName = match (
            $user->role?->role_name
        ) {
            'CUSTOMER' =>
                'current-user.profile.edit',
            'DELIVERY_PROVIDER' =>
                'current-user.provider-profile.edit',
            'COURIER' =>
                'current-user.courier-profile.edit',
            default => null,
        };

        $profileTitle = match (
            $user->role?->role_name
        ) {
            'CUSTOMER' =>
                'Perfil de cliente',
            'DELIVERY_PROVIDER' =>
                'Perfil de proveedor',
            'COURIER' =>
                'Perfil de repartidor',
            default =>
                'Perfil operativo',
        };

        $profileDescription = match (
            $user->role?->role_name
        ) {
            'CUSTOMER' =>
                'Actualiza tus datos personales y la información utilizada en tus envíos.',
            'DELIVERY_PROVIDER' =>
                'Actualiza tus datos y la información de tu servicio de entregas.',
            'COURIER' =>
                'Actualiza tu nombre y número de licencia de repartidor.',
            default =>
                'Consulta y actualiza la información de tu perfil.',
        };
    @endphp

    @if ($profileRouteName !== null)
        <section class="account-settings-profile">
            <article class="account-settings-card">
                <header>
                    <div class="account-settings-icon">
                        ID
                    </div>

                    <div>
                        <p class="portal-eyebrow">
                            Información personal
                        </p>

                        <h2>
                            {{ $profileTitle }}
                        </h2>
                    </div>
                </header>

                <p class="account-settings-description">
                    {{ $profileDescription }}
                </p>

                <a
                    href="{{ route($profileRouteName) }}"
                    class="portal-primary-button"
                >
                    Editar perfil
                </a>
            </article>
        </section>
    @endif

    <section class="account-settings-grid">
        <article class="account-settings-card">
            <header>
                <div class="account-settings-icon">
                    @
                </div>

                <div>
                    <p class="portal-eyebrow">
                        Correo electrónico
                    </p>

                    <h2>Cambiar correo</h2>
                </div>
            </header>

            <p class="account-settings-description">
                Cuando cambies el correo tendrás que
                verificar nuevamente la dirección.
            </p>

            <form
                method="POST"
                action="{{ route(
                    'current-user.email.update'
                ) }}"
                class="portal-form"
            >
                @csrf
                @method('PUT')

                <div class="portal-field">
                    <label for="email">
                        Nuevo correo electrónico
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

                <div class="portal-field">
                    <label for="email_current_password">
                        Contraseña actual
                    </label>

                    <input
                        id="email_current_password"
                        name="current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                    >

                    @error('current_password')
                        <span class="portal-field-error">
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    Actualizar correo
                </button>
            </form>
        </article>

        <article class="account-settings-card">
            <header>
                <div class="account-settings-icon">
                    *
                </div>

                <div>
                    <p class="portal-eyebrow">
                        Seguridad
                    </p>

                    <h2>Cambiar contraseña</h2>
                </div>
            </header>

            <p class="account-settings-description">
                Utiliza una contraseña nueva de al
                menos ocho caracteres.
            </p>

            <form
                method="POST"
                action="{{ route(
                    'current-user.password.update'
                ) }}"
                class="portal-form"
            >
                @csrf
                @method('PUT')

                <div class="portal-field">
                    <label for="password_current_password">
                        Contraseña actual
                    </label>

                    <input
                        id="password_current_password"
                        name="current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                    >

                    @error('current_password')
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

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    Actualizar contraseña
                </button>
            </form>
        </article>
    </section>
@endsection