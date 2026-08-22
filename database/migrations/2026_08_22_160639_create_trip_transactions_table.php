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
        Schema::create('trip_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_provider_id')
                ->constrained('delivery_providers')
                ->restrictOnDelete();
            $table->foreignId('recharge_id')
                ->nullable()
                ->constrained('recharges')
                ->nullOnDelete();
            $table->foreignId('trip_id')
                ->nullable()
                ->constrained('trips')
                ->nullOnDelete();
            $table->enum('transaction_type', [
                'CREDIT',
                'DEBIT',
            ]);
            $table->unsignedInteger('quantity');
            $table->text('description')->nullable();
            $table->timestamp('transaction_at');
            $table->timestamps();
            $table->index([
                'delivery_provider_id',
                'transaction_type',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_transactions');
    }
};
