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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_provider_id')
                ->constrained('delivery_providers')
                ->restrictOnDelete();
            $table->foreignId('trip_type_id')
                ->constrained('trip_types')
                ->restrictOnDelete();
            $table->enum('status', [
                'AVAILABLE',
                'USED',
                'CANCELLED',
            ])->default('AVAILABLE');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index([
                'delivery_provider_id',
                'trip_type_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
