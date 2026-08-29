<?php

use App\Http\Controllers\AppNotificationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CurrentUserController;
use App\Http\Controllers\DeliveryServiceController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RechargeController;
use App\Http\Controllers\RechargePackageController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\RouteShipmentController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\SupportTicketMessageReadController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripTransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Application status
|--------------------------------------------------------------------------
|
| Ruta pública utilizada para comprobar que la aplicación está funcionando.
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
| Guest authentication routes
|--------------------------------------------------------------------------
|
| El inicio de sesión solamente está disponible para usuarios invitados.
| Esta ruta debe permanecer fuera del grupo protegido con middleware auth.
|
*/

Route::post(
    '/login',
    [AuthenticatedSessionController::class, 'store']
)
    ->middleware('guest')
    ->name('login');

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
|
| Todos los endpoints principales necesitan un usuario autenticado.
|
*/

Route::middleware('auth')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/logout',
        [AuthenticatedSessionController::class, 'destroy']
    )->name('logout');

    /*
    |--------------------------------------------------------------------------
    | Current authenticated user
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/me',
        CurrentUserController::class
    )->name('current-user.show');

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

    Route::post(
        '/delivery-services/{deliveryService}/ratings',
        [RatingController::class, 'store']
    )
        ->whereNumber('deliveryService')
        ->name('delivery-services.ratings.store');

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

    /*
    |--------------------------------------------------------------------------
    | Ratings
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/ratings',
        [RatingController::class, 'index']
    )->name('ratings.index');

    Route::get(
        '/ratings/{rating}',
        [RatingController::class, 'show']
    )
        ->whereNumber('rating')
        ->name('ratings.show');

    /*
    |--------------------------------------------------------------------------
    | Application notifications
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/notifications',
        [AppNotificationController::class, 'index']
    )->name('notifications.index');

    /*
     * Esta ruta fija debe aparecer antes de las rutas
     * que contienen el parámetro {appNotification}.
     */
    Route::patch(
        '/notifications/read-all',
        [AppNotificationController::class, 'markAllAsRead']
    )->name('notifications.read-all');

    Route::patch(
        '/notifications/{appNotification}/read',
        [AppNotificationController::class, 'markAsRead']
    )
        ->whereNumber('appNotification')
        ->name('notifications.read');

    Route::get(
        '/notifications/{appNotification}',
        [AppNotificationController::class, 'show']
    )
        ->whereNumber('appNotification')
        ->name('notifications.show');

    /*
    |--------------------------------------------------------------------------
    | Incidents
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/incidents',
        [IncidentController::class, 'index']
    )->name('incidents.index');

    Route::patch(
        '/incidents/{incident}/status',
        [IncidentController::class, 'updateStatus']
    )
        ->whereNumber('incident')
        ->name('incidents.status.update');

    Route::get(
        '/incidents/{incident}',
        [IncidentController::class, 'show']
    )
        ->whereNumber('incident')
        ->name('incidents.show');

    /*
    |--------------------------------------------------------------------------
    | Support tickets
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/support-tickets',
        [SupportTicketController::class, 'index']
    )->name('support-tickets.index');

    Route::post(
        '/support-tickets',
        [SupportTicketController::class, 'store']
    )->name('support-tickets.store');

    Route::patch(
        '/support-tickets/{supportTicket}/assign',
        [SupportTicketController::class, 'assign']
    )
        ->whereNumber('supportTicket')
        ->name('support-tickets.assign');

    Route::post(
        '/support-tickets/{supportTicket}/messages',
        [SupportTicketController::class, 'addMessage']
    )
        ->whereNumber('supportTicket')
        ->name('support-tickets.messages.store');

    Route::patch(
        '/support-tickets/{supportTicket}/messages/read',
        SupportTicketMessageReadController::class
    )
        ->whereNumber('supportTicket')
        ->name('support-tickets.messages.read');

    Route::patch(
        '/support-tickets/{supportTicket}/status',
        [SupportTicketController::class, 'updateStatus']
    )
        ->whereNumber('supportTicket')
        ->name('support-tickets.status.update');

    Route::get(
        '/support-tickets/{supportTicket}',
        [SupportTicketController::class, 'show']
    )
        ->whereNumber('supportTicket')
        ->name('support-tickets.show');

    /*
    |--------------------------------------------------------------------------
    | Recharges
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/recharges',
        [RechargeController::class, 'index']
    )->name('recharges.index');

    Route::post(
        '/recharges',
        [RechargeController::class, 'store']
    )->name('recharges.store');

    Route::get(
        '/recharges/{recharge}',
        [RechargeController::class, 'show']
    )
        ->whereNumber('recharge')
        ->name('recharges.show');

    /*
    |--------------------------------------------------------------------------
    | Recharge packages
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/recharge-packages',
        [RechargePackageController::class, 'index']
    )->name('recharge-packages.index');

    Route::get(
        '/recharge-packages/{rechargePackage}',
        [RechargePackageController::class, 'show']
    )
        ->whereNumber('rechargePackage')
        ->name('recharge-packages.show');

    /*
    |--------------------------------------------------------------------------
    | Trip inventory
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/trips',
        [TripController::class, 'index']
    )->name('trips.index');

    Route::get(
        '/trips/{trip}',
        [TripController::class, 'show']
    )
        ->whereNumber('trip')
        ->name('trips.show');

    /*
    |--------------------------------------------------------------------------
    | Trip transactions
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/trip-transactions',
        [TripTransactionController::class, 'index']
    )->name('trip-transactions.index');

    Route::get(
        '/trip-transactions/{tripTransaction}',
        [TripTransactionController::class, 'show']
    )
        ->whereNumber('tripTransaction')
        ->name('trip-transactions.show');

    /*
    |--------------------------------------------------------------------------
    | Audit logs
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/audit-logs',
        [AuditLogController::class, 'index']
    )->name('audit-logs.index');

    Route::get(
        '/audit-logs/{auditLog}',
        [AuditLogController::class, 'show']
    )
        ->whereNumber('auditLog')
        ->name('audit-logs.show');
});

/*
|--------------------------------------------------------------------------
| Additional authentication routes
|--------------------------------------------------------------------------
|
| Si routes/auth.php existe, se registran sus rutas adicionales.
|
*/

if (file_exists(__DIR__.'/auth.php')) {
    require __DIR__.'/auth.php';
}