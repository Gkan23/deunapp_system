<?php

namespace App\Providers;

use App\Models\ShipmentStatusHistory;
use App\Observers\ShipmentStatusHistoryObserver;
use Illuminate\Support\ServiceProvider;

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
    public function boot(): void
    {
        ShipmentStatusHistory::observe(ShipmentStatusHistoryObserver::class);
    }
}
