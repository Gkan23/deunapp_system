<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->timestamp('read_at')
                ->nullable()
                ->after('is_read');
        });

        /*
         * Backfill for notifications that were already marked as read
         * before the read_at column existed.
         */
        DB::table('app_notifications')
            ->where('is_read', true)
            ->whereNull('read_at')
            ->update([
                'read_at' => DB::raw(
                    'COALESCE(sent_at, updated_at, created_at)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};
