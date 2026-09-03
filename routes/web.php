<?php

use App\Http\Controllers\AppNotificationController;
use App\Http\Controllers\AppNotificationIndexPageController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\CurrentUserEmailController;
use App\Http\Controllers\Auth\CurrentUserPasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\ForgotPasswordPageController;
use App\Http\Controllers\Auth\LoginPageController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterCustomerPageController;
use App\Http\Controllers\Auth\RegisterDeliveryProviderPageController;
use App\Http\Controllers\Auth\RegisteredDeliveryProviderController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\CourierLocationController;
use App\Http\Controllers\CurrentCourierAvailabilityController;
use App\Http\Controllers\CurrentCourierLocationController;
use App\Http\Controllers\CurrentCourierProfileController;
use App\Http\Controllers\CurrentCourierProfilePageController;
use App\Http\Controllers\CurrentCustomerProfileController;
use App\Http\Controllers\CurrentCustomerProfilePageController;
use App\Http\Controllers\CurrentDeliveryProviderProfileController;
use App\Http\Controllers\CurrentDeliveryProviderProfilePageController;
use App\Http\Controllers\CurrentUserController;
use App\Http\Controllers\CurrentUserSettingsPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryProofController;
use App\Http\Controllers\DeliveryServiceController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortalAppNotificationController;
use App\Http\Controllers\PortalShipmentController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RechargeController;
use App\Http\Controllers\RechargePackageController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\RouteMapController;
use App\Http\Controllers\RouteMapPageController;
use App\Http\Controllers\RouteShipmentController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentCreatePageController;
use App\Http\Controllers\ShipmentIndexPageController;
use App\Http\Controllers\ShipmentPackageController;
use App\Http\Controllers\ShipmentShowPageController;
use App\Http\Controllers\ShipmentStatusHistoryController;
use App\Http\Controllers\ShipmentTrackingController;
use App\Http\Controllers\ShipmentTrackingPageController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\SupportTicketIndexPageController;
use App\Http\Controllers\SupportTicketMessageReadController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripTransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Application status
|--------------------------------------------------------------------------
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
*/

