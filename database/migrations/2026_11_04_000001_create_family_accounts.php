<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->timestamps();
        });

        Schema::create('family_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->nullable()->constrained('families')->nullOnDelete();
            $table->string('username', 80)->unique();
            $table->string('password');
            $table->string('device_type', 20);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('family_account_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_account_id')->constrained('family_accounts')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_account_tokens');
        Schema::dropIfExists('family_accounts');
        Schema::dropIfExists('families');
    }
};
