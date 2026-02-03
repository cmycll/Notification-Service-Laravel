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
        Schema::create('notif_request_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->string('to');
            $table->json('vars');
            $table->string('channel');
            $table->string('priority');
            $table->string('status');
            $table->string('delivery_state');
            $table->integer('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->index('request_id');
            $table->index('channel');
            $table->index('priority');
            $table->index('status');
            $table->index('delivery_state');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notif_request_notifications');
    }
};
