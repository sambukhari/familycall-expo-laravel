<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camera_owner_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('secret');
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('last_counter')->nullable();
            $table->timestamps();
        });
        Schema::create('camera_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('family_account_id')->nullable()->constrained('family_accounts')->nullOnDelete();
            $table->string('label', 100);
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('pair_code_hash', 64)->nullable()->unique();
            $table->timestamp('pair_expires_at')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('armed')->default(false);
            $table->boolean('camera_available')->default(false);
            $table->string('agent_state', 30)->default('unpaired');
            $table->timestamps();
        });
        Schema::create('camera_view_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('camera_device_id')->constrained('camera_devices')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('viewer_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->json('offer')->nullable();
            $table->json('answer')->nullable();
            $table->timestamp('viewer_seen_at');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 40)->nullable();
            $table->timestamps();
            $table->index(['camera_device_id', 'status']);
        });
        Schema::create('camera_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('camera_view_session_id')->constrained('camera_view_sessions')->cascadeOnDelete();
            $table->string('sender', 10);
            $table->uuid('nonce');
            $table->json('payload');
            $table->timestamps();
            $table->unique(['camera_view_session_id', 'sender', 'nonce'], 'camera_signal_nonce');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camera_signals');
        Schema::dropIfExists('camera_view_sessions');
        Schema::dropIfExists('camera_devices');
        Schema::dropIfExists('camera_owner_keys');
    }
};
