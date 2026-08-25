<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `route_shipments`
            MODIFY `delivery_status`
            ENUM(
                'PENDING',
                'IN_PROGRESS',
                'DELIVERED',
                'FAILED',
                'CANCELLED'
            )
            NOT NULL DEFAULT 'PENDING'"
        );
    }

    public function down(): void
    {
        /*
         * MySQL cannot remove CANCELLED from the enum while records
         * still contain that value.
         */
        DB::table('route_shipments')
            ->where('delivery_status', 'CANCELLED')
            ->update([
                'delivery_status' => 'FAILED',
            ]);

        DB::statement(
            "ALTER TABLE `route_shipments`
            MODIFY `delivery_status`
            ENUM(
                'PENDING',
                'IN_PROGRESS',
                'DELIVERED',
                'FAILED'
            )
            NOT NULL DEFAULT 'PENDING'"
        );
    }
};