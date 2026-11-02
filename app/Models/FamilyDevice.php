<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FamilyDevice extends Model {
 protected $fillable=['device_id','label','authorized','last_seen_at','platform'];
 protected $casts=['authorized'=>'boolean','last_seen_at'=>'datetime'];
}
