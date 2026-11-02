<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('family_devices',function(Blueprint $t){$t->id();$t->string('device_id',80)->unique();$t->string('label',120)->nullable();$t->boolean('authorized')->default(true);$t->timestamp('last_seen_at')->nullable()->index();$t->string('platform',40)->nullable();$t->timestamps();});
  Schema::create('family_messages',function(Blueprint $t){$t->id();$t->string('sender_id',80)->index();$t->string('recipient_id',80)->index();$t->text('body');$t->timestamp('read_at')->nullable();$t->timestamps();});
  Schema::create('family_device_commands',function(Blueprint $t){$t->id();$t->string('device_id',80)->index();$t->string('type',40);$t->json('payload')->nullable();$t->timestamp('delivered_at')->nullable();$t->timestamp('acknowledged_at')->nullable();$t->timestamps();});
  Schema::create('family_admin_events',function(Blueprint $t){$t->id();$t->string('actor',120);$t->string('action',90)->index();$t->string('device_id',80)->nullable();$t->json('details')->nullable();$t->timestamps();});
 }
 public function down():void {Schema::dropIfExists('family_admin_events');Schema::dropIfExists('family_device_commands');Schema::dropIfExists('family_messages');Schema::dropIfExists('family_devices');}
};
