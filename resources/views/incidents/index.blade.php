@extends('layouts.portal')

@section('title', 'Incidentes | DeUnapp')

@section('content')
    <section class="incident-index-heading">
        <div>
            <p class="portal-eyebrow">
                Seguimiento de entregas
            </p>

            <h1>Incidentes</h1>

            <p>
                Consulta los incidentes relacionados
                con los envíos que puedes visualizar.
            </p>
        </div>

        <a
            href="{{ route('dashboard') }}"
            class="incident-index-secondary-button"
        >
            Volver al panel
        </a>
    </section>

    @if (session('status'))
        <div
            class="incident-index-notice"
            role="status"
        >
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div
            class="incident-index-alert"
            role="alert"
        >
            <strong>
                Revisa los filtros seleccionados.
            </strong>

            <ul>
                @foreach ($errors->all() as $error)
                    <li>
                        {{ $error }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <section
        class="incident-index-summary"
        aria-label="Resumen de incidentes visibles"
    >
        <article class="incident-index-summary-card">
            <span>Total</span>

            <strong>
                {{ $totalIncidents }}
            </strong>

            <small>
                Incidentes visibles
            </small>
        </article>

        <article class="incident-index-summary-card">
            <span>Pendientes</span>

            <strong>
                {{ $pendingIncidents }}
            </strong>

            <small>
                Abiertos o en revisión
            </small>
        </article>

        <article class="incident-index-summary-card">
            <span>Finalizados</span>

            <strong>
                {{ $finishedIncidents }}
            </strong>

            <small>
                Resueltos o cerrados
            </small>
        </article>
    </section>

    <section class="incident-index-toolbar">
        <form
            method="GET"
            action="{{ route('portal.incidents.index') }}"
            class="incident-index-filters"
        >
            <div
                class="incident-index-field incident-index-search-field"
            >
                <label for="search">
                    Buscar
                </label>

                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    maxlength="200"
                    placeholder="Descripción o código de envío"
                >
            </div>

            <div class="incident-index-field">
                <label for="status">
                    Estado
                </label>

                <select
                    id="status"
                    name="status"
                >
                    <option value="">
                        Todos
                    </option>

                    @foreach ($statuses as $status)
                        <option
                            value="{{ $status->status_name }}"
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

            <div class="incident-index-field">
                <label for="type">
                    Tipo
                </label>

                <select
                    id="type"
                    name="type"
                >
                    <option value="">
                        Todos
                    </option>

                    @foreach ($types as $type)
                        <option
                            value="{{ $type->type_name }}"
                            @selected(
                                $selectedType
                                === $type->type_name
                            )
                        >
                            {{ str_replace(
                                '_',
                                ' ',
                                $type->type_name
                            ) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="incident-index-filter-actions">
                <button
                    type="submit"
                    class="incident-index-primary-button"
                >
                    Aplicar
                </button>

                @if (
                    $search !== ''
                    || $selectedStatus !== ''
                    || $selectedType !== ''
                )
                    <a
                        href="{{ route('portal.incidents.index') }}"
                        class="incident-index-clear-link"
                    >
                        Limpiar
                    </a>
                @endif
            </div>
        </form>
    </section>

    <section class="incident-index-results">
        <p class="incident-index-result-count">
            {{ $incidents->total() }}

            {{ $incidents->total() === 1
                ? 'resultado'
                : 'resultados' }}
        </p>

        @if ($incidents->isEmpty())
            <div class="incident-index-empty">
                <h2>
                    No se encontraron incidentes
                </h2>

                <p>
                    No hay incidentes visibles que coincidan
                    con los filtros seleccionados.
                </p>
            </div>
        @else
            <div class="incident-index-list">
                @foreach ($incidents as $incident)
                    @php
                        $statusName = $incident
                            ->incidentStatus
                            ?->status_name ?? 'UNKNOWN';

                        $statusClass = strtolower(
                            str_replace(
                                '_',
                                '-',
                                $statusName
                            )
                        );
                    @endphp

                    <article class="incident-index-card">
                        <header class="incident-index-card-header">
                            <div>
                                <span class="incident-index-number">
                                    Incidente #{{ $incident->id }}
                                </span>

                                <h2>
                                    {{ str_replace(
                                        '_',
                                        ' ',
                                        $incident->incidentType?->type_name
                                            ?? 'Sin tipo'
                                    ) }}
                                </h2>
                            </div>

                            <span
                                class="incident-index-status incident-index-status-{{ $statusClass }}"
                            >
                                {{ str_replace(
                                    '_',
                                    ' ',
                                    $statusName
                                ) }}
                            </span>
                        </header>

                        <p class="incident-index-description">{{ $incident->description }}</p>

                        <dl class="incident-index-details">
                            <div>
                                <dt>
                                    Envío
                                </dt>

                                <dd>
                                    {{ $incident->shipment?->tracking_code
                                        ?? 'No disponible' }}
                                </dd>
                            </div>

                            <div>
                                <dt>
                                    Reportado por
                                </dt>

                                <dd>
                                    {{ $incident->reportedBy?->name
                                        ?? 'No disponible' }}
                                </dd>
                            </div>

                            <div>
                                <dt>
                                    Fecha del reporte
                                </dt>

                                <dd>
                                    {{ $incident->reported_at?->format('d/m/Y H:i')
                                        ?? 'No disponible' }}
                                </dd>
                            </div>
                        </dl>

                        <footer class="incident-index-card-actions">
                            <a
                                href="{{ route(
                                    'portal.incidents.show',
                                    $incident
                                ) }}"
                                class="incident-index-primary-button"
                                aria-label="Ver incidente número {{ $incident->id }}"
                            >
                                Ver incidente
                            </a>
                        </footer>
                    </article>
                @endforeach
            </div>

            @if ($incidents->hasPages())
                <div class="incident-index-pagination">
                    {{ $incidents->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection