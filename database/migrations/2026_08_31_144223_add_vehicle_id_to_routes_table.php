<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el vehículo asignado a una ruta.
     *
     * Se mantiene nullable para conservar compatibilidad
     * con las rutas creadas antes de este cambio.
     */
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->foreignId('vehicle_id')
                ->nullable()
                ->after('courier_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            $table->index(
                [
                    'vehicle_id',
                    'route_status_id',
                ],
                'routes_vehicle_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropIndex(
                'routes_vehicle_status_index'
            );

            $table->dropForeign([
                'vehicle_id',
            ]);

            $table->dropColumn(
                'vehicle_id'
            );
        });
    }
};