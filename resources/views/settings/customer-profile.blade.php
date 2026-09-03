@extends('layouts.portal')

@section('title', 'Perfil de cliente | DeUnapp')

@section('content')
    <section class="customer-profile-page">
        <header class="customer-profile-header">
            <div>
                <p class="customer-profile-eyebrow">
                    Configuración de la cuenta
                </p>

                <h1>Perfil de cliente</h1>

                <p>
                    Actualiza tus datos personales y la
                    información utilizada en tus envíos.
                </p>
            </div>

            <a
                href="{{ route('current-user.settings') }}"
                class="customer-profile-back-link"
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

        <div class="customer-profile-card">
            <form
                method="POST"
                action="{{ route('current-user.profile.update') }}"
                class="customer-profile-form"
            >
                @csrf
                @method('PATCH')

                <div class="customer-profile-grid">
                    <div class="customer-profile-field">
                        <label for="name">
                            Nombre completo
                        </label>

                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name', $user->name) }}"
                            maxlength="255"
                            autocomplete="name"
                            required
                        >

                        @error('name')
                            <span class="customer-profile-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div class="customer-profile-field">
                        <label for="customer_type">
                            Tipo de cliente
                        </label>

                        <select
                            id="customer_type"
                            name="customer_type"
                            required
                        >
                            @foreach ($customerTypes as $customerType)
                                <option
                                    value="{{ $customerType->type_name }}"
                                    @selected(
                                        old(
                                            'customer_type',
                                            $customer
                                                ->customerType
                                                ->type_name
                                        ) === $customerType->type_name
                                    )
                                >
                                    {{ $customerType->type_name === 'BUSINESS'
                                        ? 'Empresa'
                                        : 'Persona individual' }}
                                </option>
                            @endforeach
                        </select>

                        @error('customer_type')
                            <span class="customer-profile-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div class="customer-profile-field">
                        <label for="identity_number">
                            Número de identificación
                        </label>

                        <input
                            id="identity_number"
                            name="identity_number"
                            type="text"
                            value="{{ old(
                                'identity_number',
                                $customer->identity_number
                            ) }}"
                            maxlength="30"
                        >

                        @error('identity_number')
                            <span class="customer-profile-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div class="customer-profile-field">
                        <label for="phone">
                            Teléfono
                        </label>

                        <input
                            id="phone"
                            name="phone"
                            type="tel"
                            value="{{ old(
                                'phone',
                                $customer->phone
                            ) }}"
                            maxlength="30"
                            autocomplete="tel"
                        >

                        @error('phone')
                            <span class="customer-profile-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div
                        class="customer-profile-field
                            customer-profile-field-wide"
                        id="company-name-field"
                    >
                        <label for="company_name">
                            Nombre de la empresa
                        </label>

                        <input
                            id="company_name"
                            name="company_name"
                            type="text"
                            value="{{ old(
                                'company_name',
                                $customer->company_name
                            ) }}"
                            maxlength="150"
                            autocomplete="organization"
                        >

                        <small>
                            Es obligatorio cuando el tipo de
                            cliente es empresa.
                        </small>

                        @error('company_name')
                            <span class="customer-profile-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>
                </div>

                <footer class="customer-profile-actions">
                    <a
                        href="{{ route('dashboard') }}"
                        class="customer-profile-secondary-button"
                    >
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        class="customer-profile-primary-button"
                    >
                        Guardar cambios
                    </button>
                </footer>
            </form>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const customerType =
                document.getElementById('customer_type');

            const companyField =
                document.getElementById('company-name-field');

            const companyInput =
                document.getElementById('company_name');

            const synchronizeCompanyField = () => {
                const isBusiness =
                    customerType.value === 'BUSINESS';

                companyField.hidden = !isBusiness;
                companyInput.required = isBusiness;

                if (!isBusiness) {
                    companyInput.value = '';
                }
            };

            customerType.addEventListener(
                'change',
                synchronizeCompanyField
            );

            synchronizeCompanyField();
        });
    </script>
@endsection