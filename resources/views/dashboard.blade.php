@extends('layouts.portal')

@section('title', 'Panel principal | DeUnapp')

@section('content')
    <section class="portal-dashboard-heading">
        <div>
            <p class="portal-eyebrow">
                Panel principal
            </p>

            <h1>
                Hola, {{ $user->name }}
            </h1>

            <p>
                Consulta las funciones disponibles
                para tu cuenta.
            </p>
        </div>

        <div class="portal-user-summary">
            <span class="portal-user-avatar">
                {{ mb_strtoupper(
                    mb_substr($user->name, 0, 1)
                ) }}
            </span>

            <div>
                <strong>
                    {{ $user->name }}
                </strong>

                <span>
                    {{ $roleLabel }}
                </span>

                <small>
                    {{ $user->email }}
                </small>
            </div>
        </div>
    </section>

    <section class="portal-account-summary">
        <article>
            <span>Rol</span>

            <strong>
                {{ $roleLabel }}
            </strong>
        </article>

        <article>
            <span>Estado</span>

            <strong>
                {{ $user->accountStatus?->status_name }}
            </strong>
        </article>

        <article>
            <span>Correo verificado</span>

            <strong>
                {{ $user->hasVerifiedEmail()
                    ? 'Sí'
                    : 'No' }}
            </strong>
        </article>
    </section>

    <section class="portal-modules-section">
        <header class="portal-section-heading">
            <div>
                <p class="portal-eyebrow">
                    Funciones
                </p>

                <h2>
                    Módulos disponibles
                </h2>
            </div>

            <span>
                {{ count($modules) }}

                {{ count($modules) === 1
                    ? 'módulo'
                    : 'módulos' }}
            </span>
        </header>

        @if (count($modules) > 0)
            <div class="portal-module-grid">
                @foreach ($modules as $module)
                    <article class="portal-module-card">
                        <div class="portal-module-icon">
                            {{ mb_strtoupper(
                                mb_substr(
                                    $module['title'],
                                    0,
                                    1
                                )
                            ) }}
                        </div>

                        <h3>
                            {{ $module['title'] }}
                        </h3>

                        <p>
                            {{ $module['description'] }}
                        </p>

                        <a href="{{ $module['url'] }}">
                            <span>
                                Consultar módulo
                            </span>

                            <span aria-hidden="true">
                                →
                            </span>
                        </a>
                    </article>
                @endforeach
            </div>
        @else
            <div class="portal-empty-state">
                No hay módulos disponibles para esta cuenta.
            </div>
        @endif
    </section>
@endsection