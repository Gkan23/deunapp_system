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
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')
                ->constrained('couriers')
                ->restrictOnDelete();
            $table->foreignId('route_status_id')
                ->constrained('route_statuses')
                ->restrictOnDelete();
            $table->date('route_date');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->decimal('estimated_distance_km', 10, 2)->nullable();
            $table->timestamps();
            $table->index([
                'courier_id',
                'route_status_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
