@extends('layouts.portal')

@section('title', 'Envíos | DeUnapp')

@section('content')
    <section class="shipment-index-page">
        <header class="shipment-index-header">
            <div>
                <p class="shipment-index-eyebrow">
                    Operaciones
                </p>

                <h1>Envíos</h1>

                <p>
                    Consulta los envíos disponibles para
                    tu cuenta y su estado actual.
                </p>
            </div>

            <div class="shipment-index-header-actions">
                @can(
                    'create',
                    \App\Models\Shipment::class
                )
                    <a
                        href="{{ route(
                            'portal.shipments.create'
                        ) }}"
                        class="shipment-index-create-link"
                    >
                        Registrar envío
                    </a>
                @endcan

                <a
                    href="{{ route('dashboard') }}"
                    class="shipment-index-back-link"
                >
                    Volver al panel
                </a>
            </div>
        </header>

        <section class="shipment-index-toolbar">
            <form
                method="GET"
                action="{{ route(
                    'portal.shipments.index'
                ) }}"
                class="shipment-index-filters"
            >
                <div class="shipment-index-field">
                    <label for="search">
                        Buscar
                    </label>

                    <input
                        id="search"
                        name="search"
                        type="search"
                        value="{{ $search }}"
                        placeholder="Código, persona o dirección"
                        maxlength="255"
                    >
                </div>

                <div class="shipment-index-field">
                    <label for="status">
                        Estado
                    </label>

                    <select
                        id="status"
                        name="status"
                    >
                        <option value="">
                            Todos los estados
                        </option>

                        @foreach ($statuses as $status)
                            <option
                                value="{{ $status
                                    ->status_name }}"
                                @selected(
                                    $selectedStatus
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

                <div class="shipment-index-filter-actions">
                    <button type="submit">
                        Filtrar
                    </button>

                    @if (
                        $search !== ''
                        || $selectedStatus !== ''
                    )
                        <a
                            href="{{ route(
                                'portal.shipments.index'
                            ) }}"
                        >
                            Limpiar
                        </a>
                    @endif
                </div>
            </form>

            <div class="shipment-index-count">
                <strong>
                    {{ $shipments->total() }}
                </strong>

                <span>
                    {{ $shipments->total() === 1
                        ? 'envío encontrado'
                        : 'envíos encontrados' }}
                </span>
            </div>
        </section>

        @if ($shipments->isEmpty())
            <section class="shipment-index-empty">
                <span aria-hidden="true">
                    DU
                </span>

                <h2>
                    No se encontraron envíos
                </h2>

                <p>
                    No hay registros que coincidan con
                    los filtros seleccionados.
                </p>
            </section>
        @else
            <div class="shipment-index-table-wrapper">
                <table class="shipment-index-table">
                    <thead>
                        <tr>
                            <th>Seguimiento</th>
                            <th>Estado</th>
                            <th>Remitente</th>
                            <th>Destinatario</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Solicitud</th>
                            <th>Valor</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($shipments as $shipment)
                            <tr>
                                <td data-label="Seguimiento">
                                    <strong
                                        class="
                                            shipment-tracking-code
                                        "
                                    >
                                        {{ $shipment
                                            ->tracking_code }}
                                    </strong>
                                </td>

                                <td data-label="Estado">
                                    <span
                                        class="
                                            shipment-status-badge
                                        "
                                    >
                                        {{ str_replace(
                                            '_',
                                            ' ',
                                            $shipment
                                                ->shipmentStatus
                                                ->status_name
                                        ) }}
                                    </span>
                                </td>

                                <td data-label="Remitente">
                                    <strong>
                                        {{ $shipment
                                            ->sender
                                            ->first_name }}

                                        {{ $shipment
                                            ->sender
                                            ->last_name }}
                                    </strong>

                                    <small>
                                        {{ $shipment
                                            ->sender
                                            ->phone }}
                                    </small>
                                </td>

                                <td data-label="Destinatario">
                                    <strong>
                                        {{ $shipment
                                            ->recipient
                                            ->first_name }}

                                        {{ $shipment
                                            ->recipient
                                            ->last_name }}
                                    </strong>

                                    <small>
                                        {{ $shipment
                                            ->recipient
                                            ->phone }}
                                    </small>
                                </td>

                                <td data-label="Origen">
                                    <span>
                                        {{ $shipment
                                            ->originAddress
                                            ->address_line }}
                                    </span>
                                </td>

                                <td data-label="Destino">
                                    <span>
                                        {{ $shipment
                                            ->destinationAddress
                                            ->address_line }}
                                    </span>
                                </td>

                                <td data-label="Solicitud">
                                    <span>
                                        {{ $shipment
                                            ->requested_at
                                            ?->format(
                                                'd/m/Y H:i'
                                            )
                                            ?? 'Sin fecha' }}
                                    </span>
                                </td>

                                <td data-label="Valor">
                                    <span>
                                        {{ $shipment
                                            ->declared_value
                                            !== null
                                                ? 'C$ '.number_format(
                                                    (float) $shipment
                                                        ->declared_value,
                                                    2
                                                )
                                                : 'No declarado' }}
                                    </span>
                                </td>

                                <td data-label="Acciones">
                                    <a
                                        href="{{ route(
                                            'portal.shipments.show',
                                            $shipment
                                        ) }}"
                                        class="
                                            shipment-index-detail-link
                                        "
                                    >
                                        Ver detalle
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($shipments->hasPages())
                <div class="shipment-index-pagination">
                    {{ $shipments->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection