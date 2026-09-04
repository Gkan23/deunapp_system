@extends('layouts.portal')

@section('title', 'Nuevo ticket | DeUnapp')

@section('content')
    <section class="support-create-heading">
        <div>
            <p class="portal-eyebrow">
                Centro de ayuda
            </p>

            <h1>Nuevo ticket de soporte</h1>

            <p>
                Cuéntanos qué ocurrió para que podamos ayudarte.
            </p>
        </div>

        <a
            href="{{ route('portal.support-tickets.index') }}"
            class="support-create-secondary-button"
        >
            Volver a tickets
        </a>
    </section>

    @if ($errors->any())
        <div
            class="support-create-alert"
            role="alert"
        >
            <strong>
                No fue posible crear el ticket.
            </strong>

            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="support-create-layout">
        <section class="support-create-card">
            <header class="support-create-card-header">
                <h2>Datos de la solicitud</h2>

                <p>
                    Los campos marcados con un asterisco
                    son obligatorios.
                </p>
            </header>

            <form
                method="POST"
                action="{{ route('portal.support-tickets.store') }}"
                class="support-create-form"
            >
                @csrf

                <div class="support-create-field">
                    <label for="category">
                        Categoría *
                    </label>

                    <select
                        id="category"
                        name="category"
                        required
                        aria-invalid="{{ $errors->has('category') ? 'true' : 'false' }}"
                    >
                        <option value="">
                            Selecciona una categoría
                        </option>

                        @foreach ($categories as $category)
                            <option
                                value="{{ $category->category_name }}"
                                @selected(
                                    old('category')
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

                    @error('category')
                        <span class="support-create-field-error">
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <div class="support-create-field">
                    <label for="shipment_id">
                        Envío relacionado
                    </label>

                    <select
                        id="shipment_id"
                        name="shipment_id"
                        aria-describedby="shipment-help"
                        aria-invalid="{{ $errors->has('shipment_id') ? 'true' : 'false' }}"
                    >
                        <option value="">
                            Sin envío relacionado
                        </option>

                        @foreach ($shipments as $shipment)
                            <option
                                value="{{ $shipment->id }}"
                                @selected(
                                    (string) old('shipment_id', '')
                                    === (string) $shipment->id
                                )
                            >
                                {{ $shipment->tracking_code }}
                            </option>
                        @endforeach
                    </select>

                    <small id="shipment-help">
                        @if ($shipments->isEmpty())
                            Todavía no tienes envíos.
                            Puedes crear una solicitud general.
                        @else
                            Este campo es opcional.
                            Solo aparecen tus propios envíos.
                        @endif
                    </small>

                    @error('shipment_id')
                        <span class="support-create-field-error">
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <div class="support-create-field">
                    <label for="subject">
                        Asunto *
                    </label>

                    <input
                        id="subject"
                        name="subject"
                        type="text"
                        value="{{ old('subject') }}"
                        maxlength="200"
                        placeholder="Resume brevemente el problema"
                        required
                        aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}"
                    >

                    @error('subject')
                        <span class="support-create-field-error">
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <div class="support-create-field">
                    <label for="message">
                        Mensaje *
                    </label>

                    <textarea
                        id="message"
                        name="message"
                        rows="8"
                        placeholder="Describe lo ocurrido e incluye los detalles necesarios."
                        required
                        aria-describedby="message-help"
                        aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}"
                    >{{ old('message') }}</textarea>

                    <small id="message-help">
                        No compartas contraseñas, códigos de acceso
                        ni datos completos de tarjetas.
                    </small>

                    @error('message')
                        <span class="support-create-field-error">
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                <div class="support-create-actions">
                    <a
                        href="{{ route('portal.support-tickets.index') }}"
                        class="support-create-secondary-button"
                    >
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        class="support-create-primary-button"
                    >
                        Crear ticket
                    </button>
                </div>
            </form>
        </section>

        <aside class="support-create-help">
            <h2>¿Qué sucede después?</h2>

            <ol>
                <li>
                    Se crea tu ticket junto con el primer mensaje.
                </li>

                <li>
                    El equipo de soporte podrá revisar
                    y asignarse la solicitud.
                </li>

                <li>
                    Podrás consultar las respuestas
                    y continuar la conversación desde el detalle.
                </li>
            </ol>

            <p>
                El ticket se registra con estado OPEN
                y prioridad MEDIUM.
            </p>
        </aside>
    </div>
@endsection