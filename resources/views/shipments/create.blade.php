@extends('layouts.portal')

@section('title', 'Registrar envío | DeUnapp')

@section('content')
    <section class="shipment-create-page">
        <header class="shipment-create-header">
            <div>
                <p class="shipment-create-eyebrow">
                    Nuevo envío
                </p>

                <h1>Registrar envío</h1>

                <p>
                    Ingresa los datos del remitente,
                    destinatario, direcciones y paquete.
                </p>
            </div>

            <a
                href="{{ route(
                    'portal.shipments.index'
                ) }}"
                class="shipment-create-back-link"
            >
                Volver a envíos
            </a>
        </header>

        @if ($errors->any())
            <div
                class="shipment-create-alert"
                role="alert"
            >
                <strong>
                    No fue posible registrar el envío.
                </strong>

                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route(
                'portal.shipments.store'
            ) }}"
            class="shipment-create-form"
        >
            @csrf

            <div class="shipment-create-two-columns">
                <section class="shipment-create-card">
                    <header>
                        <p class="shipment-create-eyebrow">
                            Participante
                        </p>

                        <h2>Remitente</h2>
                    </header>

                    <div class="shipment-create-field-grid">
                        <div class="shipment-create-field">
                            <label for="sender_first_name">
                                Nombres
                            </label>

                            <input
                                id="sender_first_name"
                                name="sender[first_name]"
                                type="text"
                                value="{{ old(
                                    'sender.first_name'
                                ) }}"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div class="shipment-create-field">
                            <label for="sender_last_name">
                                Apellidos
                            </label>

                            <input
                                id="sender_last_name"
                                name="sender[last_name]"
                                type="text"
                                value="{{ old(
                                    'sender.last_name'
                                ) }}"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div class="shipment-create-field">
                            <label for="sender_phone">
                                Teléfono
                            </label>

                            <input
                                id="sender_phone"
                                name="sender[phone]"
                                type="text"
                                value="{{ old(
                                    'sender.phone'
                                ) }}"
                                maxlength="30"
                                required
                            >
                        </div>

                        <div class="shipment-create-field">
                            <label for="sender_email">
                                Correo electrónico
                            </label>

                            <input
                                id="sender_email"
                                name="sender[email]"
                                type="email"
                                value="{{ old(
                                    'sender.email'
                                ) }}"
                                maxlength="150"
                            >
                        </div>

                        <div
                            class="
                                shipment-create-field
                                shipment-create-field-full
                            "
                        >
                            <label for="sender_identity">
                                Identificación
                            </label>

                            <input
                                id="sender_identity"
                                name="sender[identity_number]"
                                type="text"
                                value="{{ old(
                                    'sender.identity_number'
                                ) }}"
                                maxlength="30"
                            >
                        </div>
                    </div>
                </section>

                <section class="shipment-create-card">
                    <header>
                        <p class="shipment-create-eyebrow">
                            Participante
                        </p>

                        <h2>Destinatario</h2>
                    </header>

                    <div class="shipment-create-field-grid">
                        <div class="shipment-create-field">
                            <label for="recipient_first_name">
                                Nombres
                            </label>

                            <input
                                id="recipient_first_name"
                                name="recipient[first_name]"
                                type="text"
                                value="{{ old(
                                    'recipient.first_name'
                                ) }}"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div class="shipment-create-field">
                            <label for="recipient_last_name">
                                Apellidos
                            </label>

                            <input
                                id="recipient_last_name"
                                name="recipient[last_name]"
                                type="text"
                                value="{{ old(
                                    'recipient.last_name'
                                ) }}"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div class="shipment-create-field">
                            <label for="recipient_phone">
                                Teléfono
                            </label>

                            <input
                                id="recipient_phone"
                                name="recipient[phone]"
                                type="text"
                                value="{{ old(
                                    'recipient.phone'
                                ) }}"
                                maxlength="30"
                                required
                            >
                        </div>

                        <div class="shipment-create-field">
                            <label for="recipient_email">
                                Correo electrónico
                            </label>

                            <input
                                id="recipient_email"
                                name="recipient[email]"
                                type="email"
                                value="{{ old(
                                    'recipient.email'
                                ) }}"
                                maxlength="150"
                            >
                        </div>

                        <div
                            class="
                                shipment-create-field
                                shipment-create-field-full
                            "
                        >
                            <label for="recipient_identity">
                                Identificación
                            </label>

                            <input
                                id="recipient_identity"
                                name="recipient[identity_number]"
                                type="text"
                                value="{{ old(
                                    'recipient.identity_number'
                                ) }}"
                                maxlength="30"
                            >
                        </div>
                    </div>
                </section>
            </div>

            <div class="shipment-create-two-columns">
                <section class="shipment-create-card">
                    <header>
                        <p class="shipment-create-eyebrow">
                            Punto de salida
                        </p>

                        <h2>Dirección de origen</h2>
                    </header>

                    <div class="shipment-create-field-grid">
                        <div
                            class="
                                shipment-create-field
                                shipment-create-field-full
                            "
                        >
                            <label for="origin_municipality">
                                Municipio
                            </label>

                            <select
                                id="origin_municipality"
                                name="
                                    origin_address[municipality_id]
                                "
                                required
                            >
                                <option value="">
                                    Selecciona un municipio
                                </option>

                                @foreach (
                                    $municipalities
                                    as $municipality
                                )
                                    <option
                                        value="{{ $municipality->id }}"
                                        @selected(
                                            (string) old(
                                                'origin_address.municipality_id'
                                            )
                                            === (string) $municipality->id
                                        )
                                    >
                                        {{ $municipality
                                            ->municipality_name }}

                                        —
                                        {{ $municipality
                                            ->department_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div
                            class="
                                shipment-create-field
                                shipment-create-field-full
                            "
                        >
                            <label for="origin_address_line">
                                Dirección
                            </label>

                            <input
                                id="origin_address_line"
                                name="
                                    origin_address[address_line]
                                "
                                type="text"
                                value="{{ old(
                                    'origin_address.address_line'
                                ) }}"
                                maxlength="255"
                                required
                            >
                        </div>

                        <div
                            class="
                                shipment-create-field
                                shipment-create-field-full
                            "
                        >
                            <label for="origin_reference">
                                Referencia
                            </label>

                            <textarea
                                id="origin_reference"
                                name="
                                    origin_address[reference_note]
                                "
                                rows="3"
                                maxlength="500"
                            >{{ old(
                                'origin_address.reference_note'
                            ) }}</textarea>
                        </div>
                    </div>
                </section>

                <section class="shipment-create-card">
                    <header>
                        <p class="shipment-create-eyebrow">
                            Punto de entrega
                        </p>

                        <h2>Dirección de destino</h2>
                    </header>

                    <div class="shipment-create-field-grid">
                        <div
                            class="
                                shipment-create-field
                                shipment-create-field-full
                            "
                        >
                            <label for="destination_municipality">
                                Municipio
                            </label>

                            <select
                                id="destination_municipality"
                                name="
                                    destination_address[municipality_id]
                                "
                                required
                            >
                                <option value="">
                                    Selecciona un municipio
                                </option>

                                @foreach (
                                    $municipalities
                                    as $municipality
                                )
                                    <option
                                        value="{{ $municipality->id }}"
                                        @selected(
                                            (string) old(
                                                'destination_address.municipality_id'
                                            )
                                            === (string) $municipality->id
                                        )
                                    >
                                        {{ $municipality
                                            ->municipality_name }}

                                        —
                                        {{ $municipality
                                            ->department_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div
                            class="
                                shipment-create-field
                                shipment-create-field-full
                            "
                        >
                            <label for="destination_address_line">
                                Dirección
                            </label>

                            <input
                                id="destination_address_line"
                                name="
                                    destination_address[address_line]
                                "
                                type="text"
                                value="{{ old(
                                    'destination_address.address_line'
                                ) }}"
                                maxlength="255"
                                required
                            >
                        </div>

                        <div
                            class="
                                shipment-create-field
                                shipment-create-field-full
                            "
                        >
                            <label for="destination_reference">
                                Referencia
                            </label>

                            <textarea
                                id="destination_reference"
                                name="
                                    destination_address[reference_note]
                                "
                                rows="3"
                                maxlength="500"
                            >{{ old(
                                'destination_address.reference_note'
                            ) }}</textarea>
                        </div>
                    </div>
                </section>
            </div>

            <section class="shipment-create-card">
                <header>
                    <p class="shipment-create-eyebrow">
                        Contenido
                    </p>

                    <h2>Paquete</h2>

                    <p>
                        Registra las características del
                        paquete que será transportado.
                    </p>
                </header>

                <div class="shipment-create-field-grid">
                    <div
                        class="
                            shipment-create-field
                            shipment-create-field-full
                        "
                    >
                        <label for="package_description">
                            Descripción del contenido
                        </label>

                        <textarea
                            id="package_description"
                            name="
                                packages[0][content_description]
                            "
                            rows="3"
                            maxlength="1000"
                            required
                        >{{ old(
                            'packages.0.content_description'
                        ) }}</textarea>
                    </div>

                    <div class="shipment-create-field">
                        <label for="package_weight">
                            Peso (kg)
                        </label>

                        <input
                            id="package_weight"
                            name="packages[0][weight]"
                            type="number"
                            value="{{ old(
                                'packages.0.weight'
                            ) }}"
                            min="0.01"
                            step="0.01"
                        >
                    </div>

                    <div class="shipment-create-field">
                        <label for="package_height">
                            Alto (cm)
                        </label>

                        <input
                            id="package_height"
                            name="packages[0][height]"
                            type="number"
                            value="{{ old(
                                'packages.0.height'
                            ) }}"
                            min="0.01"
                            step="0.01"
                        >
                    </div>

                    <div class="shipment-create-field">
                        <label for="package_width">
                            Ancho (cm)
                        </label>

                        <input
                            id="package_width"
                            name="packages[0][width]"
                            type="number"
                            value="{{ old(
                                'packages.0.width'
                            ) }}"
                            min="0.01"
                            step="0.01"
                        >
                    </div>

                    <div class="shipment-create-field">
                        <label for="package_length">
                            Largo (cm)
                        </label>

                        <input
                            id="package_length"
                            name="packages[0][length]"
                            type="number"
                            value="{{ old(
                                'packages.0.length'
                            ) }}"
                            min="0.01"
                            step="0.01"
                        >
                    </div>

                    <div class="shipment-create-field">
                        <label for="package_value">
                            Valor del paquete
                        </label>

                        <input
                            id="package_value"
                            name="
                                packages[0][declared_value]
                            "
                            type="number"
                            value="{{ old(
                                'packages.0.declared_value'
                            ) }}"
                            min="0"
                            step="0.01"
                        >
                    </div>

                    <div
                        class="
                            shipment-create-field
                            shipment-create-checkbox-field
                        "
                    >
                        <input
                            name="packages[0][is_fragile]"
                            type="hidden"
                            value="0"
                        >

                        <label class="shipment-create-checkbox">
                            <input
                                name="
                                    packages[0][is_fragile]
                                "
                                type="checkbox"
                                value="1"
                                @checked(
                                    old(
                                        'packages.0.is_fragile'
                                    )
                                )
                            >

                            <span>
                                El paquete es frágil
                            </span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="shipment-create-card">
                <header>
                    <p class="shipment-create-eyebrow">
                        Programación
                    </p>

                    <h2>Información adicional</h2>
                </header>

                <div class="shipment-create-field-grid">
                    <div class="shipment-create-field">
                        <label for="scheduled_at">
                            Fecha programada
                        </label>

                        <input
                            id="scheduled_at"
                            name="scheduled_at"
                            type="datetime-local"
                            value="{{ old(
                                'scheduled_at'
                            ) }}"
                        >
                    </div>

                    <div class="shipment-create-field">
                        <label for="declared_value">
                            Valor total declarado
                        </label>

                        <input
                            id="declared_value"
                            name="declared_value"
                            type="number"
                            value="{{ old(
                                'declared_value'
                            ) }}"
                            min="0"
                            step="0.01"
                        >
                    </div>

                    <div
                        class="
                            shipment-create-field
                            shipment-create-field-full
                        "
                    >
                        <label for="delivery_instructions">
                            Instrucciones de entrega
                        </label>

                        <textarea
                            id="delivery_instructions"
                            name="delivery_instructions"
                            rows="4"
                            maxlength="5000"
                        >{{ old(
                            'delivery_instructions'
                        ) }}</textarea>
                    </div>

                    <div
                        class="
                            shipment-create-field
                            shipment-create-field-full
                        "
                    >
                        <label for="notes">
                            Notas
                        </label>

                        <textarea
                            id="notes"
                            name="notes"
                            rows="4"
                            maxlength="5000"
                        >{{ old('notes') }}</textarea>
                    </div>
                </div>
            </section>

            <footer class="shipment-create-actions">
                <a href="{{ route(
                    'portal.shipments.index'
                ) }}">
                    Cancelar
                </a>

                <button type="submit">
                    Registrar envío
                </button>
            </footer>
        </form>
    </section>
@endsection