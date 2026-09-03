@extends('layouts.portal')

@section('title', 'Perfil de repartidor | DeUnapp')

@section('content')
    <section class="courier-profile-page">
        <header class="courier-profile-header">
            <div>
                <p class="courier-profile-eyebrow">
                    Configuración
                </p>

                <h1>Perfil de repartidor</h1>

                <p>
                    Actualiza tus datos personales y consulta
                    tu información operativa.
                </p>
            </div>

            <a
                href="{{ route('current-user.settings') }}"
                class="courier-profile-back-link"
            >
                Volver a mi cuenta
            </a>
        </header>

        @if ($errors->has('profile'))
            <div
                class="portal-alert portal-alert-error"
                role="alert"
            >
                {{ $errors->first('profile') }}
            </div>
        @endif

        <section class="courier-profile-summary">
            <article>
                <span>Proveedor</span>

                <strong>
                    {{ $provider->business_name
                        ?? $provider->user?->name
                        ?? 'Proveedor de entrega' }}
                </strong>
            </article>

            <article>
                <span>Tipo de proveedor</span>

                <strong>
                    {{ $provider
                        ->providerType
                        ->type_name }}
                </strong>
            </article>

            <article>
                <span>Estado</span>

                <strong class="courier-profile-status-active">
                    {{ $courier->is_active
                        ? 'Activo'
                        : 'Inactivo' }}
                </strong>
            </article>

            <article>
                <span>Disponibilidad</span>

                <strong
                    class="{{ $courier->is_available
                        ? 'courier-profile-status-active'
                        : 'courier-profile-status-warning' }}"
                >
                    {{ $courier->is_available
                        ? 'Disponible'
                        : 'No disponible' }}
                </strong>
            </article>
        </section>

        <div class="courier-profile-card">
            <form
                method="POST"
                action="{{ route(
                    'current-user.courier-profile.update'
                ) }}"
                class="courier-profile-form"
            >
                @csrf
                @method('PATCH')

                <div class="courier-profile-grid">
                    <div class="courier-profile-field">
                        <label for="name">
                            Nombre completo
                        </label>

                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old(
                                'name',
                                $user->name
                            ) }}"
                            maxlength="255"
                            autocomplete="name"
                            required
                        >

                        @error('name')
                            <span class="courier-profile-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div class="courier-profile-field">
                        <label for="license_number">
                            Número de licencia
                        </label>

                        <input
                            id="license_number"
                            name="license_number"
                            type="text"
                            value="{{ old(
                                'license_number',
                                $courier->license_number
                            ) }}"
                            maxlength="50"
                        >

                        <small>
                            Puedes dejarlo vacío si todavía
                            no tienes una licencia registrada.
                        </small>

                        @error('license_number')
                            <span class="courier-profile-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>
                </div>

                <aside class="courier-profile-information">
                    El proveedor asignado, el estado y la
                    disponibilidad se administran mediante
                    sus respectivos procesos operativos.
                </aside>

                <footer class="courier-profile-actions">
                    <a
                        href="{{ route('dashboard') }}"
                        class="courier-profile-secondary-button"
                    >
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        class="courier-profile-primary-button"
                    >
                        Guardar cambios
                    </button>
                </footer>
            </form>
        </div>
    </section>
@endsection