@extends('layouts.portal')

@section(
    'title',
    'Ruta #'.$deliveryRoute->id.' | DeUnapp'
)

@section('content')
    @php
        $routeStatus =
            $deliveryRoute
                ->routeStatus
                ?->status_name
            ?? 'UNKNOWN';

        $courier =
            $deliveryRoute->courier;

        $provider =
            $courier
                ?->deliveryProvider;

        $vehicle =
            $deliveryRoute->vehicle;
    @endphp

    <section class="route-show-page">
        <header class="route-show-header">
            <div>
                <p class="route-show-eyebrow">
                    Operación de entrega
                </p>

                <h1>
                    Ruta #{{ $deliveryRoute->id }}
                </h1>

                <p class="route-show-subtitle">
                    Consulta el recorrido, el repartidor,
                    el vehículo y los envíos asignados.
                </p>
            </div>

            <div class="route-show-header-actions">
                <span
                    class="route-show-status"
                    data-status="{{
                        strtolower($routeStatus)
                    }}"
                >
                    {{ str_replace(
                        '_',
                        ' ',
                        $routeStatus
                    ) }}
                </span>

                <a
                    href="{{ route(
                        'routes.map.view',
                        $deliveryRoute
                    ) }}"
                    class="route-show-map-link"
                >
                    Ver mapa
                </a>

                <a
                    href="{{ route(
                        'portal.routes.index'
                    ) }}"
                    class="route-show-back-link"
                >
                    Volver a rutas
                </a>
            </div>
        </header>

        @if (session('status'))
            <div
                class="route-show-flash"
                role="status"
            >
                {{ session('status') }}
            </div>
        @endif

        @error('activation')
            <div
                class="route-show-alert route-show-alert-error"
                role="alert"
            >
                <strong>
                    No fue posible activar la ruta.
                </strong>

                <p>
                    {{ $message }}
                </p>
            </div>
        @enderror

        @error('completion')
            <div
                class="route-show-alert route-show-alert-error"
                role="alert"
            >
                <strong>
                    No fue posible completar la ruta.
                </strong>

                <p>
                    {{ $message }}
                </p>
            </div>
        @enderror

        @error('cancellation')
            <div
                class="route-show-alert route-show-alert-error"
                role="alert"
            >
                <strong>
                    No fue posible cancelar la ruta.
                </strong>

                <p>
                    {{ $message }}
                </p>
            </div>
        @enderror

        @can('activate', $deliveryRoute)
            @if ($routeStatus === 'PLANNED')
                <section class="route-show-activation">
                    <div>
                        <p class="route-show-section-eyebrow">
                            Inicio de operación
                        </p>

                        <h2>Activar ruta</h2>

                        <p>
                            Al activar la ruta, el repartidor
                            dejará de aparecer como disponible
                            y los envíos pasarán a estar en
                            progreso.
                        </p>
                    </div>

                    <form
                        method="POST"
                        action="{{ route(
                            'portal.routes.activate',
                            $deliveryRoute
                        ) }}"
                        onsubmit="return confirm(
                            '¿Confirmas que deseas activar esta ruta?'
                        );"
                    >
                        @csrf
                        @method('PATCH')

                        <button
                            type="submit"
                            class="route-show-activate-button"
                        >
                            Activar ruta
                        </button>
                    </form>
                </section>
            @endif
        @endcan

        @can('complete', $deliveryRoute)
            @if ($routeStatus === 'ACTIVE')
                <section class="route-show-completion">
                    <div>
                        <p class="route-show-section-eyebrow">
                            Finalización
                        </p>

                        <h2>Completar ruta</h2>

                        <p>
                            La ruta solamente podrá completarse
                            cuando todos sus envíos estén
                            entregados o marcados como fallidos.
                        </p>
                    </div>

                    <form
                        method="POST"
                        action="{{ route(
                            'portal.routes.complete',
                            $deliveryRoute
                        ) }}"
                        onsubmit="return confirm(
                            '¿Confirmas que deseas completar esta ruta?'
                        );"
                    >
                        @csrf
                        @method('PATCH')

                        <button
                            type="submit"
                            class="route-show-complete-button"
                        >
                            Completar ruta
                        </button>
                    </form>
                </section>
            @endif
        @endcan

        @can('cancel', $deliveryRoute)
            @if (
                in_array(
                    $routeStatus,
                    [
                        'PLANNED',
                        'ACTIVE',
                    ],
                    true
                )
            )
                <section class="route-show-cancellation">
                    <div>
                        <p class="route-show-section-eyebrow">
                            Cancelación
                        </p>

                        <h2>Cancelar ruta</h2>

                        <p>
                            Los envíos pendientes o en progreso
                            serán devueltos para reprogramación.
                            También se registrará una incidencia
                            con el motivo indicado.
                        </p>
                    </div>

                    <form
                        method="POST"
                        action="{{ route(
                            'portal.routes.cancel',
                            $deliveryRoute
                        ) }}"
                        class="route-show-cancellation-form"
                        onsubmit="return confirm(
                            '¿Confirmas que deseas cancelar esta ruta?'
                        );"
                    >
                        @csrf
                        @method('PATCH')

                        <div class="route-show-field">
                            <label for="reason">
                                Motivo de cancelación
                            </label>

                            <textarea
                                id="reason"
                                name="reason"
                                rows="4"
                                maxlength="2000"
                                placeholder="Explica por qué se cancelará la ruta."
                                required
                            >{{ old('reason') }}</textarea>

                            @error('reason')
                                <span
                                    class="route-show-field-error"
                                >
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="route-show-cancel-button"
                        >
                            Cancelar ruta
                        </button>
                    </form>
                </section>
            @endif
        @endcan

        <section class="route-show-summary">
            <article class="route-show-summary-card">
                <span>Envíos asignados</span>

                <strong>
                    {{ $totalShipments }}
                </strong>
            </article>

            <article class="route-show-summary-card">
                <span>Entregados</span>

                <strong>
                    {{ $deliveredShipments }}
                </strong>
            </article>

            <article class="route-show-summary-card">
                <span>Fallidos</span>

                <strong>
                    {{ $failedShipments }}
                </strong>
            </article>

            <article class="route-show-summary-card">
                <span>Distancia estimada</span>

                <strong>
                    @if (
                        $deliveryRoute
                            ->estimated_distance_km
                        !== null
                    )
                        {{
                            number_format(
                                (float) $deliveryRoute
                                    ->estimated_distance_km,
                                2
                            )
                        }} km
                    @else
                        No estimada
                    @endif
                </strong>
            </article>
        </section>

        <div class="route-show-grid">
            <section class="route-show-card">
                <header class="route-show-card-header">
                    <div>
                        <p class="route-show-section-eyebrow">
                            Información
                        </p>

                        <h2>Datos de la ruta</h2>
                    </div>
                </header>

                <dl class="route-show-details">
                    <div>
                        <dt>Identificador</dt>

                        <dd>
                            #{{ $deliveryRoute->id }}
                        </dd>
                    </div>

                    <div>
                        <dt>Estado</dt>

                        <dd>
                            {{ str_replace(
                                '_',
                                ' ',
                                $routeStatus
                            ) }}
                        </dd>
                    </div>

                    <div>
                        <dt>Fecha programada</dt>

                        <dd>
                            {{
                                $deliveryRoute
                                    ->route_date
                                    ?->format('d/m/Y')
                                ?? 'Sin fecha'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt>Inicio</dt>

                        <dd>
                            {{
                                $deliveryRoute
                                    ->started_at
                                    ?->format(
                                        'd/m/Y H:i'
                                    )
                                ?? 'Sin iniciar'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt>Finalización</dt>

                        <dd>
                            {{
                                $deliveryRoute
                                    ->finished_at
                                    ?->format(
                                        'd/m/Y H:i'
                                    )
                                ?? 'Sin finalizar'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt>Rol actual</dt>

                        <dd>
                            {{ str_replace(
                                '_',
                                ' ',
                                $roleName
                            ) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="route-show-card">
                <header class="route-show-card-header">
                    <div>
                        <p class="route-show-section-eyebrow">
                            Asignación
                        </p>

                        <h2>Repartidor y proveedor</h2>
                    </div>
                </header>

                <dl class="route-show-details">
                    <div>
                        <dt>Repartidor</dt>

                        <dd>
                            {{
                                $courier
                                    ?->user
                                    ?->name
                                ?? 'No disponible'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt>Correo del repartidor</dt>

                        <dd>
                            {{
                                $courier
                                    ?->user
                                    ?->email
                                ?? 'No disponible'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt>Proveedor</dt>

                        <dd>
                            {{
                                $provider
                                    ?->business_name
                                ?? $provider
                                    ?->user
                                    ?->name
                                ?? 'No disponible'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt>Repartidor activo</dt>

                        <dd>
                            {{
                                $courier?->is_active
                                    ? 'Sí'
                                    : 'No'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt>Disponibilidad</dt>

                        <dd>
                            {{
                                $courier?->is_available
                                    ? 'Disponible'
                                    : 'No disponible'
                            }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="route-show-card">
                <header class="route-show-card-header">
                    <div>
                        <p class="route-show-section-eyebrow">
                            Transporte
                        </p>

                        <h2>Vehículo asignado</h2>
                    </div>
                </header>

                @if ($vehicle !== null)
                    <dl class="route-show-details">
                        <div>
                            <dt>Placa</dt>

                            <dd>
                                {{ $vehicle->plate_number }}
                            </dd>
                        </div>

                        <div>
                            <dt>Tipo</dt>

                            <dd>
                                {{
                                    $vehicle
                                        ->vehicleType
                                        ?->type_name
                                    ?? 'No disponible'
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>Estado</dt>

                            <dd>
                                {{
                                    str_replace(
                                        '_',
                                        ' ',
                                        $vehicle
                                            ->vehicleStatus
                                            ?->status_name
                                        ?? 'UNKNOWN'
                                    )
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>Marca</dt>

                            <dd>
                                {{
                                    $vehicle->brand
                                    ?? 'No registrada'
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>Modelo</dt>

                            <dd>
                                {{
                                    $vehicle->model
                                    ?? 'No registrado'
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>Color</dt>

                            <dd>
                                {{
                                    $vehicle->color
                                    ?? 'No registrado'
                                }}
                            </dd>
                        </div>
                    </dl>
                @else
                    <div class="route-show-empty">
                        <strong>
                            Sin vehículo asignado.
                        </strong>

                        <p>
                            Esta ruta puede corresponder a datos
                            anteriores a la asignación obligatoria
                            de vehículos.
                        </p>
                    </div>
                @endif
            </section>
        </div>

        <section class="route-show-stops-section">
            <header class="route-show-section-header">
                <div>
                    <p class="route-show-section-eyebrow">
                        Recorrido
                    </p>

                    <h2>Paradas de entrega</h2>

                    <p>
                        Los envíos aparecen según el orden
                        establecido para la ruta.
                    </p>
                </div>

                <span class="route-show-section-count">
                    {{ $totalShipments }}

                    {{
                        $totalShipments === 1
                            ? 'parada'
                            : 'paradas'
                    }}
                </span>
            </header>

            @if ($stops->isEmpty())
                <div class="route-show-empty">
                    <strong>
                        Esta ruta no tiene envíos asignados.
                    </strong>

                    <p>
                        Cuando se agreguen envíos, aparecerán
                        aquí en su orden de entrega.
                    </p>
                </div>
            @else
                <ol class="route-show-stop-list">
                    @foreach ($stops as $stop)
                        @php
                            $routeShipment =
                                data_get(
                                    $stop,
                                    'route_shipment'
                                )
                                ?? data_get(
                                    $stop,
                                    'assignment'
                                );

                            if (
                                $routeShipment === null
                                && is_object($stop)
                            ) {
                                $routeShipment = $stop;
                            }

                            $shipment =
                                data_get(
                                    $stop,
                                    'shipment'
                                );

                            $deliveryOrder =
                                $routeShipment
                                    ?->delivery_order
                                ?? data_get(
                                    $stop,
                                    'delivery_order'
                                )
                                ?? $loop->iteration;

                            $deliveryStatus =
                                $routeShipment
                                    ?->delivery_status
                                ?? data_get(
                                    $stop,
                                    'delivery_status'
                                )
                                ?? 'PENDING';
                        @endphp

                        <li class="route-show-stop">
                            <div class="route-show-stop-order">
                                {{ $deliveryOrder }}
                            </div>

                            <div class="route-show-stop-content">
                                <div class="route-show-stop-heading">
                                    <div>
                                        <span>
                                            Parada
                                            {{ $deliveryOrder }}
                                        </span>

                                        @if ($shipment !== null)
                                            <h3>
                                                {{
                                                    $shipment
                                                        ->tracking_code
                                                }}
                                            </h3>
                                        @else
                                            <h3>
                                                Envío con acceso restringido
                                            </h3>
                                        @endif
                                    </div>

                                    <span
                                        class="route-show-delivery-status"
                                        data-status="{{
                                            strtolower(
                                                $deliveryStatus
                                            )
                                        }}"
                                    >
                                        {{
                                            str_replace(
                                                '_',
                                                ' ',
                                                $deliveryStatus
                                            )
                                        }}
                                    </span>
                                </div>

                                @if ($shipment !== null)
                                    <div class="route-show-stop-grid">
                                        <div>
                                            <span>
                                                Estado del envío
                                            </span>

                                            <strong>
                                                {{
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $shipment
                                                            ->shipmentStatus
                                                            ?->status_name
                                                        ?? 'UNKNOWN'
                                                    )
                                                }}
                                            </strong>
                                        </div>

                                        <div>
                                            <span>
                                                Destinatario
                                            </span>

                                            <strong>
                                                {{
                                                    trim(
                                                        (
                                                            $shipment
                                                                ->recipient
                                                                ?->first_name
                                                            ?? ''
                                                        )
                                                        .' '.
                                                        (
                                                            $shipment
                                                                ->recipient
                                                                ?->last_name
                                                            ?? ''
                                                        )
                                                    )
                                                    ?: 'No disponible'
                                                }}
                                            </strong>
                                        </div>

                                        <div>
                                            <span>Teléfono</span>

                                            <strong>
                                                {{
                                                    $shipment
                                                        ->recipient
                                                        ?->phone
                                                    ?? 'No disponible'
                                                }}
                                            </strong>
                                        </div>

                                        <div>
                                            <span>Paquetes</span>

                                            <strong>
                                                {{
                                                    $shipment
                                                        ->packages_count
                                                    ?? 0
                                                }}
                                            </strong>
                                        </div>

                                        <div class="route-show-stop-address">
                                            <span>
                                                Dirección de entrega
                                            </span>

                                            <strong>
                                                {{
                                                    $shipment
                                                        ->destinationAddress
                                                        ?->address_line
                                                    ?? 'No disponible'
                                                }}
                                            </strong>

                                            @if (
                                                $shipment
                                                    ->destinationAddress
                                                    ?->municipality
                                                    ?->municipality_name
                                            )
                                                <small>
                                                    {{
                                                        $shipment
                                                            ->destinationAddress
                                                            ->municipality
                                                            ->municipality_name
                                                    }}
                                                </small>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="route-show-stop-actions">
                                        <a
                                            href="{{ route(
                                                'portal.shipments.show',
                                                $shipment
                                            ) }}"
                                            class="route-show-stop-link"
                                        >
                                            Ver envío
                                        </a>

                                        <a
                                            href="{{ route(
                                                'portal.shipments.tracking',
                                                $shipment
                                            ) }}"
                                            class="route-show-stop-secondary-link"
                                        >
                                            Ver seguimiento
                                        </a>
                                    </div>
                                @else
                                    <div class="route-show-restricted">
                                        <strong>
                                            Información restringida
                                        </strong>

                                        <p>
                                            Puedes consultar la ruta, pero
                                            no tienes autorización para ver
                                            los datos personales de este
                                            envío.
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </section>
@endsection