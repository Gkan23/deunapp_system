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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')
                ->constrained('couriers')
                ->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')
                ->constrained('vehicle_types')
                ->restrictOnDelete();
            $table->foreignId('vehicle_status_id')
                ->constrained('vehicle_statuses')
                ->restrictOnDelete();
            $table->string('plate_number', 20)->unique();
            $table->string('brand', 80)->nullable();
            $table->string('model', 80)->nullable();
            $table->string('color', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
