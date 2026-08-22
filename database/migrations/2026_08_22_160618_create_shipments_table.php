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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_code', 50)->unique();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();
            $table->foreignId('sender_id')
                ->constrained('shipment_people')
                ->restrictOnDelete();
            $table->foreignId('recipient_id')
                ->constrained('shipment_people')
                ->restrictOnDelete();
            $table->foreignId('origin_address_id')
                ->constrained('addresses')
                ->restrictOnDelete();
            $table->foreignId('destination_address_id')
                ->constrained('addresses')
                ->restrictOnDelete();
            $table->foreignId('origin_branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();
            $table->foreignId('destination_branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();
            $table->foreignId('shipment_status_id')
                ->constrained('shipment_statuses')
                ->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->decimal('declared_value', 12, 2)->nullable();
            $table->text('delivery_instructions')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
