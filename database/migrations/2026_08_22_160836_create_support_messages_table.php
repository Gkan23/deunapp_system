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
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')
                ->constrained('support_tickets')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('message_text');
            $table->string('attachment_url', 500)->nullable();
            $table->timestamp('sent_at');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
