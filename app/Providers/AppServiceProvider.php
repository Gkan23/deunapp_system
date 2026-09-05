<?php

namespace App\Providers;

use App\Models\ShipmentStatusHistory;
use App\Models\User;
use App\Observers\ShipmentStatusHistoryObserver;
use App\Support\PortalNavigation;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(
        PortalNavigation $navigation
    ): void {
        ShipmentStatusHistory::observe(
            ShipmentStatusHistoryObserver::class
        );

        ViewFacade::composer(
            'layouts.portal',
            function (View $view) use ($navigation): void {
                $user = auth()->user();

                if (! $user instanceof User) {
                    $view->with([
                        'portalUser' => null,
                        'portalRoleLabel' => 'Usuario',
                        'portalNavigationModules' => [],
                    ]);

                    return;
                }

                $user->loadMissing([
                    'role',
                    'accountStatus',
                ]);

                $roleName = $user->role?->role_name;

                $view->with([
                    'portalUser' => $user,
                    'portalRoleLabel' =>
                        $navigation->roleLabel(
                            $roleName
                        ),
                    'portalNavigationModules' =>
                        $navigation->modulesFor(
                            $roleName
                        ),
                ]);
            }
        );
    }
}