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
        Schema::create('route_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')
                ->constrained('routes')
                ->cascadeOnDelete();
            $table->foreignId('shipment_id')
                ->constrained('shipments')
                ->restrictOnDelete();
            $table->unsignedInteger('delivery_order');
            $table->enum('delivery_status', [
                'PENDING',
                'IN_PROGRESS',
                'DELIVERED',
                'FAILED',
            ])->default('PENDING');
            $table->timestamps();
            $table->unique([
                'route_id',
                'shipment_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_shipments');
    }
};
