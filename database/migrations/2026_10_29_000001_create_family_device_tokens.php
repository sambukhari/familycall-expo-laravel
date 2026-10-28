<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('family_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('device_id',80)->index();
            $table->string('token', 512)->unique();
            $table->string('platform', 20)->default('android');
            $table->timestamps();
        });
    }
    public function down(): void {Schema::dropIfExists('family_device_tokens');}
};
