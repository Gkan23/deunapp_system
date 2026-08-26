<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->string('deduplication_key', 150)
                ->nullable()
                ->after('notification_type_id');

            $table->unique(
                'deduplication_key',
                'app_notifications_deduplication_key_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropUnique(
                'app_notifications_deduplication_key_unique'
            );

            $table->dropColumn('deduplication_key');
        });
    }
};

