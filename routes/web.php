<?php

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

    Route::get(
        '/shipments/{shipment}',
        [ShipmentController::class, 'show']
    )
        ->whereNumber('shipment')
        ->name('shipments.show');
});
