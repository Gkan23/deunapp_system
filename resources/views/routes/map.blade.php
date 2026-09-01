<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        Ruta #{{ $deliveryRoute->id }} | DeUnapp
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body class="route-map-body">
    <main
        id="route-map-application"
        class="route-map-application"
        data-mapbox-token="{{ $mapboxPublicToken }}"
        data-map-data-url="{{ $mapDataUrl }}"
    >
        <header class="route-map-header">
            <div>
                <p class="route-map-eyebrow">
                    DeUnapp System
                </p>

                <h1 class="route-map-title">
                    Mapa de la ruta
                    #{{ $deliveryRoute->id }}
                </h1>

                <p class="route-map-subtitle">
                    Fecha:
                    {{ $deliveryRoute->route_date->format('d/m/Y') }}
                </p>
            </div>

            <div class="route-map-route-status">
                {{ $deliveryRoute->routeStatus->status_name }}
            </div>
        </header>

        <section class="route-map-summary">
            <article class="route-map-summary-card">
                <span class="route-map-summary-label">
                    Repartidor
                </span>

                <strong>
                    {{ $deliveryRoute->courier->user->name }}
                </strong>
            </article>

            <article class="route-map-summary-card">
                <span class="route-map-summary-label">
                    Vehículo
                </span>

                @if ($deliveryRoute->vehicle !== null)
                    <strong>
                        {{ $deliveryRoute->vehicle->plate_number }}
                    </strong>

                    <span>
                        {{ $deliveryRoute->vehicle->vehicleType->type_name }}
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
                        $deliveryRoute->estimated_distance_km
                        !== null
                    )
                        {{ number_format(
                            (float) $deliveryRoute->estimated_distance_km,
                            2
                        ) }}
                        km
                    @else
                        No disponible
                    @endif
                </strong>
            </article>
        </section>

        <section class="route-map-content">
            <aside class="route-map-sidebar">
                <div class="route-map-sidebar-header">
                    <div>
                        <h2>
                            Paradas
                        </h2>

                        <p id="route-map-stop-count">
                            Cargando información…
                        </p>
                    </div>
                </div>

                <div
                    id="route-map-message"
                    class="route-map-message"
                    role="status"
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

                <div class="route-map-legend">
                    <div>
                        <span
                            class="route-map-legend-dot
                                route-map-legend-courier"
                        ></span>

                        Repartidor
                    </div>

                    <div>
                        <span
                            class="route-map-legend-dot
                                route-map-legend-origin"
                        ></span>

                        Origen
                    </div>

                    <div>
                        <span
                            class="route-map-legend-dot
                                route-map-legend-destination"
                        ></span>

                        Destino
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>