Route::middleware('guest')
    ->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Customer registration
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/register',
            RegisterCustomerPageController::class
        )->name('register.page');

        Route::post(
            '/register',
            [
                RegisteredUserController::class,
                'store',
            ]
        )->name('register');

        /*
        |--------------------------------------------------------------------------
        | Delivery provider registration
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/register/provider',
            RegisterDeliveryProviderPageController::class
        )->name('provider.register.page');

        Route::post(
            '/register/provider',
            [
                RegisteredDeliveryProviderController::class,
                'store',
            ]
        )->name('provider.register');

        /*
        |--------------------------------------------------------------------------
        | Login
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/login',
            LoginPageController::class
        )->name('login.page');

        Route::post(
            '/login',
            [
                AuthenticatedSessionController::class,
                'store',
            ]
        )->name('login');

        /*
        |--------------------------------------------------------------------------
        | Password recovery
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/forgot-password',
            ForgotPasswordPageController::class
        )->name('password.request');

        Route::post(
            '/forgot-password',
            [
                PasswordResetLinkController::class,
                'store',
            ]
        )->name('password.email');

        Route::get(
            '/reset-password/{token}',
            [
                NewPasswordController::class,
                'create',
            ]
        )->name('password.reset');

        Route::post(
            '/reset-password',
            [
                NewPasswordController::class,
                'store',
            ]
        )->name('password.store');
    });

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')
    ->group(function (): void {
        Route::post(
            '/logout',
            [
                AuthenticatedSessionController::class,
                'destroy',
            ]
        )->name('logout');

        /*
        |--------------------------------------------------------------------------
        | Email verification
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/email/verify',
            EmailVerificationPromptController::class
        )->name('verification.notice');

        Route::post(
            '/email/verification-notification',
            [
                EmailVerificationNotificationController::class,
                'store',
            ]
        )
            ->middleware('throttle:6,1')
            ->name('verification.send');

        Route::get(
            '/verify-email/{id}/{hash}',
            VerifyEmailController::class
        )
            ->middleware([
                'signed',
                'throttle:6,1',
            ])
            ->name('verification.verify');

        /*
        |--------------------------------------------------------------------------
        | Current user settings and profile
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/settings/account',
            CurrentUserSettingsPageController::class
        )->name('current-user.settings');

        Route::get(
            '/settings/customer-profile',
            CurrentCustomerProfilePageController::class
        )->name(
            'current-user.profile.edit'
        );

        Route::get(
            '/settings/provider-profile',
            CurrentDeliveryProviderProfilePageController::class
        )->name(
            'current-user.provider-profile.edit'
        );

        Route::get(
            '/settings/courier-profile',
            CurrentCourierProfilePageController::class
        )->name(
            'current-user.courier-profile.edit'
        );

        Route::get(
            '/me',
            CurrentUserController::class
        )->name('current-user.show');

        Route::put(
            '/me/email',
            [
                CurrentUserEmailController::class,
                'update',
            ]
        )->name(
            'current-user.email.update'
        );

        Route::put(
            '/me/password',
            [
                CurrentUserPasswordController::class,
                'update',
            ]
        )->name(
            'current-user.password.update'
        );

        Route::patch(
            '/me/profile',
            [
                CurrentCustomerProfileController::class,
                'update',
            ]
        )->name(
            'current-user.profile.update'
        );

        Route::patch(
            '/me/provider-profile',
            [
                CurrentDeliveryProviderProfileController::class,
                'update',
            ]
        )->name(
            'current-user.provider-profile.update'
        );

        Route::patch(
            '/me/courier-profile',
            [
                CurrentCourierProfileController::class,
                'update',
            ]
        )->name(
            'current-user.courier-profile.update'
        );

        Route::patch(
            '/me/courier-availability',
            [
                CurrentCourierAvailabilityController::class,
                'update',
            ]
        )
            ->middleware('verified')
            ->name(
                'current-user.courier-availability.update'
            );

        Route::post(
            '/me/courier-locations',
            [
                CurrentCourierLocationController::class,
                'store',
            ]
        )
            ->middleware([
                'verified',
                'throttle:30,1',
            ])
            ->name(
                'current-user.courier-locations.store'
            );

        /*
        |--------------------------------------------------------------------------
        | Verified operational routes
        |--------------------------------------------------------------------------
        */

        Route::middleware('verified')
            ->group(function (): void {
                /*
                |--------------------------------------------------------------------------
                | Dashboard
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/dashboard',
                    DashboardController::class
                )->name('dashboard');

                /*
                |--------------------------------------------------------------------------
                | Users
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/users',
                    [
                        UserController::class,
                        'index',
                    ]
                )->name('users.index');

                Route::post(
                    '/users/staff',
                    [
                        UserController::class,
                        'storeStaff',
                    ]
                )->name('users.staff.store');

                Route::patch(
                    '/users/{user}/account-status',
                    [
                        UserController::class,
                        'updateAccountStatus',
                    ]
                )
                    ->whereNumber('user')
                    ->name(
                        'users.account-status.update'
                    );

                Route::patch(
                    '/users/{user}/role',
                    [
                        UserController::class,
                        'updateRole',
                    ]
                )
                    ->whereNumber('user')
                    ->name(
                        'users.role.update'
                    );

                Route::get(
                    '/users/{user}',
                    [
                        UserController::class,
                        'show',
                    ]
                )
                    ->whereNumber('user')
                    ->name('users.show');

                /*
                |--------------------------------------------------------------------------
                | Couriers
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/couriers',
                    [
                        CourierController::class,
                        'index',
                    ]
                )->name('couriers.index');

                Route::post(
                    '/couriers',
                    [
                        CourierController::class,
                        'store',
                    ]
                )->name('couriers.store');

                Route::patch(
                    '/couriers/{courier}/status',
                    [
                        CourierController::class,
                        'updateStatus',
                    ]
                )
                    ->whereNumber('courier')
                    ->name(
                        'couriers.status.update'
                    );

                Route::get(
                    '/couriers/{courier}/locations/latest',
                    [
                        CourierLocationController::class,
                        'latest',
                    ]
                )
                    ->whereNumber('courier')
                    ->name(
                        'couriers.locations.latest'
                    );

                Route::get(
                    '/couriers/{courier}',
                    [
                        CourierController::class,
                        'show',
                    ]
                )
                    ->whereNumber('courier')
                    ->name('couriers.show');

                /*
                |--------------------------------------------------------------------------
                | Vehicles
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/vehicles',
                    [
                        VehicleController::class,
                        'index',
                    ]
                )->name('vehicles.index');

                Route::post(
                    '/vehicles',
                    [
                        VehicleController::class,
                        'store',
                    ]
                )->name('vehicles.store');

                Route::patch(
                    '/vehicles/{vehicle}/status',
                    [
                        VehicleController::class,
                        'updateStatus',
                    ]
                )
                    ->whereNumber('vehicle')
                    ->name(
                        'vehicles.status.update'
                    );

                Route::get(
                    '/vehicles/{vehicle}',
                    [
                        VehicleController::class,
                        'show',
                    ]
                )
                    ->whereNumber('vehicle')
                    ->name('vehicles.show');

                /*
                |--------------------------------------------------------------------------
                | Shipment portal
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/portal/shipments',
                    ShipmentIndexPageController::class
                )->name(
                    'portal.shipments.index'
                );

                Route::get(
                    '/portal/shipments/create',
                    ShipmentCreatePageController::class
                )->name(
                    'portal.shipments.create'
                );

                Route::post(
                    '/portal/shipments',
                    [
                        PortalShipmentController::class,
                        'store',
                    ]
                )->name(
                    'portal.shipments.store'
                );

                Route::get(
                    '/portal/shipments/{shipment}/tracking',
                    ShipmentTrackingPageController::class
                )
                    ->whereNumber('shipment')
                    ->name(
                        'portal.shipments.tracking'
                    );

                Route::get(
                    '/portal/shipments/{shipment}',
                    ShipmentShowPageController::class
                )
                    ->whereNumber('shipment')
                    ->name(
                        'portal.shipments.show'
                    );

                /*
                |--------------------------------------------------------------------------
                | Shipment JSON endpoints
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/shipments',
                    [
                        ShipmentController::class,
                        'index',
                    ]
                )->name('shipments.index');

                Route::post(
                    '/shipments',
                    [
                        ShipmentController::class,
                        'store',
                    ]
                )->name('shipments.store');

                Route::patch(
                    '/shipments/{shipment}/status',
                    [
                        ShipmentController::class,
                        'updateStatus',
                    ]
                )
                    ->whereNumber('shipment')
                    ->name(
                        'shipments.status.update'
                    );

                Route::patch(
                    '/shipments/{shipment}/cancel',
                    [
                        ShipmentController::class,
                        'cancel',
                    ]
                )
                    ->whereNumber('shipment')
                    ->name('shipments.cancel');

                Route::get(
                    '/shipments/{shipment}/tracking',
                    [
                        ShipmentTrackingController::class,
                        'show',
                    ]
                )
                    ->whereNumber('shipment')
                    ->name('shipments.tracking');

                Route::get(
                    '/shipments/{shipment}/delivery-proof',
                    [
                        DeliveryProofController::class,
                        'show',
                    ]
                )
                    ->whereNumber('shipment')
                    ->name(
                        'shipments.delivery-proof.show'
                    );

                Route::get(
                    '/shipments/{shipment}/status-history',
                    [
                        ShipmentStatusHistoryController::class,
                        'index',
                    ]
                )
                    ->whereNumber('shipment')
                    ->name(
                        'shipments.status-history.index'
                    );

                Route::get(
                    '/shipments/{shipment}/packages',
                    [
                        ShipmentPackageController::class,
                        'index',
                    ]
                )
                    ->whereNumber('shipment')
                    ->name(
                        'shipments.packages.index'
                    );

                Route::post(
                    '/shipments/{shipment}/incidents',
                    [
                        IncidentController::class,
                        'store',
                    ]
                )
                    ->whereNumber('shipment')
                    ->name(
                        'shipments.incidents.store'
                    );

                Route::get(
                    '/shipments/{shipment}',
                    [
                        ShipmentController::class,
                        'show',
                    ]
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
                    [
                        RouteController::class,
                        'index',
                    ]
                )->name('routes.index');

                Route::post(
                    '/routes',
                    [
                        RouteController::class,
                        'store',
                    ]
                )->name('routes.store');

                Route::patch(
                    '/routes/{route}/activate',
                    [
                        RouteController::class,
                        'activate',
                    ]
                )
                    ->whereNumber('route')
                    ->name('routes.activate');

                Route::patch(
                    '/routes/{route}/complete',
                    [
                        RouteController::class,
                        'complete',
                    ]
                )
                    ->whereNumber('route')
                    ->name('routes.complete');

                Route::patch(
                    '/routes/{route}/cancel',
                    [
                        RouteController::class,
                        'cancel',
                    ]
                )
                    ->whereNumber('route')
                    ->name('routes.cancel');

                Route::get(
                    '/routes/{route}/map',
                    [
                        RouteMapController::class,
                        'show',
                    ]
                )
                    ->whereNumber('route')
                    ->name('routes.map');

                Route::get(
                    '/routes/{route}/map/view',
                    [
                        RouteMapPageController::class,
                        'show',
                    ]
                )
                    ->whereNumber('route')
                    ->name('routes.map.view');

                Route::get(
                    '/routes/{route}',
                    [
                        RouteController::class,
                        'show',
                    ]
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
                    [
                        RouteShipmentController::class,
                        'failAttempt',
                    ]
                )
                    ->whereNumber(
                        'routeShipment'
                    )
                    ->name(
                        'route-shipments.fail-attempt'
                    );

                /*
                |--------------------------------------------------------------------------
                | Delivery services
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/delivery-services',
                    [
                        DeliveryServiceController::class,
                        'index',
                    ]
                )->name(
                    'delivery-services.index'
                );

                Route::patch(
                    '/delivery-services/{deliveryService}/assign',
                    [
                        DeliveryServiceController::class,
                        'assign',
                    ]
                )
                    ->whereNumber(
                        'deliveryService'
                    )
                    ->name(
                        'delivery-services.assign'
                    );

                Route::patch(
                    '/delivery-services/{deliveryService}/complete',
                    [
                        DeliveryServiceController::class,
                        'complete',
                    ]
                )
                    ->whereNumber(
                        'deliveryService'
                    )
                    ->name(
                        'delivery-services.complete'
                    );

                Route::post(
                    '/delivery-services/{deliveryService}/ratings',
                    [
                        RatingController::class,
                        'store',
                    ]
                )
                    ->whereNumber(
                        'deliveryService'
                    )
                    ->name(
                        'delivery-services.ratings.store'
                    );

                Route::get(
                    '/delivery-services/{deliveryService}',
                    [
                        DeliveryServiceController::class,
                        'show',
                    ]
                )
                    ->whereNumber(
                        'deliveryService'
                    )
                    ->name(
                        'delivery-services.show'
                    );

                /*
                |--------------------------------------------------------------------------
                | Payments
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/payments',
                    [
                        PaymentController::class,
                        'index',
                    ]
                )->name('payments.index');

                Route::patch(
                    '/payments/{payment}/confirm',
                    [
                        PaymentController::class,
                        'confirm',
                    ]
                )
                    ->whereNumber('payment')
                    ->name('payments.confirm');

                Route::patch(
                    '/payments/{payment}/refund',
                    [
                        PaymentController::class,
                        'refund',
                    ]
                )
                    ->whereNumber('payment')
                    ->name('payments.refund');

                Route::get(
                    '/payments/{payment}',
                    [
                        PaymentController::class,
                        'show',
                    ]
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
                    [
                        RatingController::class,
                        'index',
                    ]
                )->name('ratings.index');

                Route::get(
                    '/ratings/{rating}',
                    [
                        RatingController::class,
                        'show',
                    ]
                )
                    ->whereNumber('rating')
                    ->name('ratings.show');

                /*
                |--------------------------------------------------------------------------
                | Notification portal
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/portal/notifications',
                    AppNotificationIndexPageController::class
                )->name(
                    'portal.notifications.index'
                );

                Route::patch(
                    '/portal/notifications/read-all',
                    [
                        PortalAppNotificationController::class,
                        'markAllAsRead',
                    ]
                )->name(
                    'portal.notifications.read-all'
                );

                Route::patch(
                    '/portal/notifications/{appNotification}/read',
                    [
                        PortalAppNotificationController::class,
                        'markAsRead',
                    ]
                )
                    ->whereNumber(
                        'appNotification'
                    )
                    ->name(
                        'portal.notifications.read'
                    );

                /*
                |--------------------------------------------------------------------------
                | Notification JSON endpoints
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/notifications',
                    [
                        AppNotificationController::class,
                        'index',
                    ]
                )->name('notifications.index');

                Route::patch(
                    '/notifications/read-all',
                    [
                        AppNotificationController::class,
                        'markAllAsRead',
                    ]
                )->name(
                    'notifications.read-all'
                );

                Route::patch(
                    '/notifications/{appNotification}/read',
                    [
                        AppNotificationController::class,
                        'markAsRead',
                    ]
                )
                    ->whereNumber(
                        'appNotification'
                    )
                    ->name(
                        'notifications.read'
                    );

                Route::get(
                    '/notifications/{appNotification}',
                    [
                        AppNotificationController::class,
                        'show',
                    ]
                )
                    ->whereNumber(
                        'appNotification'
                    )
                    ->name(
                        'notifications.show'
                    );

                /*
                |--------------------------------------------------------------------------
                | Incidents
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/incidents',
                    [
                        IncidentController::class,
                        'index',
                    ]
                )->name('incidents.index');

                Route::patch(
                    '/incidents/{incident}/status',
                    [
                        IncidentController::class,
                        'updateStatus',
                    ]
                )
                    ->whereNumber('incident')
                    ->name(
                        'incidents.status.update'
                    );

                Route::get(
                    '/incidents/{incident}',
                    [
                        IncidentController::class,
                        'show',
                    ]
                )
                    ->whereNumber('incident')
                    ->name('incidents.show');

                /*
                |--------------------------------------------------------------------------
                | Support ticket portal
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/portal/support-tickets',
                    SupportTicketIndexPageController::class
                )->name(
                    'portal.support-tickets.index'
                );

                /*
                |--------------------------------------------------------------------------
                | Support ticket JSON endpoints
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/support-tickets',
                    [
                        SupportTicketController::class,
                        'index',
                    ]
                )->name(
                    'support-tickets.index'
                );

                Route::post(
                    '/support-tickets',
                    [
                        SupportTicketController::class,
                        'store',
                    ]
                )->name(
                    'support-tickets.store'
                );

                Route::patch(
                    '/support-tickets/{supportTicket}/assign',
                    [
                        SupportTicketController::class,
                        'assign',
                    ]
                )
                    ->whereNumber(
                        'supportTicket'
                    )
                    ->name(
                        'support-tickets.assign'
                    );

                Route::post(
                    '/support-tickets/{supportTicket}/messages',
                    [
                        SupportTicketController::class,
                        'addMessage',
                    ]
                )
                    ->whereNumber(
                        'supportTicket'
                    )
                    ->name(
                        'support-tickets.messages.store'
                    );

                Route::patch(
                    '/support-tickets/{supportTicket}/messages/read',
                    SupportTicketMessageReadController::class
                )
                    ->whereNumber(
                        'supportTicket'
                    )
                    ->name(
                        'support-tickets.messages.read'
                    );

                Route::patch(
                    '/support-tickets/{supportTicket}/status',
                    [
                        SupportTicketController::class,
                        'updateStatus',
                    ]
                )
                    ->whereNumber(
                        'supportTicket'
                    )
                    ->name(
                        'support-tickets.status.update'
                    );

                Route::get(
                    '/support-tickets/{supportTicket}',
                    [
                        SupportTicketController::class,
                        'show',
                    ]
                )
                    ->whereNumber(
                        'supportTicket'
                    )
                    ->name(
                        'support-tickets.show'
                    );

                /*
                |--------------------------------------------------------------------------
                | Recharges
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/recharges',
                    [
                        RechargeController::class,
                        'index',
                    ]
                )->name('recharges.index');

                Route::post(
                    '/recharges',
                    [
                        RechargeController::class,
                        'store',
                    ]
                )->name('recharges.store');

                Route::get(
                    '/recharges/{recharge}',
                    [
                        RechargeController::class,
                        'show',
                    ]
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
                    [
                        RechargePackageController::class,
                        'index',
                    ]
                )->name(
                    'recharge-packages.index'
                );

                Route::get(
                    '/recharge-packages/{rechargePackage}',
                    [
                        RechargePackageController::class,
                        'show',
                    ]
                )
                    ->whereNumber(
                        'rechargePackage'
                    )
                    ->name(
                        'recharge-packages.show'
                    );

                /*
                |--------------------------------------------------------------------------
                | Trips
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/trips',
                    [
                        TripController::class,
                        'index',
                    ]
                )->name('trips.index');

                Route::get(
                    '/trips/{trip}',
                    [
                        TripController::class,
                        'show',
                    ]
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
                    [
                        TripTransactionController::class,
                        'index',
                    ]
                )->name(
                    'trip-transactions.index'
                );

                Route::get(
                    '/trip-transactions/{tripTransaction}',
                    [
                        TripTransactionController::class,
                        'show',
                    ]
                )
                    ->whereNumber(
                        'tripTransaction'
                    )
                    ->name(
                        'trip-transactions.show'
                    );

                /*
                |--------------------------------------------------------------------------
                | Audit logs
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/audit-logs',
                    [
                        AuditLogController::class,
                        'index',
                    ]
                )->name('audit-logs.index');

                Route::get(
                    '/audit-logs/{auditLog}',
                    [
                        AuditLogController::class,
                        'show',
                    ]
                )
                    ->whereNumber('auditLog')
                    ->name('audit-logs.show');
            });
    });

/*
|--------------------------------------------------------------------------
| Optional authentication routes
|--------------------------------------------------------------------------
*/

if (file_exists(__DIR__.'/auth.php')) {
    require __DIR__.'/auth.php';
}