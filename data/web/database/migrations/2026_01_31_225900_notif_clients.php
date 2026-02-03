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
        Schema::create('notif_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username');
            $table->string('email');
            $table->string('password_hash');
            $table->string('api_key_hash')->nullable();
            $table->string('status');
            $table->integer('quota_per_day')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->index('status');
            $table->unique('api_key_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notif_clients');
    }
};
