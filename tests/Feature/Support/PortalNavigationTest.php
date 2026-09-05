<?php

namespace Tests\Feature\Support;

use App\Support\PortalNavigation;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PortalNavigationTest extends TestCase
{
    public function test_it_returns_the_modules_available_for_each_role(): void
    {
        $expectedModules = [
            'CUSTOMER' => [
                'Mis envíos',
                'Servicios de entrega',
                'Pagos',
                'Incidentes',
                'Tickets de soporte',
                'Notificaciones',
            ],
            'DELIVERY_PROVIDER' => [
                'Repartidores',
                'Vehículos',
                'Rutas',
                'Incidentes',
                'Recargas',
                'Paquetes de recarga',
                'Viajes',
                'Notificaciones',
            ],
            'COURIER' => [
                'Mis rutas',
                'Incidentes',
                'Notificaciones',
            ],
            'SUPPORT_AGENT' => [
                'Tickets de soporte',
                'Incidentes',
                'Usuarios',
                'Rutas',
                'Notificaciones',
            ],
            'ADMINISTRATOR' => [
                'Administración de usuarios',
                'Repartidores',
                'Vehículos',
                'Envíos',
                'Rutas',
                'Tickets de soporte',
                'Incidentes',
                'Registros de auditoría',
                'Notificaciones',
            ],
        ];

        $navigation = app(
            PortalNavigation::class
        );

        foreach (
            $expectedModules as
            $roleName => $expectedTitles
        ) {
            $modules = $navigation->modulesFor(
                $roleName
            );

            $this->assertSame(
                $expectedTitles,
                array_column($modules, 'title')
            );

            foreach ($modules as $module) {
                $this->assertTrue(
                    Route::has($module['route'])
                );

                $this->assertSame(
                    route($module['route']),
                    $module['url']
                );
            }
        }
    }

    public function test_it_returns_no_modules_for_an_unknown_role(): void
    {
        $navigation = app(
            PortalNavigation::class
        );

        $this->assertSame(
            [],
            $navigation->modulesFor('UNKNOWN')
        );

        $this->assertSame(
            'Usuario',
            $navigation->roleLabel('UNKNOWN')
        );
    }
}