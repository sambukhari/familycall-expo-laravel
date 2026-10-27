<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FamilyCall extends Model {
 protected $fillable=['caller_id','callee_id','status','offer','answer'];
 protected $casts=['offer'=>'array','answer'=>'array'];
 public function signals(){return $this->hasMany(CallSignal::class,'family_call_id');}
}
