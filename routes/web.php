<?php

use App\Http\Controllers\DeliveryServiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\RouteShipmentController;
use App\Http\Controllers\ShipmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Application status
|--------------------------------------------------------------------------
|
| Esta ruta permite comprobar que la aplicación está funcionando.
| También satisface la prueba predeterminada ExampleTest.
|
*/

Route::get('/', function () {
    return response()->json([
        'application' => 'DeUnapp System',
        'status' => 'ok',
    ]);
})->name('home');

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Shipments
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Route shipments
    |--------------------------------------------------------------------------
    */

    Route::patch(
        '/route-shipments/{routeShipment}/fail-attempt',
        [RouteShipmentController::class, 'failAttempt']
    )
        ->whereNumber('routeShipment')
        ->name('route-shipments.fail-attempt');

    /*
    |--------------------------------------------------------------------------
    | Delivery services
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/delivery-services',
        [DeliveryServiceController::class, 'index']
    )->name('delivery-services.index');

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

    Route::get(
        '/delivery-services/{deliveryService}',
        [DeliveryServiceController::class, 'show']
    )
        ->whereNumber('deliveryService')
        ->name('delivery-services.show');

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/payments',
        [PaymentController::class, 'index']
    )->name('payments.index');

    Route::patch(
        '/payments/{payment}/confirm',
        [PaymentController::class, 'confirm']
    )
        ->whereNumber('payment')
        ->name('payments.confirm');

    Route::patch(
        '/payments/{payment}/refund',
        [PaymentController::class, 'refund']
    )
        ->whereNumber('payment')
        ->name('payments.refund');

    Route::get(
        '/payments/{payment}',
        [PaymentController::class, 'show']
    )
        ->whereNumber('payment')
        ->name('payments.show');
});

/*
|--------------------------------------------------------------------------
| Authentication routes
|--------------------------------------------------------------------------
|
| Si routes/auth.php existe, se cargan sus rutas. Si no existe, el proyecto
| sigue funcionando porque los endpoints JSON devuelven 401 a invitados.
|
*/

if (file_exists(__DIR__.'/auth.php')) {
    require __DIR__.'/auth.php';
}