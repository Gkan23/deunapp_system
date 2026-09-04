@extends('layouts.portal')

@section('title', 'Rutas de entrega | DeUnapp')

@section('content')
    <section class="route-index-page">
        <header class="route-index-header">
            <div>
                <p class="route-index-eyebrow">
                    Operación de entregas
                </p>

                <h1>
                    {{ $roleName === 'COURIER'
                        ? 'Mis rutas'
                        : 'Rutas de entrega' }}
                </h1>

                <p>
                    Consulta las rutas disponibles para tu cuenta,
                    revisa su detalle y abre su mapa de recorrido.
                </p>
            </div>

            <a
                href="{{ route('dashboard') }}"
                class="route-index-secondary-button"
            >
                Volver al panel
            </a>
        </header>

        @if (session('status'))
            <div
                class="route-index-notice"
                role="status"
            >
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div
                class="route-index-alert"
                role="alert"
            >
                <strong>
                    Revisa los filtros ingresados.
                </strong>

                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section
            class="route-index-summary"
            aria-label="Resumen de rutas accesibles"
        >
            <article>
                <span>Total de rutas</span>
                <strong>{{ $totalRoutes }}</strong>
            </article>

            <article>
                <span>Planificadas</span>
                <strong>{{ $plannedRoutes }}</strong>
            </article>

            <article>
                <span>Activas</span>
                <strong>{{ $activeRoutes }}</strong>
            </article>
        </section>

        <p class="route-index-summary-note">
            El resumen incluye todas tus rutas accesibles,
            sin aplicar los filtros del listado.
        </p>

        <form
            method="GET"
            action="{{ route('portal.routes.index') }}"
            class="route-index-filters"
        >
            <div class="route-index-field route-index-search">
                <label for="search">Buscar</label>

                <input
                    id="search"
                    name="search"
                    type="search"
                    maxlength="100"
                    value="{{ old('search', $search) }}"
                    placeholder="Número de ruta, repartidor o placa"
                >
            </div>

            <div class="route-index-field">
                <label for="status">Estado</label>

                <select
                    id="status"
                    name="status"
                >
                    <option value="">
                        Todos los estados
                    </option>

                    @foreach ($statuses as $status)
                        <option
                            value="{{ $status->status_name }}"
                            @selected(
                                old('status', $selectedStatus)
                                    === $status->status_name
                            )
                        >
                            {{ str_replace(
                                '_',
                                ' ',
                                $status->status_name
                            ) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="route-index-field">
                <label for="date_from">Desde</label>

                <input
                    id="date_from"
                    name="date_from"
                    type="date"
                    value="{{ old('date_from', $dateFrom) }}"
                >
            </div>

            <div class="route-index-field">
                <label for="date_to">Hasta</label>

                <input
                    id="date_to"
                    name="date_to"
                    type="date"
                    value="{{ old('date_to', $dateTo) }}"
                >
            </div>

            <div class="route-index-filter-actions">
                <button
                    type="submit"
                    class="route-index-primary-button"
                >
                    Aplicar filtros
                </button>

                <a
                    href="{{ route('portal.routes.index') }}"
                    class="route-index-secondary-button"
                >
                    Limpiar
                </a>
            </div>
        </form>

        <div class="route-index-results-header">
            <h2>Listado de rutas</h2>

            <span>
                {{ $routes->total() }}
                {{ $routes->total() === 1
                    ? 'resultado'
                    : 'resultados' }}
            </span>
        </div>

        @if ($routes->isEmpty())
            <div class="route-index-empty">
                <h3>No se encontraron rutas</h3>

                <p>
                    No tienes rutas disponibles para esta consulta.
                    Prueba con otros filtros.
                </p>
            </div>
        @else
            <div class="route-index-list">
                @foreach ($routes as $deliveryRoute)
                    @php
                        $statusName = $deliveryRoute->routeStatus?->status_name
                            ?? 'UNKNOWN';

                        $provider = $deliveryRoute->courier?->deliveryProvider;
                    @endphp

                    <article class="route-index-card">
                        <header class="route-index-card-header">
                            <div>
                                <p class="route-index-eyebrow">
                                    Ruta de entrega
                                </p>

                                <h3>
                                    Ruta #{{ $deliveryRoute->id }}
                                </h3>
                            </div>

                            <span
                                class="route-index-status"
                                data-status="{{ strtolower($statusName) }}"
                            >
                                {{ str_replace('_', ' ', $statusName) }}
                            </span>
                        </header>

                        <dl class="route-index-details">
                            <div>
                                <dt>Fecha</dt>

                                <dd>
                                    {{ $deliveryRoute->route_date?->format('d/m/Y')
                                        ?? 'Sin fecha' }}
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

                            <div>
                                <dt>Vehículo</dt>

                                <dd>
                                    {{ $deliveryRoute->vehicle?->plate_number
                                        ?? 'Sin vehículo asignado' }}
                                </dd>
                            </div>

                            <div>
                                <dt>Tipo de vehículo</dt>

                                <dd>
                                    {{ $deliveryRoute->vehicle?->vehicleType?->type_name
                                        ?? 'No disponible' }}
                                </dd>
                            </div>

                            <div>
                                <dt>Envíos asignados</dt>

                                <dd>
                                    {{ $deliveryRoute->route_shipments_count }}
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
                        </dl>

                        <footer class="route-index-card-actions">
                            <a
                                href="{{ route(
                                    'portal.routes.show',
                                    $deliveryRoute
                                ) }}"
                                class="route-index-secondary-button"
                                aria-label="Ver detalle de la ruta {{ $deliveryRoute->id }}"
                            >
                                Ver detalle
                            </a>

                            <a
                                href="{{ route(
                                    'routes.map.view',
                                    $deliveryRoute
                                ) }}"
                                class="route-index-primary-button"
                                aria-label="Ver mapa de la ruta {{ $deliveryRoute->id }}"
                            >
                                Ver mapa
                            </a>
                        </footer>
                    </article>
                @endforeach
            </div>

            @if ($routes->hasPages())
                <div class="route-index-pagination">
                    {{ $routes->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection