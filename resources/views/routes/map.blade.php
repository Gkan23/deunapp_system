@extends('layouts.portal')

@section(
    'title',
    'Mapa de la ruta #'.$deliveryRoute->id.' | DeUnapp'
)

@section('content')
    @php
        $routeStatus =
            $deliveryRoute
                ->routeStatus
                ?->status_name
            ?? 'UNKNOWN';
    @endphp

    <section
        id="route-map-application"
        class="route-map-application"
        data-mapbox-token="{{ $mapboxPublicToken }}"
        data-map-data-url="{{ $mapDataUrl }}"
    >
        <header class="route-map-header">
            <div>
                <p class="route-map-eyebrow">
                    Operación de entrega
                </p>

                <h1 class="route-map-title">
                    Mapa de la ruta
                    #{{ $deliveryRoute->id }}
                </h1>

                <p class="route-map-subtitle">
                    Fecha:
                    {{
                        $deliveryRoute
                            ->route_date
                            ?->format('d/m/Y')
                        ?? 'Sin fecha'
                    }}
                </p>
            </div>

            <div class="route-map-header-actions">
                <span
                    class="route-map-route-status"
                    data-status="{{
                        strtolower($routeStatus)
                    }}"
                >
                    {{
                        str_replace(
                            '_',
                            ' ',
                            $routeStatus
                        )
                    }}
                </span>

                <a
                    href="{{ route(
                        'portal.routes.show',
                        $deliveryRoute
                    ) }}"
                    class="route-map-detail-link"
                >
                    Ver detalle
                </a>

                <a
                    href="{{ route(
                        'portal.routes.index'
                    ) }}"
                    class="route-map-back-link"
                >
                    Volver a rutas
                </a>
            </div>
        </header>

        <section
            class="route-map-summary"
            aria-label="Resumen de la ruta"
        >
            <article class="route-map-summary-card">
                <span class="route-map-summary-label">
                    Repartidor
                </span>

                <strong>
                    {{
                        $deliveryRoute
                            ->courier
                            ?->user
                            ?->name
                        ?? 'No disponible'
                    }}
                </strong>
            </article>

            <article class="route-map-summary-card">
                <span class="route-map-summary-label">
                    Vehículo
                </span>

                @if ($deliveryRoute->vehicle !== null)
                    <strong>
                        {{
                            $deliveryRoute
                                ->vehicle
                                ->plate_number
                        }}
                    </strong>

                    <span>
                        {{
                            $deliveryRoute
                                ->vehicle
                                ->vehicleType
                                ?->type_name
                            ?? 'Tipo no disponible'
                        }}
                    </span>
                @else
                    <strong>
                        Sin vehículo
                    </strong>
                @endif
            </article>

            <article class="route-map-summary-card">
                <span class="route-map-summary-label">
                    Distancia estimada
                </span>

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
                        No disponible
                    @endif
                </strong>
            </article>
        </section>

        <section class="route-map-content">
            <aside
                class="route-map-sidebar"
                aria-label="Paradas de la ruta"
            >
                <header class="route-map-sidebar-header">
                    <h2>
                        Paradas
                    </h2>

                    <p id="route-map-stop-count">
                        Cargando información…
                    </p>
                </header>

                <div
                    id="route-map-message"
                    class="route-map-message"
                    role="status"
                    aria-live="polite"
                >
                    Preparando el mapa…
                </div>

                <ol
                    id="route-map-stop-list"
                    class="route-map-stop-list"
                ></ol>
            </aside>

            <div class="route-map-map-wrapper">
                <div
                    id="route-map"
                    class="route-map-map"
                    aria-label="Mapa de entregas de la ruta"
                ></div>

                <div
                    class="route-map-legend"
                    aria-label="Leyenda del mapa"
                >
                    <div>
                        <span
                            class="route-map-legend-dot
                                route-map-legend-courier"
                            aria-hidden="true"
                        ></span>

                        Repartidor
                    </div>

                    <div>
                        <span
                            class="route-map-legend-dot
                                route-map-legend-origin"
                            aria-hidden="true"
                        ></span>

                        Origen
                    </div>

                    <div>
                        <span
                            class="route-map-legend-dot
                                route-map-legend-destination"
                            aria-hidden="true"
                        ></span>

                        Destino
                    </div>
                </div>
            </div>
        </section>
    </section>
@endsection