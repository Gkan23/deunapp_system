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
        Schema::table('recharges', function (Blueprint $table) {
            $table->unique(
                'payment_reference',
                'recharges_payment_reference_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('recharges', function (Blueprint $table) {
            $table->dropUnique(
                'recharges_payment_reference_unique'
            );
        });
    }
};
