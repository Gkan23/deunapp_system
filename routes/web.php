<?php

use App\Http\Controllers\ShipmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Página principal de DeUnapp.
|
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| Rutas protegidas
|--------------------------------------------------------------------------
|
| Solamente los usuarios autenticados pueden acceder.
| StoreShipmentRequest comprobará además que el usuario
| sea un cliente autorizado para crear envíos.
|
*/

Route::middleware('auth')->group(function (): void {
    Route::post(
        '/shipments',
        [ShipmentController::class, 'store']
    )->name('shipments.store');
});