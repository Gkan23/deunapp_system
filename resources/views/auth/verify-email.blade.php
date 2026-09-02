@extends('layouts.portal')

@section('title', 'Verificar correo | DeUnapp')

@section('content')
    <section class="portal-centered-section">
        <article class="portal-verification-card">
            <div class="portal-verification-icon">
                @
            </div>

            <p class="portal-eyebrow">
                Verificación requerida
            </p>

            <h1>Revisa tu correo electrónico</h1>

            <p>
                Enviamos un enlace de verificación a
                <strong>{{ $user->email }}</strong>.
                Debes verificarlo antes de utilizar
                las funciones operativas del sistema.
            </p>

            <div
                id="verification-message"
                class="portal-alert"
                hidden
            ></div>

            <form
                id="verification-form"
                action="{{ route('verification.send') }}"
                method="POST"
            >
                @csrf

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    Reenviar enlace de verificación
                </button>
            </form>
        </article>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById(
                'verification-form'
            );

            const message = document.getElementById(
                'verification-message'
            );

            if (! form || ! message) {
                return;
            }

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                const button = form.querySelector(
                    'button[type="submit"]'
                );

                button.disabled = true;
                button.textContent = 'Enviando...';

                try {
                    const response = await fetch(
                        form.action,
                        {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document
                                    .querySelector(
                                        'meta[name="csrf-token"]'
                                    )
                                    .content,
                            },
                        }
                    );

                    const data = await response.json();

                    message.hidden = false;
                    message.textContent = data.message
                        ?? 'Solicitud procesada.';

                    message.className = response.ok
                        ? 'portal-alert portal-alert-success'
                        : 'portal-alert portal-alert-error';
                } catch (error) {
                    message.hidden = false;
                    message.textContent =
                        'No fue posible enviar el enlace.';

                    message.className =
                        'portal-alert portal-alert-error';
                } finally {
                    button.disabled = false;
                    button.textContent =
                        'Reenviar enlace de verificación';
                }
            });
        });
    </script>
@endsection