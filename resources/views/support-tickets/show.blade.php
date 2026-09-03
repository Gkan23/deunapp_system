@extends('layouts.portal')

@section('title', 'Ticket #'.$supportTicket->id.' | DeUnapp')

@section('content')
    @php
        $statusName = $supportTicket->status
            ?->status_name ?? 'UNKNOWN';

        $statusClass = strtolower(
            str_replace('_', '-', $statusName)
        );
    @endphp

    <section class="support-show-heading">
        <div>
            <p class="portal-eyebrow">
                Centro de ayuda
            </p>

            <h1>
                Ticket #{{ $supportTicket->id }}
            </h1>

            <p>
                {{ $supportTicket->subject }}
            </p>
        </div>

        <a
            href="{{ route(
                'portal.support-tickets.index'
            ) }}"
            class="support-show-secondary-button"
        >
            Volver a los tickets
        </a>
    </section>

    @if (session('status'))
        <div
            class="support-show-alert
                support-show-alert-success"
            role="status"
        >
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div
            class="support-show-alert
                support-show-alert-error"
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

    <section class="support-show-layout">
        <div class="support-show-main">
            <article class="support-show-ticket-card">
                <header class="support-show-ticket-header">
                    <div>
                        <span>Asunto</span>
                        <h2>
                            {{ $supportTicket->subject }}
                        </h2>
                    </div>

                    <span
                        class="support-show-status
                            support-show-status-{{ $statusClass }}"
                    >
                        {{ str_replace(
                            '_',
                            ' ',
                            $statusName
                        ) }}
                    </span>
                </header>

                <div class="support-show-information-grid">
                    <div>
                        <span>Categoría</span>

                        <strong>
                            {{ str_replace(
                                '_',
                                ' ',
                                $supportTicket->category
                                    ?->category_name
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
                                $supportTicket->priority
                                    ?->priority_name
                                    ?? 'Sin prioridad'
                            ) }}
                        </strong>
                    </div>

                    <div>
                        <span>Creado</span>

                        <strong>
                            {{ $supportTicket->created_at
                                ?->format('d/m/Y H:i') }}
                        </strong>
                    </div>

                    <div>
                        <span>Cerrado</span>

                        <strong>
                            {{ $supportTicket->closed_at
                                ?->format('d/m/Y H:i')
                                ?? 'No' }}
                        </strong>
                    </div>
                </div>

                <div class="support-show-participants">
                    <div>
                        <span>Cliente</span>

                        <strong>
                            {{ $supportTicket->customer
                                ?->user
                                ?->name
                                ?? 'No disponible' }}
                        </strong>

                        <small>
                            {{ $supportTicket->customer
                                ?->user
                                ?->email }}
                        </small>
                    </div>

                    <div>
                        <span>Asignado a</span>

                        <strong>
                            {{ $supportTicket->assignedTo
                                ?->name
                                ?? 'Sin asignar' }}
                        </strong>

                        @if ($supportTicket->assignedTo)
                            <small>
                                {{ str_replace(
                                    '_',
                                    ' ',
                                    $supportTicket
                                        ->assignedTo
                                        ->role
                                        ?->role_name
                                        ?? ''
                                ) }}
                            </small>
                        @endif
                    </div>

                    <div>
                        <span>Envío relacionado</span>

                        <strong>
                            {{ $supportTicket->shipment
                                ?->tracking_code
                                ?? 'Ninguno' }}
                        </strong>
                    </div>
                </div>
            </article>

            <section class="support-show-conversation">
                <header class="support-show-section-header">
                    <div>
                        <h2>Conversación</h2>

                        <p>
                            {{ $supportTicket
                                ->messages
                                ->count() }}
                            mensajes
                        </p>
                    </div>

                    @if ($canMarkMessagesAsRead)
                        <form
                            method="POST"
                            action="{{ route(
                                'portal.support-tickets.messages.read',
                                $supportTicket
                            ) }}"
                        >
                            @csrf
                            @method('PATCH')

                            <button
                                type="submit"
                                class="support-show-read-button"
                            >
                                Marcar
                                {{ $unreadMessageCount }}
                                como leído
                            </button>
                        </form>
                    @endif
                </header>

                @if ($supportTicket->messages->isEmpty())
                    <div class="support-show-empty">
                        No hay mensajes en este ticket.
                    </div>
                @else
                    <div class="support-show-message-list">
                        @foreach (
                            $supportTicket->messages
                            as $message
                        )
                            @php
                                $isOwnMessage = (
                                    (int) $message->user_id
                                    === (int) $user->id
                                );
                            @endphp

                            <article
                                class="support-show-message
                                    {{ $isOwnMessage
                                        ? 'support-show-message-own'
                                        : '' }}"
                            >
                                <header>
                                    <div>
                                        <strong>
                                            {{ $message->user
                                                ?->name
                                                ?? 'Usuario' }}
                                        </strong>

                                        <span>
                                            {{ str_replace(
                                                '_',
                                                ' ',
                                                $message
                                                    ->user
                                                    ?->role
                                                    ?->role_name
                                                    ?? ''
                                            ) }}
                                        </span>
                                    </div>

                                    <time>
                                        {{ $message->sent_at
                                            ?->format(
                                                'd/m/Y H:i'
                                            ) }}
                                    </time>
                                </header>

                                <p>
                                    {{ $message->message_text }}
                                </p>

                                @if ($message->attachment_url)
                                    <a
                                        href="{{ $message
                                            ->attachment_url }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="support-show-attachment"
                                    >
                                        Abrir archivo adjunto
                                    </a>
                                @endif

                                <footer>
                                    {{ $message->is_read
                                        ? 'Leído'
                                        : 'No leído' }}
                                </footer>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($canReply)
                <section class="support-show-form-card">
                    <h2>Responder</h2>

                    <form
                        method="POST"
                        action="{{ route(
                            'portal.support-tickets.messages.store',
                            $supportTicket
                        ) }}"
                        class="support-show-form"
                    >
                        @csrf

                        <div class="support-show-field">
                            <label for="message">
                                Mensaje
                            </label>

                            <textarea
                                id="message"
                                name="message"
                                rows="5"
                                required
                            >{{ old('message') }}</textarea>

                            @error('message')
                                <span>
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="support-show-field">
                            <label for="attachment_url">
                                URL del archivo adjunto
                            </label>

                            <input
                                id="attachment_url"
                                name="attachment_url"
                                type="url"
                                maxlength="500"
                                value="{{ old(
                                    'attachment_url'
                                ) }}"
                                placeholder="https://..."
                            >

                            @error('attachment_url')
                                <span>
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="support-show-primary-button"
                        >
                            Enviar mensaje
                        </button>
                    </form>
                </section>
            @endif
        </div>

        <aside class="support-show-sidebar">
            @if (
                $canAssign
                && $supportUsers->isNotEmpty()
            )
                <section class="support-show-action-card">
                    <h2>
                        {{ $supportTicket
                            ->assigned_to_user_id
                            ? 'Reasignar ticket'
                            : 'Asignar ticket' }}
                    </h2>

                    <form
                        method="POST"
                        action="{{ route(
                            'portal.support-tickets.assign',
                            $supportTicket
                        ) }}"
                        class="support-show-form"
                    >
                        @csrf
                        @method('PATCH')

                        <div class="support-show-field">
                            <label for="assigned_to_user_id">
                                Usuario de soporte
                            </label>

                            <select
                                id="assigned_to_user_id"
                                name="assigned_to_user_id"
                                required
                            >
                                <option value="">
                                    Selecciona un usuario
                                </option>

                                @foreach (
                                    $supportUsers
                                    as $supportUser
                                )
                                    <option
                                        value="{{ $supportUser->id }}"
                                        @selected(
                                            old(
                                                'assigned_to_user_id'
                                            )
                                            == $supportUser->id
                                        )
                                    >
                                        {{ $supportUser->name }}
                                        —
                                        {{ str_replace(
                                            '_',
                                            ' ',
                                            $supportUser
                                                ->role
                                                ?->role_name
                                                ?? ''
                                        ) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button
                            type="submit"
                            class="support-show-primary-button"
                        >
                            Guardar asignación
                        </button>
                    </form>
                </section>
            @endif

            @if ($canChangeStatus)
                <section class="support-show-action-card">
                    <h2>Cambiar estado</h2>

                    <form
                        method="POST"
                        action="{{ route(
                            'portal.support-tickets.status.update',
                            $supportTicket
                        ) }}"
                        class="support-show-form"
                    >
                        @csrf
                        @method('PATCH')

                        <div class="support-show-field">
                            <label for="status">
                                Nuevo estado
                            </label>

                            <select
                                id="status"
                                name="status"
                                required
                            >
                                <option value="">
                                    Selecciona un estado
                                </option>

                                @foreach (
                                    $availableStatuses
                                    as $availableStatus
                                )
                                    <option
                                        value="{{ $availableStatus
                                            ->status_name }}"
                                        @selected(
                                            old('status')
                                            === $availableStatus
                                                ->status_name
                                        )
                                    >
                                        {{ str_replace(
                                            '_',
                                            ' ',
                                            $availableStatus
                                                ->status_name
                                        ) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="support-show-field">
                            <label for="comment">
                                Comentario
                            </label>

                            <textarea
                                id="comment"
                                name="comment"
                                rows="4"
                                maxlength="2000"
                                placeholder="Obligatorio para resolver, cerrar o reabrir"
                            >{{ old('comment') }}</textarea>
                        </div>

                        <button
                            type="submit"
                            class="support-show-primary-button"
                        >
                            Actualizar estado
                        </button>
                    </form>
                </section>
            @endif
        </aside>
    </section>
@endsection