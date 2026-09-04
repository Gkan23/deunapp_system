@extends('layouts.portal')

@section('title', 'Reportar incidente | DeUnapp')

@section('content')
    <section class="incident-create-page">
        <header class="incident-create-header">
            <div>
                <p class="incident-create-eyebrow">
                    Atención del envío
                </p>

                <h1>Reportar incidente</h1>

                <p>
                    Describe lo ocurrido para que el equipo
                    pueda revisar la situación.
                </p>
            </div>

            <a
                href="{{ route('portal.shipments.show', $shipment) }}"
                class="incident-create-secondary-button"
            >
                Volver al envío
            </a>
        </header>

        @if ($errors->any())
            <div
                class="incident-create-alert"
                role="alert"
            >
                <strong>
                    No fue posible registrar el incidente.
                </strong>

                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="incident-create-grid">
            <aside class="incident-create-card">
                <p class="incident-create-eyebrow">
                    Envío relacionado
                </p>

                <h2>{{ $shipment->tracking_code }}</h2>

                <dl class="incident-create-details">
                    <div>
                        <dt>Estado del envío</dt>

                        <dd>
                            {{ str_replace(
                                '_',
                                ' ',
                                $shipment->shipmentStatus?->status_name
                                    ?? 'Sin estado'
                            ) }}
                        </dd>
                    </div>

                    <div>
                        <dt>Reportado por</dt>
                        <dd>{{ $user->name }}</dd>
                    </div>

                    <div>
                        <dt>Estado inicial del incidente</dt>
                        <dd>OPEN</dd>
                    </div>
                </dl>

                <p class="incident-create-help">
                    El reporte quedará asociado a este envío
                    y a tu cuenta. Su creación quedará
                    registrada en la auditoría del sistema.
                </p>
            </aside>

            <section class="incident-create-card">
                <h2>Información del incidente</h2>

                @if ($incidentTypes->isEmpty())
                    <div
                        class="incident-create-empty"
                        role="status"
                    >
                        No hay tipos de incidente disponibles.
                        Contacta al equipo de soporte.
                    </div>
                @else
                    <form
                        method="POST"
                        action="{{ route(
                            'portal.shipments.incidents.store',
                            $shipment
                        ) }}"
                        class="incident-create-form"
                    >
                        @csrf

                        <div class="incident-create-field">
                            <label for="incident_type">
                                Tipo de incidente
                            </label>

                            <select
                                id="incident_type"
                                name="incident_type"
                                required
                                @error('incident_type')
                                    aria-invalid="true"
                                    aria-describedby="incident-type-error"
                                @enderror
                            >
                                <option value="">
                                    Selecciona un tipo
                                </option>

                                @foreach ($incidentTypes as $incidentType)
                                    <option
                                        value="{{ $incidentType->type_name }}"
                                        @selected(
                                            old('incident_type')
                                                === $incidentType->type_name
                                        )
                                    >
                                        {{ str_replace(
                                            '_',
                                            ' ',
                                            $incidentType->type_name
                                        ) }}
                                    </option>
                                @endforeach
                            </select>

                            @error('incident_type')
                                <span
                                    id="incident-type-error"
                                    class="incident-create-field-error"
                                >
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="incident-create-field">
                            <label for="description">
                                Descripción
                            </label>

                            <textarea
                                id="description"
                                name="description"
                                rows="9"
                                maxlength="5000"
                                required
                                aria-describedby="incident-description-help{{ $errors->has('description') ? ' incident-description-error' : '' }}"
                                @error('description')
                                    aria-invalid="true"
                                @enderror
                                placeholder="Explica qué ocurrió y agrega los detalles necesarios para revisar el incidente."
                            >{{ old('description') }}</textarea>

                            <p
                                id="incident-description-help"
                                class="incident-create-help"
                            >
                                Máximo 5000 caracteres. No incluyas
                                contraseñas ni información de pago.
                            </p>

                            @error('description')
                                <span
                                    id="incident-description-error"
                                    class="incident-create-field-error"
                                >
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="incident-create-actions">
                            <a
                                href="{{ route(
                                    'portal.shipments.show',
                                    $shipment
                                ) }}"
                                class="incident-create-secondary-button"
                            >
                                Cancelar
                            </a>

                            <button
                                type="submit"
                                class="incident-create-primary-button"
                            >
                                Reportar incidente
                            </button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </section>
@endsection