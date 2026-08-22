<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_provider_id')
                ->constrained('delivery_providers')
                ->restrictOnDelete();
            $table->foreignId('recharge_package_id')
                ->constrained('recharge_packages')
                ->restrictOnDelete();
            /*
            \* Datos históricos de la recarga.
            \* Se conservan aunque posteriormente cambie
            \* la configuración del paquete.
            */
            $table->foreignId('trip_type_id')
                ->constrained('trip_types')
                ->restrictOnDelete();
            $table->unsignedInteger('trip_quantity');
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('amount', 10, 2);
            $table->string('payment_reference', 100)->nullable();
            $table->timestamp('recharged_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recharges');
    }
};
