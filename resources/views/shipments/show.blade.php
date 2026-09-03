@extends('layouts.portal')

@section(
    'title',
    $shipment->tracking_code.' | DeUnapp'
)

@section('content')
    <section class="shipment-show-page">
        <header class="shipment-show-header">
            <div>
                <p class="shipment-show-eyebrow">
                    Detalle del envío
                </p>

                <h1>
                    {{ $shipment->tracking_code }}
                </h1>

                <p>
                    Información general, participantes,
                    direcciones y trazabilidad del envío.
                </p>
            </div>

            <div class="shipment-show-header-actions">
                <span
                    class="shipment-show-status"
                    data-status="{{
                        strtolower(
                            $shipment
                                ->shipmentStatus
                                ->status_name
                        )
                    }}"
                >
                    {{ str_replace(
                        '_',
                        ' ',
                        $shipment
                            ->shipmentStatus
                            ->status_name
                    ) }}
                </span>

                <a
                    href="{{ route(
                        'portal.shipments.index'
                    ) }}"
                    class="shipment-show-back-link"
                >
                    Volver a envíos
                </a>
            </div>
        </header>

        <section class="shipment-show-summary">
            <article>
                <span>Cliente</span>

                <strong>
                    {{ $shipment
                        ->customer
                        ->user
                        ->name }}
                </strong>

                <small>
                    {{ $shipment
                        ->customer
                        ->user
                        ->email }}
                </small>
            </article>

            <article>
                <span>Solicitado</span>

                <strong>
                    {{ $shipment->requested_at
                        ?->format('d/m/Y H:i')
                        ?? 'Sin fecha' }}
                </strong>
            </article>

            <article>
                <span>Valor declarado</span>

                <strong>
                    {{ $shipment->declared_value
                        !== null
                            ? 'C$ '.number_format(
                                (float) $shipment
                                    ->declared_value,
                                2
                            )
                            : 'No declarado' }}
                </strong>
            </article>

            <article>
                <span>Paquetes</span>

                <strong>
                    {{ $shipment->packages->count() }}
                </strong>
            </article>
        </section>

        <div class="shipment-show-grid">
            <section class="shipment-show-card">
                <header>
                    <p class="shipment-show-eyebrow">
                        Participantes
                    </p>

                    <h2>
                        Remitente y destinatario
                    </h2>
                </header>

                <div class="shipment-show-person-grid">
                    <article>
                        <span class="shipment-show-label">
                            Remitente
                        </span>

                        <strong>
                            {{ $shipment->sender->first_name }}
                            {{ $shipment->sender->last_name }}
                        </strong>

                        <span>
                            {{ $shipment->sender->phone }}
                        </span>

                        <span>
                            {{ $shipment->sender->email
                                ?? 'Sin correo registrado' }}
                        </span>

                        <span>
                            {{ $shipment
                                ->sender
                                ->identity_number
                                ?? 'Sin identificación' }}
                        </span>
                    </article>

                    <article>
                        <span class="shipment-show-label">
                            Destinatario
                        </span>

                        <strong>
                            {{ $shipment
                                ->recipient
                                ->first_name }}

                            {{ $shipment
                                ->recipient
                                ->last_name }}
                        </strong>

                        <span>
                            {{ $shipment
                                ->recipient
                                ->phone }}
                        </span>

                        <span>
                            {{ $shipment
                                ->recipient
                                ->email
                                ?? 'Sin correo registrado' }}
                        </span>

                        <span>
                            {{ $shipment
                                ->recipient
                                ->identity_number
                                ?? 'Sin identificación' }}
                        </span>
                    </article>
                </div>
            </section>

            <section class="shipment-show-card">
                <header>
                    <p class="shipment-show-eyebrow">
                        Recorrido
                    </p>

                    <h2>
                        Origen y destino
                    </h2>
                </header>

                <div class="shipment-show-address-list">
                    <article>
                        <span class="shipment-show-address-marker">
                            O
                        </span>

                        <div>
                            <span class="shipment-show-label">
                                Origen
                            </span>

                            <strong>
                                {{ $shipment
                                    ->originAddress
                                    ->address_line }}
                            </strong>

                            <span>
                                {{ $shipment
                                    ->originAddress
                                    ->municipality
                                    ?->municipality_name
                                    ?? 'Municipio no disponible' }}
                            </span>

                            <small>
                                {{ $shipment
                                    ->originAddress
                                    ->reference_note
                                    ?? 'Sin referencia adicional' }}
                            </small>
                        </div>
                    </article>

                    <article>
                        <span
                            class="
                                shipment-show-address-marker
                                shipment-show-address-marker-destination
                            "
                        >
                            D
                        </span>

                        <div>
                            <span class="shipment-show-label">
                                Destino
                            </span>

                            <strong>
                                {{ $shipment
                                    ->destinationAddress
                                    ->address_line }}
                            </strong>

                            <span>
                                {{ $shipment
                                    ->destinationAddress
                                    ->municipality
                                    ?->municipality_name
                                    ?? 'Municipio no disponible' }}
                            </span>

                            <small>
                                {{ $shipment
                                    ->destinationAddress
                                    ->reference_note
                                    ?? 'Sin referencia adicional' }}
                            </small>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <section class="shipment-show-card">
            <header>
                <p class="shipment-show-eyebrow">
                    Cronología
                </p>

                <h2>
                    Fechas del envío
                </h2>
            </header>

            <div class="shipment-show-date-grid">
                <article>
                    <span>Solicitud</span>

                    <strong>
                        {{ $shipment->requested_at
                            ?->format('d/m/Y H:i')
                            ?? 'Sin fecha' }}
                    </strong>
                </article>

                <article>
                    <span>Programado</span>

                    <strong>
                        {{ $shipment->scheduled_at
                            ?->format('d/m/Y H:i')
                            ?? 'Sin programar' }}
                    </strong>
                </article>

                <article>
                    <span>Entrega estimada</span>

                    <strong>
                        {{ $shipment
                            ->estimated_delivery_at
                            ?->format('d/m/Y H:i')
                            ?? 'Sin estimación' }}
                    </strong>
                </article>

                <article>
                    <span>Entregado</span>

                    <strong>
                        {{ $shipment->delivered_at
                            ?->format('d/m/Y H:i')
                            ?? 'Pendiente' }}
                    </strong>
                </article>
            </div>
        </section>

        <section class="shipment-show-card">
            <header>
                <p class="shipment-show-eyebrow">
                    Contenido
                </p>

                <h2>
                    Paquetes
                </h2>
            </header>

            @if ($shipment->packages->isEmpty())
                <div class="shipment-show-empty">
                    Este envío no tiene paquetes registrados.
                </div>
            @else
                <div class="shipment-show-package-grid">
                    @foreach ($shipment->packages as $package)
                        <article class="shipment-show-package">
                            <header>
                                <span>
                                    Paquete {{ $loop->iteration }}
                                </span>

                                @if ($package->is_fragile)
                                    <strong>
                                        Frágil
                                    </strong>
                                @endif
                            </header>

                            <p>
                                {{ $package
                                    ->content_description
                                    ?? 'Sin descripción' }}
                            </p>

                            <dl>
                                <div>
                                    <dt>Peso</dt>

                                    <dd>
                                        {{ $package->weight
                                            !== null
                                                ? $package->weight.' kg'
                                                : 'No indicado' }}
                                    </dd>
                                </div>

                                <div>
                                    <dt>Alto</dt>

                                    <dd>
                                        {{ $package->height
                                            !== null
                                                ? $package->height.' cm'
                                                : 'No indicado' }}
                                    </dd>
                                </div>

                                <div>
                                    <dt>Ancho</dt>

                                    <dd>
                                        {{ $package->width
                                            !== null
                                                ? $package->width.' cm'
                                                : 'No indicado' }}
                                    </dd>
                                </div>

                                <div>
                                    <dt>Largo</dt>

                                    <dd>
                                        {{ $package->length
                                            !== null
                                                ? $package->length.' cm'
                                                : 'No indicado' }}
                                    </dd>
                                </div>

                                <div>
                                    <dt>Valor</dt>

                                    <dd>
                                        {{ $package->declared_value
                                            !== null
                                                ? 'C$ '.number_format(
                                                    (float) $package
                                                        ->declared_value,
                                                    2
                                                )
                                                : 'No declarado' }}
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="shipment-show-grid">
            <section class="shipment-show-card">
                <header>
                    <p class="shipment-show-eyebrow">
                        Servicio
                    </p>

                    <h2>
                        Servicio de entrega
                    </h2>
                </header>

                @if ($shipment->deliveryService)
                    <dl class="shipment-show-description-list">
                        <div>
                            <dt>Estado</dt>

                            <dd>
                                {{ str_replace(
                                    '_',
                                    ' ',
                                    $shipment
                                        ->deliveryService
                                        ->status
                                ) }}
                            </dd>
                        </div>

                        <div>
                            <dt>Tipo de servicio</dt>

                            <dd>
                                {{ $shipment
                                    ->deliveryService
                                    ->serviceType
                                    ?->service_name
                                    ?? 'No asignado' }}
                            </dd>
                        </div>

                        <div>
                            <dt>Tipo de viaje</dt>

                            <dd>
                                {{ $shipment
                                    ->deliveryService
                                    ->tripType
                                    ?->type_name
                                    ?? 'No asignado' }}
                            </dd>
                        </div>

                        <div>
                            <dt>Tarifa</dt>

                            <dd>
                                {{ $shipment
                                    ->deliveryService
                                    ->delivery_fee
                                    !== null
                                        ? 'C$ '.number_format(
                                            (float) $shipment
                                                ->deliveryService
                                                ->delivery_fee,
                                            2
                                        )
                                        : 'No calculada' }}
                            </dd>
                        </div>

                        <div>
                            <dt>Proveedor</dt>

                            <dd>
                                {{ $shipment
                                    ->deliveryService
                                    ->trip
                                    ?->deliveryProvider
                                    ?->business_name
                                    ?? 'No asignado' }}
                            </dd>
                        </div>
                    </dl>
                @else
                    <div class="shipment-show-empty">
                        El envío todavía no tiene un servicio
                        de entrega asignado.
                    </div>
                @endif
            </section>

            <section class="shipment-show-card">
                <header>
                    <p class="shipment-show-eyebrow">
                        Rutas
                    </p>

                    <h2>
                        Asignaciones de ruta
                    </h2>
                </header>

                @if ($shipment->routeShipments->isEmpty())
                    <div class="shipment-show-empty">
                        El envío todavía no está asignado
                        a una ruta.
                    </div>
                @else
                    <div class="shipment-show-route-list">
                        @foreach (
                            $shipment->routeShipments
                            as $routeShipment
                        )
                            <article>
                                <strong>
                                    Ruta
                                    #{{ $routeShipment->route_id }}
                                </strong>

                                <span>
                                    Orden:
                                    {{ $routeShipment
                                        ->delivery_order }}
                                </span>

                                <span>
                                    Entrega:
                                    {{ str_replace(
                                        '_',
                                        ' ',
                                        $routeShipment
                                            ->delivery_status
                                    ) }}
                                </span>

                                <span>
                                    Ruta:
                                    {{ $routeShipment
                                        ->route
                                        ->routeStatus
                                        ?->status_name
                                        ?? 'Sin estado' }}
                                </span>

                                <span>
                                    Repartidor:
                                    {{ $routeShipment
                                        ->route
                                        ->courier
                                        ?->user
                                        ?->name
                                        ?? 'No asignado' }}
                                </span>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <section class="shipment-show-card">
            <header>
                <p class="shipment-show-eyebrow">
                    Trazabilidad
                </p>

                <h2>
                    Historial de estados
                </h2>
            </header>

            @if ($shipment->statusHistory->isEmpty())
                <div class="shipment-show-empty">
                    No hay cambios de estado registrados.
                </div>
            @else
                <div class="shipment-show-history">
                    @foreach (
                        $shipment
                            ->statusHistory
                            ->sortByDesc('changed_at')
                        as $history
                    )
                        <article>
                            <span class="shipment-show-history-dot">
                            </span>

                            <div>
                                <strong>
                                    {{ str_replace(
                                        '_',
                                        ' ',
                                        $history
                                            ->shipmentStatus
                                            ->status_name
                                    ) }}
                                </strong>

                                <span>
                                    {{ $history->changed_at
                                        ?->format('d/m/Y H:i')
                                        ?? 'Sin fecha' }}
                                </span>

                                <small>
                                    {{ $history
                                        ->changedBy
                                        ?->name
                                        ?? 'Sistema' }}
                                </small>

                                @if ($history->comment)
                                    <p>
                                        {{ $history->comment }}
                                    </p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="shipment-show-card">
            <header>
                <p class="shipment-show-eyebrow">
                    Confirmación
                </p>

                <h2>
                    Comprobante de entrega
                </h2>
            </header>

            @if ($shipment->deliveryProof)
                <dl class="shipment-show-description-list">
                    <div>
                        <dt>Recibido por</dt>

                        <dd>
                            {{ $shipment
                                ->deliveryProof
                                ->receiver_name }}
                        </dd>
                    </div>

                    <div>
                        <dt>Identificación</dt>

                        <dd>
                            {{ $shipment
                                ->deliveryProof
                                ->receiver_identity_number
                                ?? 'No registrada' }}
                        </dd>
                    </div>

                    <div>
                        <dt>Registrado</dt>

                        <dd>
                            {{ $shipment
                                ->deliveryProof
                                ->recorded_at
                                ?->format('d/m/Y H:i')
                                ?? 'Sin fecha' }}
                        </dd>
                    </div>

                    <div>
                        <dt>Coordenadas</dt>

                        <dd>
                            @if (
                                $shipment
                                    ->deliveryProof
                                    ->latitude !== null
                                && $shipment
                                    ->deliveryProof
                                    ->longitude !== null
                            )
                                {{ $shipment
                                    ->deliveryProof
                                    ->latitude }},
                                {{ $shipment
                                    ->deliveryProof
                                    ->longitude }}
                            @else
                                No registradas
                            @endif
                        </dd>
                    </div>
                </dl>
            @else
                <div class="shipment-show-empty">
                    Todavía no existe un comprobante
                    de entrega.
                </div>
            @endif
        </section>

        @if (
            $shipment->delivery_instructions
            || $shipment->notes
        )
            <section class="shipment-show-card">
                <header>
                    <p class="shipment-show-eyebrow">
                        Información adicional
                    </p>

                    <h2>
                        Indicaciones y notas
                    </h2>
                </header>

                <div class="shipment-show-notes">
                    <article>
                        <strong>
                            Instrucciones de entrega
                        </strong>

                        <p>
                            {{ $shipment
                                ->delivery_instructions
                                ?? 'Sin instrucciones' }}
                        </p>
                    </article>

                    <article>
                        <strong>Notas</strong>

                        <p>
                            {{ $shipment->notes
                                ?? 'Sin notas' }}
                        </p>
                    </article>
                </div>
            </section>
        @endif
    </section>
@endsection