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
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')
                ->constrained('shipments')
                ->cascadeOnDelete();
            $table->foreignId('reported_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('incident_type_id')
                ->constrained('incident_types')
                ->restrictOnDelete();
            $table->foreignId('incident_status_id')
                ->constrained('incident_statuses')
                ->restrictOnDelete();
            $table->text('description');
            $table->timestamp('reported_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
