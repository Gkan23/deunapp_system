@extends('layouts.portal')

@section('title', 'Notificaciones | DeUnapp')

@section('content')
    <section class="notification-index-page">
        <header class="notification-index-header">
            <div>
                <p class="notification-index-eyebrow">
                    Centro de avisos
                </p>

                <h1>Notificaciones</h1>

                <p>
                    Consulta las novedades relacionadas
                    con tu cuenta y tus operaciones.
                </p>
            </div>

            <div class="notification-index-header-actions">
                @if ($unreadCount > 0)
                    <form
                        method="POST"
                        action="{{ route(
                            'portal.notifications.read-all'
                        ) }}"
                    >
                        @csrf
                        @method('PATCH')

                        <button type="submit">
                            Marcar todas como leídas
                        </button>
                    </form>
                @endif

                <a href="{{ route('dashboard') }}">
                    Volver al panel
                </a>
            </div>
        </header>

        @if (session('status'))
            <div
                class="
                    notification-index-alert
                    notification-index-alert-success
                "
                role="status"
            >
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('notification'))
            <div
                class="
                    notification-index-alert
                    notification-index-alert-error
                "
                role="alert"
            >
                {{ $errors->first(
                    'notification'
                ) }}
            </div>
        @endif

        <section class="notification-index-summary">
            <a
                href="{{ route(
                    'portal.notifications.index',
                    ['status' => 'all']
                ) }}"
                @class([
                    'notification-index-summary-card',
                    'is-active' =>
                        $selectedStatus === 'all',
                ])
            >
                <span>Total</span>
                <strong>{{ $totalCount }}</strong>
            </a>

            <a
                href="{{ route(
                    'portal.notifications.index',
                    ['status' => 'unread']
                ) }}"
                @class([
                    'notification-index-summary-card',
                    'is-active' =>
                        $selectedStatus === 'unread',
                ])
            >
                <span>Pendientes</span>
                <strong>{{ $unreadCount }}</strong>
            </a>

            <a
                href="{{ route(
                    'portal.notifications.index',
                    ['status' => 'read']
                ) }}"
                @class([
                    'notification-index-summary-card',
                    'is-active' =>
                        $selectedStatus === 'read',
                ])
            >
                <span>Leídas</span>
                <strong>{{ $readCount }}</strong>
            </a>
        </section>

        @if ($notifications->isEmpty())
            <section class="notification-index-empty">
                <span aria-hidden="true">
                    DU
                </span>

                <h2>
                    No hay notificaciones
                </h2>

                <p>
                    No existen notificaciones que coincidan
                    con el filtro seleccionado.
                </p>
            </section>
        @else
            <div class="notification-index-list">
                @foreach (
                    $notifications
                    as $notification
                )
                    <article
                        @class([
                            'notification-index-item',
                            'is-unread' =>
                                ! $notification->is_read,
                        ])
                    >
                        <div class="notification-index-icon">
                            {{ mb_strtoupper(
                                mb_substr(
                                    $notification->title,
                                    0,
                                    1
                                )
                            ) }}
                        </div>

                        <div class="notification-index-information">
                            <header>
                                <div>
                                    <span
                                        class="
                                            notification-index-type
                                        "
                                    >
                                        {{ $notification
                                            ->notificationType
                                            ?->type_name
                                            ?? 'SISTEMA' }}
                                    </span>

                                    <h2>
                                        {{ $notification->title }}
                                    </h2>
                                </div>

                                <span
                                    @class([
                                        'notification-index-status',
                                        'is-read' =>
                                            $notification->is_read,
                                    ])
                                >
                                    {{ $notification->is_read
                                        ? 'Leída'
                                        : 'Pendiente' }}
                                </span>
                            </header>

                            <p>
                                {{ $notification->message }}
                            </p>

                            <footer>
                                <span>
                                    Enviada:
                                    {{ $notification->sent_at
                                        ?->format('d/m/Y H:i')
                                        ?? 'Sin fecha' }}
                                </span>

                                @if ($notification->read_at)
                                    <span>
                                        Leída:
                                        {{ $notification
                                            ->read_at
                                            ->format('d/m/Y H:i') }}
                                    </span>
                                @endif
                            </footer>
                        </div>

                        @if (! $notification->is_read)
                            <form
                                method="POST"
                                action="{{ route(
                                    'portal.notifications.read',
                                    $notification
                                ) }}"
                                class="notification-index-read-form"
                            >
                                @csrf
                                @method('PATCH')

                                <button type="submit">
                                    Marcar como leída
                                </button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>

            @if ($notifications->hasPages())
                <div class="notification-index-pagination">
                    {{ $notifications->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection