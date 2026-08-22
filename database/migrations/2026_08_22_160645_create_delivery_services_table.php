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
        Schema::create('delivery_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();
            $table->foreignId('trip_id')
                ->nullable()
                ->unique()
                ->constrained('trips')
                ->nullOnDelete();
            $table->foreignId('shipment_id')
                ->unique()
                ->constrained('shipments')
                ->restrictOnDelete();
            $table->foreignId('service_type_id')
                ->constrained('service_types')
                ->restrictOnDelete();
            $table->foreignId('trip_type_id')
                ->constrained('trip_types')
                ->restrictOnDelete();
            $table->enum('status', [
                'REQUESTED',
                'ASSIGNED',
                'IN_PROGRESS',
                'COMPLETED',
                'CANCELLED',
            ])->default('REQUESTED');
            $table->timestamp('requested_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->decimal('delivery_fee', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_services');
    }
};
