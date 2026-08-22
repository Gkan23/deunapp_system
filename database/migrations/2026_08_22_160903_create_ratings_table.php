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
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_service_id')
                ->unique()
                ->constrained('delivery_services')
                ->restrictOnDelete();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();
            $table->unsignedTinyInteger('punctuality_score');
            $table->unsignedTinyInteger('customer_service_score');
            $table->unsignedTinyInteger('package_condition_score');
            $table->decimal('overall_score', 3, 2);
            $table->text('comment')->nullable();
            $table->timestamp('rated_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
