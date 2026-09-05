<?php

namespace App\Support;

class PortalNavigation
{
    /**
     * @return array<int, array{
     *     title: string,
     *     description: string,
     *     route: string,
     *     url: string
     * }>
     */
    public function modulesFor(?string $roleName): array
    {
        $modules = match ($roleName) {
            'CUSTOMER' => [
                [
                    'Mis envíos',
                    'Consulta tus envíos y su estado actual.',
                    'portal.shipments.index',
                ],
                [
                    'Servicios de entrega',
                    'Consulta los servicios solicitados.',
                    'delivery-services.index',
                ],
                [
                    'Pagos',
                    'Consulta el historial de pagos.',
                    'payments.index',
                ],
                [
                    'Incidentes',
                    'Consulta los incidentes de tus envíos.',
                    'portal.incidents.index',
                ],
                [
                    'Tickets de soporte',
                    'Solicita ayuda y consulta tus tickets.',
                    'portal.support-tickets.index',
                ],
                [
                    'Notificaciones',
                    'Revisa las novedades de tu cuenta.',
                    'portal.notifications.index',
                ],
            ],

            'DELIVERY_PROVIDER' => [
                [
                    'Repartidores',
                    'Administra los repartidores del proveedor.',
                    'couriers.index',
                ],
                [
                    'Vehículos',
                    'Consulta y administra los vehículos.',
                    'vehicles.index',
                ],
                [
                    'Rutas',
                    'Consulta las rutas de entrega.',
                    'portal.routes.index',
                ],
                [
                    'Incidentes',
                    'Consulta los incidentes relacionados con tus viajes.',
                    'portal.incidents.index',
                ],
                [
                    'Recargas',
                    'Consulta y confirma recargas de viajes.',
                    'recharges.index',
                ],
                [
                    'Paquetes de recarga',
                    'Consulta los paquetes disponibles.',
                    'recharge-packages.index',
                ],
                [
                    'Viajes',
                    'Consulta los viajes disponibles y utilizados.',
                    'trips.index',
                ],
                [
                    'Notificaciones',
                    'Revisa las novedades del proveedor.',
                    'portal.notifications.index',
                ],
            ],

            'COURIER' => [
                [
                    'Mis rutas',
                    'Consulta tus rutas y abre el mapa.',
                    'portal.routes.index',
                ],
                [
                    'Incidentes',
                    'Consulta los incidentes relacionados.',
                    'portal.incidents.index',
                ],
                [
                    'Notificaciones',
                    'Revisa las novedades de tus entregas.',
                    'portal.notifications.index',
                ],
            ],

            'SUPPORT_AGENT' => [
                [
                    'Tickets de soporte',
                    'Atiende las solicitudes de los clientes.',
                    'portal.support-tickets.index',
                ],
                [
                    'Incidentes',
                    'Consulta los incidentes reportados.',
                    'portal.incidents.index',
                ],
                [
                    'Usuarios',
                    'Consulta las cuentas registradas.',
                    'users.index',
                ],
                [
                    'Rutas',
                    'Consulta la operación de las rutas.',
                    'portal.routes.index',
                ],
                [
                    'Notificaciones',
                    'Revisa tus notificaciones.',
                    'portal.notifications.index',
                ],
            ],

            'ADMINISTRATOR' => [
                [
                    'Administración de usuarios',
                    'Administra roles y estados de cuenta.',
                    'users.index',
                ],
                [
                    'Repartidores',
                    'Consulta todos los repartidores.',
                    'couriers.index',
                ],
                [
                    'Vehículos',
                    'Consulta todos los vehículos.',
                    'vehicles.index',
                ],
                [
                    'Envíos',
                    'Supervisa los envíos registrados.',
                    'portal.shipments.index',
                ],
                [
                    'Rutas',
                    'Supervisa las rutas de entrega.',
                    'portal.routes.index',
                ],
                [
                    'Tickets de soporte',
                    'Administra los tickets de soporte.',
                    'portal.support-tickets.index',
                ],
                [
                    'Incidentes',
                    'Consulta los incidentes reportados.',
                    'portal.incidents.index',
                ],
                [
                    'Registros de auditoría',
                    'Consulta la trazabilidad del sistema.',
                    'audit-logs.index',
                ],
                [
                    'Notificaciones',
                    'Revisa las notificaciones del sistema.',
                    'portal.notifications.index',
                ],
            ],

            default => [],
        };

        return array_map(
            fn (array $module): array => [
                'title' => $module[0],
                'description' => $module[1],
                'route' => $module[2],
                'url' => route($module[2]),
            ],
            $modules
        );
    }

    public function roleLabel(?string $roleName): string
   
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