<?php

use App\Http\Controllers\DeliveryServiceController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\RouteShipmentController;
use App\Http\Controllers\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/shipments',
        [ShipmentController::class, 'index']
    )->name('shipments.index');

    Route::post(
        '/shipments',
        [ShipmentController::class, 'store']
    )->name('shipments.store');

    Route::patch(
        '/shipments/{shipment}/status',
        [ShipmentController::class, 'updateStatus']
    )
        ->whereNumber('shipment')
        ->name('shipments.status.update');

    Route::patch(
        '/shipments/{shipment}/cancel',
        [ShipmentController::class, 'cancel']
    )
        ->whereNumber('shipment')
        ->name('shipments.cancel');

    Route::get(
        '/shipments/{shipment}',
        [ShipmentController::class, 'show']
    )
        ->whereNumber('shipment')
        ->name('shipments.show');

    Route::get(
        '/routes',
        [RouteController::class, 'index']
    )->name('routes.index');

    Route::post(
        '/routes',
        [RouteController::class, 'store']
    )->name('routes.store');

    Route::patch(
        '/routes/{route}/activate',
        [RouteController::class, 'activate']
    )
        ->whereNumber('route')
        ->name('routes.activate');

    Route::patch(
        '/routes/{route}/complete',
        [RouteController::class, 'complete']
    )
        ->whereNumber('route')
        ->name('routes.complete');

    Route::patch(
        '/routes/{route}/cancel',
        [RouteController::class, 'cancel']
    )
        ->whereNumber('route')
        ->name('routes.cancel');

    Route::get(
        '/routes/{route}',
        [RouteController::class, 'show']
    )
        ->whereNumber('route')
        ->name('routes.show');

    Route::patch(
        '/delivery-services/{deliveryService}/assign',
        [DeliveryServiceController::class, 'assign']
    )
        ->whereNumber('deliveryService')
        ->name('delivery-services.assign');

    Route::patch(
        '/delivery-services/{deliveryService}/complete',
        [DeliveryServiceController::class, 'complete']
    )
        ->whereNumber('deliveryService')
        ->name('delivery-services.complete');

    Route::patch(
        '/route-shipments/{routeShipment}/fail-attempt',
        [RouteShipmentController::class, 'failAttempt']
    )
        ->whereNumber('routeShipment')
        ->name('route-shipments.fail-attempt');
});