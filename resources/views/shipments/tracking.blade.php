@extends('layouts.portal')

@section(
    'title',
    'Seguimiento '.$shipment->tracking_code.' | DeUnapp'
)

@section('content')
    <section
        id="shipment-tracking-application"
        class="shipment-tracking-page"
        data-mapbox-token="{{ $mapboxToken }}"
        data-tracking-url="{{ $trackingDataUrl }}"
        data-origin-latitude="{{
            $shipment->originAddress->latitude ?? ''
        }}"
        data-origin-longitude="{{
            $shipment->originAddress->longitude ?? ''
        }}"
        data-origin-address="{{
            $shipment->originAddress->address_line
        }}"
        data-destination-latitude="{{
            $shipment->destinationAddress->latitude ?? ''
        }}"
        data-destination-longitude="{{
            $shipment->destinationAddress->longitude ?? ''
        }}"
        data-destination-address="{{
            $shipment->destinationAddress->address_line
        }}"
    >
        <header class="shipment-tracking-header">
            <div>
                <p class="shipment-tracking-eyebrow">
                    Seguimiento en tiempo real
                </p>

                <h1>
                    {{ $shipment->tracking_code }}
                </h1>

                <p>
                    Consulta la ubicación más reciente
                    relacionada con este envío.
                </p>
            </div>

            <div class="shipment-tracking-header-actions">
                <button
                    id="shipment-tracking-refresh"
                    type="button"
                    class="shipment-tracking-refresh"
                >
                    Actualizar ubicación
                </button>

                <a
                    href="{{ route(
                        'portal.shipments.show',
                        $shipment
                    ) }}"
                    class="shipment-tracking-back-link"
                >
                    Volver al detalle
                </a>
            </div>
        </header>

        <section class="shipment-tracking-summary">
            <article>
                <span>Estado del envío</span>

                <strong>
                    {{ str_replace(
                        '_',
                        ' ',
                        $shipment
                            ->shipmentStatus
                            ->status_name
                    ) }}
                </strong>
            </article>

            <article>
                <span>Disponibilidad</span>

                <strong
                    id="shipment-tracking-availability"
                >
                    Consultando...
                </strong>
            </article>

            <article>
                <span>Repartidor</span>

                <strong
                    id="shipment-tracking-courier"
                >
                    Sin información
                </strong>
            </article>

            <article>
                <span>Última actualización</span>

                <strong
                    id="shipment-tracking-recorded-at"
                >
                    Sin información
                </strong>
            </article>
        </section>

        <div
            id="shipment-tracking-message"
            class="shipment-tracking-message"
            role="status"
        >
            Cargando información de seguimiento...
        </div>

        <div class="shipment-tracking-content">
            <aside class="shipment-tracking-sidebar">
                <section>
                    <p class="shipment-tracking-eyebrow">
                        Recorrido
                    </p>

                    <h2>Origen y destino</h2>

                    <div class="shipment-tracking-address-list">
                        <article>
                            <span
                                class="
                                    shipment-tracking-address-marker
                                    shipment-tracking-origin-marker
                                "
                            >
                                O
                            </span>

                            <div>
                                <strong>Origen</strong>

                                <span>
                                    {{ $shipment
                                        ->originAddress
                                        ->address_line }}
                                </span>

                                <small>
                                    {{ $shipment
                                        ->originAddress
                                        ->municipality
                                        ?->municipality_name
                                        ?? 'Municipio no disponible' }}
                                </small>
                            </div>
                        </article>

                        <article>
                            <span
                                class="
                                    shipment-tracking-address-marker
                                    shipment-tracking-destination-marker
                                "
                            >
                                D
                            </span>

                            <div>
                                <strong>Destino</strong>

                                <span>
                                    {{ $shipment
                                        ->destinationAddress
                                        ->address_line }}
                                </span>

                                <small>
                                    {{ $shipment
                                        ->destinationAddress
                                        ->municipality
                                        ?->municipality_name
                                        ?? 'Municipio no disponible' }}
                                </small>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="shipment-tracking-route-information">
                    <p class="shipment-tracking-eyebrow">
                        Asignación
                    </p>

                    <h2>Información de ruta</h2>

                    <dl>
                        <div>
                            <dt>Ruta</dt>

                            <dd id="shipment-tracking-route">
                                Sin asignar
                            </dd>
                        </div>

                        <div>
                            <dt>Orden de entrega</dt>

                            <dd id="shipment-tracking-order">
                                Sin asignar
                            </dd>
                        </div>

                        <div>
                            <dt>Estado de entrega</dt>

                            <dd id="shipment-tracking-delivery-status">
                                Sin asignar
                            </dd>
                        </div>

                        <div>
                            <dt>Precisión GPS</dt>

                            <dd id="shipment-tracking-accuracy">
                                Sin información
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="shipment-tracking-auto-refresh">
                    <strong>
                        Actualización automática
                    </strong>

                    <p>
                        La ubicación se consulta nuevamente
                        cada 30 segundos.
                    </p>
                </section>
            </aside>

            <section class="shipment-tracking-map-wrapper">
                <div
                    id="shipment-tracking-map"
                    class="shipment-tracking-map"
                    aria-label="Mapa de seguimiento del envío"
                ></div>

                <div class="shipment-tracking-legend">
                    <div>
                        <span
                            class="
                                shipment-tracking-legend-dot
                                shipment-tracking-legend-courier
                            "
                        ></span>

                        Repartidor
                    </div>

                    <div>
                        <span
                            class="
                                shipment-tracking-legend-dot
                                shipment-tracking-legend-origin
                            "
                        ></span>

                        Origen
                    </div>

                    <div>
                        <span
                            class="
                                shipment-tracking-legend-dot
                                shipment-tracking-legend-destination
                            "
                        ></span>

                        Destino
                    </div>
                </div>
            </section>
        </div>
    </section>
@endsection