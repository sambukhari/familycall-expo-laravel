<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CallSignal extends Model {
 protected $fillable=['sender_id','kind','payload'];
 protected $casts=['payload'=>'array'];
 public function call(){return $this->belongsTo(FamilyCall::class,'family_call_id');}
}
