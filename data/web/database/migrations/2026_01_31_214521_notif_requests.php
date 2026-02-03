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
        Schema::create('notif_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->string('idempotency_key')->nullable();
            $table->string('correlation_id');
            $table->text('template_subject')->nullable();
            $table->string('template_body_path')->nullable();
            $table->text('template_body_inline')->nullable();
            $table->integer('requested_count')->default(0);
            $table->integer('accepted_count')->default(0);
            $table->integer('pending_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('cancelled_count')->default(0);
            $table->string('channel');
            $table->string('priority');
            $table->string('status');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('client_id');
            $table->index('idempotency_key');
            $table->index('correlation_id');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('created_at');
            $table->unique(['client_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notif_requests');
    }
};
