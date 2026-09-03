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