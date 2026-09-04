@extends('layouts.portal')

@section('title', 'Detalle del incidente | DeUnapp')

@section('content')
    @php
        $statusClass = strtolower(
            str_replace('_', '-', $currentStatusName)
        );
    @endphp

    <section class="incident-show-heading">
        <div>
            <p class="portal-eyebrow">
                Seguimiento de entregas
            </p>

            <h1>Incidente #{{ $incident->id }}</h1>

            <p>
                Consulta la información del reporte
                y su estado actual.
            </p>
        </div>

        <a
            href="{{ route('portal.incidents.index') }}"
            class="incident-show-secondary-button"
        >
            Volver a incidentes
        </a>
    </section>

    @if (session('status'))
        <div
            class="incident-show-alert incident-show-alert-success"
            role="status"
        >
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div
            class="incident-show-alert incident-show-alert-error"
            role="alert"
        >
            <strong>
                No fue posible actualizar el incidente.
            </strong>

            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="incident-show-layout">
        <section class="incident-show-card">
            <header class="incident-show-card-header">
                <div>
                    <span class="incident-show-label">
                        Tipo de incidente
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
                    class="incident-show-status incident-show-status-{{ $statusClass }}"
                >
                    {{ str_replace('_', ' ', $currentStatusName) }}
                </span>
            </header>

            <dl class="incident-show-details">
                <div>
                    <dt>Envío relacionado</dt>

                    <dd>
                        {{ $incident->shipment?->tracking_code
                            ?? 'No disponible' }}
                    </dd>
                </div>

                <div>
                    <dt>Reportado por</dt>

                    <dd>
                        {{ $incident->reportedBy?->name
                            ?? 'No disponible' }}
                    </dd>
                </div>

                <div>
                    <dt>Fecha del reporte</dt>

                    <dd>
                        {{ $incident->reported_at?->format('d/m/Y H:i')
                            ?? 'No disponible' }}
                    </dd>
                </div>
            </dl>

            <section class="incident-show-description-section">
                <h3>Descripción</h3>

                <p class="incident-show-description">{{ $incident->description }}</p>
            </section>
        </section>

        <aside class="incident-show-card">
            <h2>Estado del incidente</h2>

            @if ($canManageStatus)
                @if ($availableStatuses->isNotEmpty())
                    <p class="incident-show-help">
                        Selecciona uno de los cambios disponibles.
                        El comentario es obligatorio al resolver
                        o reabrir el incidente.
                    </p>

                    <form
                        method="POST"
                        action="{{ route(
                            'portal.incidents.status.update',
                            $incident
                        ) }}"
                        class="incident-show-form"
                    >
                        @csrf
                        @method('PATCH')

                        <div class="incident-show-field">
                            <label for="status">
                                Nuevo estado *
                            </label>

                            <select
                                id="status"
                                name="status"
                                required
                                aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}"
                            >
                                <option value="">
                                    Selecciona un estado
                                </option>

                                @foreach ($availableStatuses as $status)
                                    <option
                                        value="{{ $status->status_name }}"
                                        @selected(
                                            old('status')
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

                            @error('status')
                                <span class="incident-show-field-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="incident-show-field">
                            <label for="comment">
                                Comentario
                            </label>

                            <textarea
                                id="comment"
                                name="comment"
                                rows="6"
                                maxlength="2000"
                                aria-describedby="incident-comment-help"
                                aria-invalid="{{ $errors->has('comment') ? 'true' : 'false' }}"
                            >{{ old('comment') }}</textarea>

                            <small id="incident-comment-help">
                                Máximo 2000 caracteres.
                                El comentario se registra en la auditoría.
                            </small>

                            @error('comment')
                                <span class="incident-show-field-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="incident-show-primary-button"
                        >
                            Actualizar estado
                        </button>
                    </form>
                @else
                    <p class="incident-show-help">
                        No hay cambios de estado disponibles
                        para este incidente.
                    </p>

                    @if ($currentStatusName === 'CLOSED')
                        <div class="incident-show-notice">
                            El incidente está cerrado
                            y no admite más cambios.
                        </div>
                    @endif
                @endif
            @else
                <p class="incident-show-help">
                    Puedes consultar el reporte y su estado actual.
                    Los cambios de estado corresponden a soporte
                    y administración.
                </p>
            @endif
        </aside>
    </div>
@endsection