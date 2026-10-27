<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('family_calls',function(Blueprint $t){
   $t->id();$t->string('caller_id');$t->string('callee_id');$t->string('status');
   $t->json('offer');$t->json('answer')->nullable();$t->timestamps();
  });
  Schema::create('call_signals',function(Blueprint $t){
   $t->id();$t->foreignId('family_call_id')->constrained('family_calls')->cascadeOnDelete();
   $t->string('sender_id');$t->string('kind');$t->json('payload');$t->timestamps();
  });
 }
 public function down():void {Schema::dropIfExists('call_signals');Schema::dropIfExists('family_calls');}
};
