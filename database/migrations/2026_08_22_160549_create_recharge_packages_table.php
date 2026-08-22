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
        Schema::create('recharge_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_rule_id')
                ->constrained('commission_rules')
                ->restrictOnDelete();
            $table->string('package_name', 100);
            $table->unsignedInteger('trip_quantity');
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recharge_packages');
    }
};
