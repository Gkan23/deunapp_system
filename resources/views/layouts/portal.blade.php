<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        @yield('title', 'DeUnapp System')
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body class="portal-body">
    <a
        href="#portal-content"
        class="portal-skip-link"
    >
        Saltar al contenido
    </a>

    <div class="portal-application">
        <aside
            id="portal-sidebar"
            class="portal-sidebar"
            data-portal-sidebar
            data-open="false"
            aria-label="Navegación principal"
        >
            <header class="portal-sidebar-header">
                <a
                    href="{{ route('dashboard') }}"
                    class="portal-sidebar-brand"
                    aria-label="DeUnapp - Panel principal"
                >
                    <img
                        src="{{ asset(
                            'images/brand/deunapp-horizontal-negative.png'
                        ) }}"
                        alt="DeUnapp"
                        width="2048"
                        height="657"
                    >
                </a>

                <button
                    type="button"
                    class="portal-sidebar-close"
                    data-portal-menu-close
                    aria-label="Cerrar menú"
                >
                    <span aria-hidden="true">
                        &times;
                    </span>
                </button>
            </header>

            @if (($portalUser ?? null) !== null)
                <section class="portal-sidebar-user">
                    <span
                        class="portal-sidebar-avatar"
                        aria-hidden="true"
                    >
                        {{
                            mb_strtoupper(
                                mb_substr(
                                    $portalUser->name,
                                    0,
                                    1
                                )
                            )
                        }}
                    </span>

                    <div class="portal-sidebar-user-information">
                        <strong>
                            {{ $portalUser->name }}
                        </strong>

                        <span>
                            {{ $portalRoleLabel }}
                        </span>
                    </div>
                </section>
            @endif

            <nav
                class="portal-sidebar-navigation"
                aria-label="Módulos del portal"
            >
                <p class="portal-sidebar-label">
                    Navegación
                </p>

                <a
                    href="{{ route('dashboard') }}"
                    class="portal-sidebar-link
                        {{
                            request()->routeIs('dashboard')
                                ? 'portal-sidebar-link-active'
                                : ''
                        }}"
                    @if (request()->routeIs('dashboard'))
                        aria-current="page"
                    @endif
                >
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                    >
                        <path d="M3 11.5 12 4l9 7.5" />

                        <path d="M5.5 10v10h13V10" />

                        <path d="M9.5 20v-6h5v6" />
                    </svg>

                    <span>
                        Inicio
                    </span>
                </a>

                @foreach (
                    ($portalNavigationModules ?? [])
                    as $module
                )
                    @php
                        $modulePattern =
                            \Illuminate\Support\Str::beforeLast(
                                $module['route'],
                                '.'
                            ).'.*';

                        $routeMapIsActive =
                            $module['route']
                                === 'portal.routes.index'
                            && request()->routeIs(
                                'routes.map.view'
                            );

                        $moduleIsActive =
                            request()->routeIs(
                                $module['route'],
                                $modulePattern
                            )
                            || $routeMapIsActive;
                    @endphp

                    <a
                        href="{{ $module['url'] }}"
                        class="portal-sidebar-link
                            {{
                                $moduleIsActive
                                    ? 'portal-sidebar-link-active'
                                    : ''
                            }}"
                        @if ($moduleIsActive)
                            aria-current="page"
                        @endif
                    >
                        <span
                            class="portal-sidebar-module-mark"
                            aria-hidden="true"
                        >
                            {{
                                mb_strtoupper(
                                    mb_substr(
                                        $module['title'],
                                        0,
                                        1
                                    )
                                )
                            }}
                        </span>

                        <span>
                            {{ $module['title'] }}
                        </span>
                    </a>
                @endforeach
            </nav>

            <footer class="portal-sidebar-footer">
                <a
                    href="{{ route(
                        'current-user.settings'
                    ) }}"
                    class="portal-sidebar-link
                        {{
                            request()->routeIs(
                                'current-user.*'
                            )
                                ? 'portal-sidebar-link-active'
                                : ''
                        }}"
                    @if (
                        request()->routeIs(
                            'current-user.*'
                        )
                    )
                        aria-current="page"
                    @endif
                >
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            cx="12"
                            cy="8"
                            r="3.5"
                        />

                        <path
                            d="M5.5 20c.6-3.4
                                3-5.2 6.5-5.2
                                s5.9 1.8 6.5 5.2"
                        />
                    </svg>

                    <span>
                        Mi cuenta
                    </span>
                </a>

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf

                    <button
                        type="submit"
                        class="portal-sidebar-logout"
                    >
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 24 24"
                        >
                            <path d="M10 5H5v14h5" />

                            <path d="M14 8l4 4-4 4" />

                            <path d="M8 12h10" />
                        </svg>

                        <span>
                            Cerrar sesión
                        </span>
                    </button>
                </form>
            </footer>
        </aside>

        <button
            type="button"
            class="portal-sidebar-overlay"
            data-portal-overlay
            aria-label="Cerrar menú"
            tabindex="-1"
        ></button>

        <div class="portal-workspace">
            <header class="portal-mobile-header">
                <button
                    type="button"
                    class="portal-mobile-menu-button"
                    data-portal-menu-toggle
                    aria-controls="portal-sidebar"
                    aria-expanded="false"
                    aria-label="Abrir menú"
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <a
                    href="{{ route('dashboard') }}"
                    class="portal-mobile-brand"
                    aria-label="DeUnapp - Panel principal"
                >
                    <img
                        src="{{ asset(
                            'images/brand/deunapp-horizontal.png'
                        ) }}"
                        alt="DeUnapp"
                        width="2048"
                        height="657"
                    >
                </a>

                @if (($portalUser ?? null) !== null)
                    <span
                        class="portal-mobile-avatar"
                        title="{{ $portalUser->name }}"
                        aria-label="{{
                            'Usuario: '.$portalUser->name
                        }}"
                    >
                        {{
                            mb_strtoupper(
                                mb_substr(
                                    $portalUser->name,
                                    0,
                                    1
                                )
                            )
                        }}
                    </span>
                @endif
            </header>

            @if (session('status'))
                <div
                    class="portal-alert portal-alert-success"
                    role="status"
                >
                    {{ session('status') }}
                </div>
            @endif

            <main
                id="portal-content"
                class="portal-main"
                tabindex="-1"
            >
                @yield('content')
            </main>

            <footer class="portal-footer">
                <span>
                    DeUnapp System
                </span>

                <span>
                    {{ now()->year }}
                </span>
            </footer>
        </div>
    </div>
</body>
</html>