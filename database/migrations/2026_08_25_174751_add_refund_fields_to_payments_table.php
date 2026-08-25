<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('refund_reference', 150)
                ->nullable()
                ->after('payment_reference');

            $table->text('refund_reason')
                ->nullable()
                ->after('refund_reference');

            $table->timestamp('refunded_at')
                ->nullable()
                ->after('paid_at');

            $table->unique(
                'refund_reference',
                'payments_refund_reference_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(
                'payments_refund_reference_unique'
            );

            $table->dropColumn([
                'refund_reference',
                'refund_reason',
                'refunded_at',
            ]);
        });
    }
};

