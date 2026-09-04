@extends('layouts.portal')

@section('title', 'Ruta #'.$deliveryRoute->id.' | DeUnapp')

@section('content')
    @php
        $routeStatus = $deliveryRoute->routeStatus?->status_name
            ?? 'UNKNOWN';

        $provider = $deliveryRoute->courier?->deliveryProvider;
    @endphp

    <section class="route-show-page">
        <header class="route-show-header">
            <div>
                <p class="route-show-eyebrow">
                    Detalle de ruta
                </p>

                <h1>Ruta #{{ $deliveryRoute->id }}</h1>

                <p>
                    Consulta la asignación, las fechas
                    y el orden de entrega de los envíos.
                </p>
            </div>

            <div class="route-show-header-actions">
                <a
                    href="{{ route(
                        'routes.map.view',
                        $deliveryRoute
                    ) }}"
                    class="route-show-primary-button"
                >
                    Ver mapa
                </a>

                <a
                    href="{{ route('portal.routes.index') }}"
                    class="route-show-secondary-button"
                >
                    Volver a rutas
                </a>
            </div>
        </header>

        @if (session('status'))
            <div
                class="route-show-notice"
                role="status"
            >
                {{ session('status') }}
            </div>
        @endif

        @error('activation')
            <div
                class="route-show-alert"
                role="alert"
            >
                <strong>
                    No fue posible activar la ruta.
                </strong>

                <p>{{ $message }}</p>
            </div>
        @enderror

        @can('activate', $deliveryRoute)
            @if ($routeStatus === 'PLANNED')
                <section
                    class="route-show-activation"
                    aria-labelledby="route-activation-title"
                >
                    <div>
                        <h2 id="route-activation-title">
                            Activar ruta
                        </h2>

                        <p>
                            La ruta solo puede activarse en su fecha
                            programada, con el repartidor disponible
                            y los envíos preparados.
                        </p>

                        <p>
                            Al activarla, las entregas pasarán a estar
                            en progreso y el repartidor dejará de
                            estar disponible para nuevas rutas.
                        </p>
                    </div>

                    <form
                        method="POST"
                        action="{{ route(
                            'portal.routes.activate',
                            $deliveryRoute
                        ) }}"
                        class="route-show-activation-form"
                    >
                        @csrf
                        @method('PATCH')

                        <button
                            type="submit"
                            class="route-show-primary-button"
                        >
                            Activar ruta
                        </button>
                    </form>
                </section>
            @endif
        @endcan

        <section
            class="route-show-summary"
            aria-label="Resumen de asignaciones de la ruta"
        >
            <article>
                <span>Envíos asignados</span>
                <strong>{{ $totalShipments }}</strong>
            </article>

            <article>
                <span>Entregados en esta ruta</span>
                <strong>{{ $deliveredShipments }}</strong>
            </article>

            <article>
                <span>Asignaciones fallidas</span>
                <strong>{{ $failedShipments }}</strong>
            </article>
        </section>

        <div class="route-show-grid">
            <section class="route-show-card">
                <header class="route-show-card-header">
                    <h2>Información de la ruta</h2>

                    <span
                        class="route-show-status"
                        data-status="{{ strtolower($routeStatus) }}"
                    >
                        {{ str_replace('_', ' ', $routeStatus) }}
                    </span>
                </header>

                <dl class="route-show-details">
                    <div>
                        <dt>Fecha de la ruta</dt>

                        <dd>
                            {{ $deliveryRoute->route_date?->format('d/m/Y')
                                ?? 'Sin fecha' }}
                        </dd>
                    </div>

                    <div>
                        <dt>Distancia estimada</dt>

                        <dd>
                            {{ $deliveryRoute->estimated_distance_km !== null
                                ? number_format(
                                    (float) $deliveryRoute->estimated_distance_km,
                                    2
                                ).' km'
                                : 'No estimada' }}
                        </dd>
                    </div>

                    <div>
                        <dt>Inicio</dt>

                        <dd>
                            {{ $deliveryRoute->started_at?->format('d/m/Y H:i')
                                ?? 'Sin iniciar' }}
                        </dd>
                    </div>

                    <div>
                        <dt>Finalización</dt>

                        <dd>
                            {{ $deliveryRoute->finished_at?->format('d/m/Y H:i')
                                ?? 'Sin finalizar' }}
                        </dd>
                    </div>

                    <div>
                        <dt>Repartidor</dt>

                        <dd>
                            {{ $deliveryRoute->courier?->user?->name
                                ?? 'No disponible' }}
                        </dd>
                    </div>

                    <div>
                        <dt>Proveedor</dt>

                        <dd>
                            {{ $provider?->business_name
                                ?: ($provider?->user?->name
                                    ?? 'No disponible') }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="route-show-card">
                <header class="route-show-card-header">
                    <h2>Vehículo asignado</h2>
                </header>

                @if ($deliveryRoute->vehicle)
                    <dl class="route-show-details">
                        <div>
                            <dt>Placa</dt>

                            <dd>
                                {{ $deliveryRoute->vehicle->plate_number }}
                            </dd>
                        </div>

                        <div>
                            <dt>Tipo</dt>

                            <dd>
                                {{ $deliveryRoute->vehicle->vehicleType?->type_name
                                    ?? 'No disponible' }}
                            </dd>
                        </div>

                        <div>
                            <dt>Estado del vehículo</dt>

                            <dd>
                                {{ $deliveryRoute->vehicle->vehicleStatus?->status_name
                                    ?? 'No disponible' }}
                            </dd>
                        </div>

                        <div>
                            <dt>Marca</dt>

                            <dd>
                                {{ $deliveryRoute->vehicle->brand
                                    ?? 'No indicada' }}
                            </dd>
                        </div>

                        <div>
                            <dt>Modelo</dt>

                            <dd>
                                {{ $deliveryRoute->vehicle->model
                                    ?? 'No indicado' }}
                            </dd>
                        </div>

                        <div>
                            <dt>Color</dt>

                            <dd>
                                {{ $deliveryRoute->vehicle->color
                                    ?? 'No indicado' }}
                            </dd>
                        </div>
                    </dl>
                @else
                    <div class="route-show-empty">
                        Sin vehículo asignado.
                    </div>
                @endif
            </section>
        </div>

        <section class="route-show-card">
            <header class="route-show-stops-header">
                <div>
                    <p class="route-show-eyebrow">
                        Orden de entrega
                    </p>

                    <h2>Envíos de la ruta</h2>
                </div>

                <span>
                    {{ $totalShipments }}
                    {{ $totalShipments === 1
                        ? 'asignación'
                        : 'asignaciones' }}
                </span>
            </header>

            @if ($stops->isEmpty())
                <div class="route-show-empty">
                    Esta ruta no tiene envíos asignados.
                </div>
            @else
                <ol class="route-show-stop-list">
                    @foreach ($stops as $stop)
                        @php
                            $assignment = $stop['assignment'];
                            $shipment = $stop['shipment'];
                        @endphp

                        <li class="route-show-stop">
                            <div class="route-show-stop-heading">
                                <span
                                    class="route-show-order"
                                    aria-label="Posición {{ $assignment->delivery_order }}"
                                >
                                    {{ $assignment->delivery_order }}
                                </span>

                                <div class="route-show-stop-title">
                                    @if ($shipment !== null)
                                        <h3>
                                            {{ $shipment->tracking_code }}
                                        </h3>
                                    @else
                                        <h3>
                                            Envío con acceso restringido
                                        </h3>
                                    @endif

                                    <span
                                        class="route-show-status"
                                        data-status="{{ strtolower(
                                            $assignment->delivery_status
                                        ) }}"
                                    >
                                        {{ str_replace(
                                            '_',
                                            ' ',
                                            $assignment->delivery_status
                                        ) }}
                                    </span>
                                </div>
                            </div>

                            @if ($shipment !== null)
                                <dl
                                    class="route-show-details route-show-stop-details"
                                >
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
                                        <dt>Paquetes</dt>

                                        <dd>
                                            {{ $shipment->packages_count }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt>Destinatario</dt>

                                        <dd>
                                            @if ($shipment->recipient)
                                                {{ $shipment->recipient->first_name }}
                                                {{ $shipment->recipient->last_name }}
                                            @else
                                                No disponible
                                            @endif
                                        </dd>
                                    </div>

                                    <div>
                                        <dt>Teléfono</dt>

                                        <dd>
                                            {{ $shipment->recipient?->phone
                                                ?? 'No disponible' }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt>Dirección de entrega</dt>

                                        <dd>
                                            {{ $shipment->destinationAddress?->address_line
                                                ?? 'No disponible' }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt>Municipio</dt>

                                        <dd>
                                            {{ $shipment->destinationAddress?->municipality?->municipality_name
                                                ?? 'No disponible' }}
                                        </dd>
                                    </div>
                                </dl>

                                <footer class="route-show-stop-actions">
                                    <a
                                        href="{{ route(
                                            'portal.shipments.show',
                                            $shipment
                                        ) }}"
                                        class="route-show-secondary-button"
                                    >
                                        Ver envío
                                    </a>
                                </footer>
                            @else
                                <p class="route-show-restricted">
                                    Tu cuenta puede consultar esta ruta,
                                    pero no los datos de este envío.
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </section>
@endsection