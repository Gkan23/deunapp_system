<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Muestra el panel principal del usuario autenticado.
     */
    public function __invoke(Request $request): View
    {
        $user = User::query()
            ->with([
                'role',
                'accountStatus',
                'customer.customerType',
                'deliveryProvider.providerType',
                'courier.deliveryProvider.providerType',
            ])
            ->findOrFail(
                $request->user()->getKey()
            );

        abort_unless(
            $user->accountStatus?->status_name === 'ACTIVE',
            403,
            'The account is not active.'
        );

        $roleName = $user->role?->role_name;

        return view('dashboard', [
            'user' => $user,
            'roleName' => $roleName,
            'roleLabel' => $this->roleLabel($roleName),
            'modules' => $this->modulesFor($roleName),
        ]);
    }

    /**
     * Obtiene los módulos disponibles para cada rol.
     *
     * @return array<int, array<string, string>>
     */
    private function modulesFor(?string $roleName): array
    {
        return match ($roleName) {
            'CUSTOMER' => [
                $this->module(
                    'Mis envíos',
                    'Consulta tus envíos y su estado actual.',
                    'portal.shipments.index'
                ),
                $this->module(
                    'Servicios de entrega',
                    'Consulta los servicios solicitados.',
                    'delivery-services.index'
                ),
                $this->module(
                    'Pagos',
                    'Consulta el historial de pagos.',
                    'payments.index'
                ),
                $this->module(
                    'Incidentes',
                    'Consulta los incidentes de tus envíos.',
                    'portal.incidents.index'
                ),
                $this->module(
                    'Tickets de soporte',
                    'Solicita ayuda y consulta tus tickets.',
                    'portal.support-tickets.index'
                ),
                $this->module(
                    'Notificaciones',
                    'Revisa las novedades de tu cuenta.',
                    'portal.notifications.index'
                ),
            ],

            'DELIVERY_PROVIDER' => [
                $this->module(
                    'Repartidores',
                    'Administra los repartidores del proveedor.',
                    'couriers.index'
                ),
                $this->module(
                    'Vehículos',
                    'Consulta y administra los vehículos.',
                    'vehicles.index'
                ),
                $this->module(
                    'Rutas',
                    'Consulta las rutas de entrega.',
                    'portal.routes.index'
                ),
                $this->module(
                    'Incidentes',
                    'Consulta los incidentes relacionados con tus viajes.',
                    'portal.incidents.index'
                ),
                $this->module(
                    'Recargas',
                    'Consulta y confirma recargas de viajes.',
                    'recharges.index'
                ),
                $this->module(
                    'Paquetes de recarga',
                    'Consulta los paquetes disponibles.',
                    'recharge-packages.index'
                ),
                $this->module(
                    'Viajes',
                    'Consulta los viajes disponibles y utilizados.',
                    'trips.index'
                ),
                $this->module(
                    'Notificaciones',
                    'Revisa las novedades del proveedor.',
                    'portal.notifications.index'
                ),
            ],

            'COURIER' => [
                $this->module(
                    'Mis rutas',
                    'Consulta tus rutas y abre el mapa.',
                    'portal.routes.index'
                ),
                $this->module(
                    'Incidentes',
                    'Consulta los incidentes relacionados.',
                    'portal.incidents.index'
                ),
                $this->module(
                    'Notificaciones',
                    'Revisa las novedades de tus entregas.',
                    'portal.notifications.index'
                ),
            ],

            'SUPPORT_AGENT' => [
                $this->module(
                    'Tickets de soporte',
                    'Atiende las solicitudes de los clientes.',
                    'portal.support-tickets.index'
                ),
                $this->module(
                    'Incidentes',
                    'Consulta los incidentes reportados.',
                    'portal.incidents.index'
                ),
                $this->module(
                    'Usuarios',
                    'Consulta las cuentas registradas.',
                    'users.index'
                ),
                $this->module(
                    'Rutas',
                    'Consulta la operación de las rutas.',
                    'portal.routes.index'
                ),
                $this->module(
                    'Notificaciones',
                    'Revisa tus notificaciones.',
                    'portal.notifications.index'
                ),
            ],

            'ADMINISTRATOR' => [
                $this->module(
                    'Administración de usuarios',
                    'Administra roles y estados de cuenta.',
                    'users.index'
                ),
                $this->module(
                    'Repartidores',
                    'Consulta todos los repartidores.',
                    'couriers.index'
                ),
                $this->module(
                    'Vehículos',
                    'Consulta todos los vehículos.',
                    'vehicles.index'
                ),
                $this->module(
                    'Envíos',
                    'Supervisa los envíos registrados.',
                    'portal.shipments.index'
                ),
                $this->module(
                    'Rutas',
                    'Supervisa las rutas de entrega.',
                    'portal.routes.index'
                ),
                $this->module(
                    'Tickets de soporte',
                    'Administra los tickets de soporte.',
                    'portal.support-tickets.index'
                ),
                $this->module(
                    'Incidentes',
                    'Consulta los incidentes reportados.',
                    'portal.incidents.index'
                ),
                $this->module(
                    'Registros de auditoría',
                    'Consulta la trazabilidad del sistema.',
                    'audit-logs.index'
                ),
                $this->module(
                    'Notificaciones',
                    'Revisa las notificaciones del sistema.',
                    'portal.notifications.index'
                ),
            ],

            default => [],
        };
    }

    /**
     * Construye la información de un módulo.
     *
     * @return array<string, string>
     */
    private function module(
        string $title,
        string $description,
        string $routeName
    ): array {
        return [
            'title' => $title,
            'description' => $description,
            'url' => route($routeName),
        ];
    }

    /**
     * Devuelve la etiqueta visible del rol.
     */
    private function roleLabel(?string $roleName): string
    {
        return match ($roleName) {
            'CUSTOMER' => 'Cliente',
            'DELIVERY_PROVIDER' => 'Proveedor de entrega',
            'COURIER' => 'Repartidor',
            'SUPPORT_AGENT' => 'Agente de soporte',
            'ADMINISTRATOR' => 'Administrador',
            default => 'Usuario',
        };
    }
}