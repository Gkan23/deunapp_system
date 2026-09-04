@extends('layouts.portal')

@section('title', 'Tickets de soporte | DeUnapp')

@section('content')
    <section class="support-ticket-heading">
        <div>
            <p class="portal-eyebrow">
                Centro de ayuda
            </p>

            <h1>Tickets de soporte</h1>

            <p>
                Consulta las solicitudes de soporte
                disponibles para tu cuenta.
            </p>
        </div>

        <div class="support-ticket-heading-actions">
            @can('create', \App\Models\SupportTicket::class)
                <a
                    href="{{ route('portal.support-tickets.create') }}"
                    class="support-ticket-primary-button"
                >
                    Nuevo ticket
                </a>
            @endcan

            <a
                href="{{ route('dashboard') }}"
                class="support-ticket-secondary-button"
            >
                Volver al panel
            </a>
        </div>
    </section>

    @if (session('status'))
        <div
            class="support-ticket-alert support-ticket-alert-success"
            role="status"
        >
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div
            class="support-ticket-alert support-ticket-alert-error"
            role="alert"
        >
            <strong>
                No fue posible completar la operación.
            </strong>

            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="support-ticket-summary">
        <article class="support-ticket-summary-card">
            <span>Total</span>
            <strong>{{ $totalTickets }}</strong>
            <small>Tickets visibles</small>
        </article>

        <article class="support-ticket-summary-card">
            <span>En proceso</span>
            <strong>{{ $openTickets }}</strong>
            <small>Requieren seguimiento</small>
        </article>

        <article class="support-ticket-summary-card">
            <span>Finalizados</span>
            <strong>{{ $closedTickets }}</strong>
            <small>Resueltos o cerrados</small>
        </article>
    </section>

    <section class="support-ticket-toolbar">
        <form
            method="GET"
            action="{{ route('portal.support-tickets.index') }}"
            class="support-ticket-filters"
        >
            <div
                class="support-ticket-field support-ticket-search-field"
            >
                <label for="search">Buscar</label>

                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Asunto, guía, cliente o correo"
                >
            </div>

            <div class="support-ticket-field">
                <label for="status">Estado</label>

                <select id="status" name="status">
                    <option value="">Todos</option>

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

            <div class="support-ticket-field">
                <label for="category">Categoría</label>

                <select id="category" name="category">
                    <option value="">Todas</option>

                    @foreach ($categories as $category)
                        <option
                            value="{{ $category->category_name }}"
                            @selected(
                                $selectedCategory
                                === $category->category_name
                            )
                        >
                            {{ str_replace(
                                '_',
                                ' ',
                                $category->category_name
                            ) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="support-ticket-filter-actions">
                <button
                    type="submit"
                    class="support-ticket-primary-button"
                >
                    Aplicar
                </button>

                @if (
                    $search !== ''
                    || $selectedStatus !== ''
                    || $selectedCategory !== ''
                )
                    <a
                        href="{{ route('portal.support-tickets.index') }}"
                        class="support-ticket-clear-link"
                    >
                        Limpiar
                    </a>
                @endif
            </div>
        </form>
    </section>

    <section class="support-ticket-results">
        @if ($tickets->isEmpty())
            <div class="support-ticket-empty">
                <span aria-hidden="true">?</span>

                <h2>No se encontraron tickets</h2>

                <p>
                    No hay solicitudes que coincidan
                    con los filtros seleccionados.
                </p>
            </div>
        @else
            <div class="support-ticket-list">
                @foreach ($tickets as $ticket)
                    @php
                        $statusName = $ticket
                            ->status
                            ?->status_name ?? 'UNKNOWN';

                        $statusClass = strtolower(
                            str_replace('_', '-', $statusName)
                        );
                    @endphp

                    <article class="support-ticket-card">
                        <header class="support-ticket-card-header">
                            <div>
                                <span class="support-ticket-number">
                                    Ticket #{{ $ticket->id }}
                                </span>

                                <h2>{{ $ticket->subject }}</h2>
                            </div>

                            <span
                                class="support-ticket-status support-ticket-status-{{ $statusClass }}"
                            >
                                {{ str_replace('_', ' ', $statusName) }}
                            </span>
                        </header>

                        <div class="support-ticket-card-grid">
                            <div>
                                <span>Categoría</span>

                                <strong>
                                    {{ str_replace(
                                        '_',
                                        ' ',
                                        $ticket->category?->category_name
                                            ?? 'Sin categoría'
                                    ) }}
                                </strong>
                            </div>

                            <div>
                                <span>Prioridad</span>

                                <strong>
                                    {{ str_replace(
                                        '_',
                                        ' ',
                                        $ticket->priority?->priority_name
                                            ?? 'Sin prioridad'
                                    ) }}
                                </strong>
                            </div>

                            <div>
                                <span>Mensajes</span>
                                <strong>{{ $ticket->messages_count }}</strong>
                            </div>

                            <div>
                                <span>Creado</span>

                                <strong>
                                    {{ $ticket->created_at?->format('d/m/Y H:i') }}
                                </strong>
                            </div>
                        </div>

                        @if ($roleName !== 'CUSTOMER')
                            <div class="support-ticket-person">
                                <span>Cliente</span>

                                <strong>
                                    {{ $ticket->customer?->user?->name
                                        ?? 'No disponible' }}
                                </strong>

                                <small>
                                    {{ $ticket->customer?->user?->email }}
                                </small>
                            </div>
                        @endif

                        <footer class="support-ticket-card-footer">
                            <div class="support-ticket-card-details">
                                <div>
                                    @if ($ticket->shipment)
                                        <span>
                                            Envío:
                                            <strong>
                                                {{ $ticket->shipment->tracking_code }}
                                            </strong>
                                        </span>
                                    @else
                                        <span>Sin envío relacionado</span>
                                    @endif
                                </div>

                                <div>
                                    @if ($ticket->assignedTo)
                                        <span>
                                            Asignado a
                                            <strong>
                                                {{ $ticket->assignedTo->name }}
                                            </strong>
                                        </span>
                                    @else
                                        <span>Sin asignar</span>
                                    @endif
                                </div>
                            </div>

                            <a
                                href="{{ route(
                                    'portal.support-tickets.show',
                                    $ticket
                                ) }}"
                                class="support-ticket-primary-button"
                            >
                                Ver ticket
                            </a>
                        </footer>
                    </article>
                @endforeach
            </div>

            @if ($tickets->hasPages())
                <div class="support-ticket-pagination">
                    {{ $tickets->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection