<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Muestra el panel del usuario autenticado.
     */
    public function __invoke(
        Request $request
    ): View {
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
            $user->accountStatus?->status_name
                === 'ACTIVE',
            403,
            'The account is not active.'
        );

        $roleName = $user->role?->role_name;

        return view('dashboard', [
            'user' => $user,
            'roleName' => $roleName,
            'roleLabel' =>
                $this->roleLabel($roleName),
            'modules' =>
                $this->modulesFor($roleName),
        ]);
    }

    /**
     * Devuelve los módulos disponibles según el rol.
     *
     * @return array<int, array<string, string>>
     */
    private function modulesFor(
        ?string $roleName
    ): array {
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
                    'Tickets de soporte',
                    'Solicita ayuda y consulta tus tickets.',
                    'support-tickets.index'
                ),
                $this->module(
                    'Notificaciones',
                    'Revisa las novedades de tu cuenta.',
                    'notifications.index'
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
                    'routes.index'
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
                    'notifications.index'
                ),
            ],

            'COURIER' => [
                $this->module(
                    'Mis rutas',
                    'Consulta tus rutas y abre el mapa.',
                    'routes.index'
                ),
                $this->module(
                    'Incidentes',
                    'Consulta los incidentes relacionados.',
                    'incidents.index'
                ),
                $this->module(
                    'Notificaciones',
                    'Revisa las novedades de tus entregas.',
                    'notifications.index'
                ),
            ],

            'SUPPORT_AGENT' => [
                $this->module(
                    'Tickets de soporte',
                    'Atiende solicitudes de los clientes.',
                    'support-tickets.index'
                ),
                $this->module(
                    'Incidentes',
                    'Consulta y administra incidentes.',
                    'incidents.index'
                ),
                $this->module(
                    'Usuarios',
                    'Consulta las cuentas registradas.',
                    'users.index'
                ),
                $this->module(
                    'Rutas',
                    'Consulta la operación de las rutas.',
                    'routes.index'
                ),
                $this->module(
                    'Notificaciones',
                    'Revisa tus notificaciones.',
                    'notifications.index'
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
                    'routes.index'
                ),
                $this->module(
                    'Tickets de soporte',
                    'Administra los tickets de soporte.',
                    'support-tickets.index'
                ),
                $this->module(
                    'Incidentes',
                    'Administra los incidentes reportados.',
                    'incidents.index'
                ),
                $this->module(
                    'Registros de auditoría',
                    'Consulta la trazabilidad del sistema.',
                    'audit-logs.index'
                ),
            ],

            default => [],
        };
    }

    /**
     * Crea la configuración de un módulo.
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
     * Convierte el nombre interno del rol
     * en una etiqueta para la interfaz.
     */
    private function roleLabel(
        ?string $roleName
    ): string {
        return match ($roleName) {
            'CUSTOMER' => 'Cliente',
            'DELIVERY_PROVIDER' =>
                'Proveedor de entrega',
            'COURIER' => 'Repartidor',
            'SUPPORT_AGENT' =>
                'Agente de soporte',
            'ADMINISTRATOR' =>
                'Administrador',
            default => 'Usuario',
        };
    }
}