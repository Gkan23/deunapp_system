@extends('layouts.portal')

@section('title', 'Perfil de proveedor | DeUnapp')

@section('content')
    <section class="provider-profile-page">
        <header class="provider-profile-header">
            <div>
                <p class="provider-profile-eyebrow">
                    Configuración
                </p>

                <h1>Perfil de proveedor</h1>

                <p>
                    Actualiza los datos personales y la
                    información de tu servicio de entregas.
                </p>
            </div>

            <a
                href="{{ route('current-user.settings') }}"
                class="provider-profile-back-link"
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

        <div class="provider-profile-card">
            <form
                method="POST"
                action="{{ route(
                    'current-user.provider-profile.update'
                ) }}"
                class="provider-profile-form"
            >
                @csrf
                @method('PATCH')

                <div class="provider-profile-grid">
                    <div class="provider-profile-field">
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
                            <span class="provider-profile-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div class="provider-profile-field">
                        <label for="provider_type">
                            Tipo de proveedor
                        </label>

                        <select
                            id="provider_type"
                            name="provider_type"
                            required
                        >
                            @foreach (
                                $providerTypes as $providerType
                            )
                                <option
                                    value="{{ $providerType->type_name }}"
                                    @selected(
                                        old(
                                            'provider_type',
                                            $provider
                                                ->providerType
                                                ->type_name
                                        )
                                        === $providerType->type_name
                                    )
                                >
                                    {{ $providerType->type_name
                                        === 'COMPANY'
                                            ? 'Empresa'
                                            : 'Independiente' }}
                                </option>
                            @endforeach
                        </select>

                        @error('provider_type')
                            <span class="provider-profile-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div class="provider-profile-field">
                        <label for="identity_number">
                            Número de identificación
                        </label>

                        <input
                            id="identity_number"
                            name="identity_number"
                            type="text"
                            value="{{ old(
                                'identity_number',
                                $provider->identity_number
                            ) }}"
                            maxlength="30"
                        >

                        @error('identity_number')
                            <span class="provider-profile-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div class="provider-profile-field">
                        <label for="phone">
                            Teléfono
                        </label>

                        <input
                            id="phone"
                            name="phone"
                            type="tel"
                            value="{{ old(
                                'phone',
                                $provider->phone
                            ) }}"
                            maxlength="30"
                            autocomplete="tel"
                        >

                        @error('phone')
                            <span class="provider-profile-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div
                        id="business-name-field"
                        class="provider-profile-field
                            provider-profile-field-wide"
                    >
                        <label for="business_name">
                            Nombre de la empresa
                        </label>

                        <input
                            id="business_name"
                            name="business_name"
                            type="text"
                            value="{{ old(
                                'business_name',
                                $provider->business_name
                            ) }}"
                            maxlength="150"
                            autocomplete="organization"
                        >

                        <small>
                            Es obligatorio para los
                            proveedores de tipo empresa.
                        </small>

                        @error('business_name')
                            <span class="provider-profile-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>
                </div>

                <footer class="provider-profile-actions">
                    <a
                        href="{{ route('dashboard') }}"
                        class="provider-profile-secondary-button"
                    >
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        class="provider-profile-primary-button"
                    >
                        Guardar cambios
                    </button>
                </footer>
            </form>
        </div>
    </section>

    <script>
        document.addEventListener(
            'DOMContentLoaded',
            () => {
                const providerType =
                    document.getElementById(
                        'provider_type'
                    );

                const businessField =
                    document.getElementById(
                        'business-name-field'
                    );

                const businessInput =
                    document.getElementById(
                        'business_name'
                    );

                if (
                    providerType === null
                    || businessField === null
                    || businessInput === null
                ) {
                    return;
                }

                const synchronizeBusinessField =
                    () => {
                        const isCompany =
                            providerType.value
                                === 'COMPANY';

                        businessField.hidden =
                            !isCompany;

                        businessInput.required =
                            isCompany;

                        if (!isCompany) {
                            businessInput.value = '';
                        }
                    };

                providerType.addEventListener(
                    'change',
                    synchronizeBusinessField
                );

                synchronizeBusinessField();
            }
        );
    </script>
@endsection