<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FamilyDeviceCommand extends Model {
 protected $fillable=['device_id','type','payload','delivered_at','acknowledged_at'];
 protected $casts=['payload'=>'array','delivered_at'=>'datetime','acknowledged_at'=>'datetime'];
}
