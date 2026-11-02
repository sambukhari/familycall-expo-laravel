<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FamilyAdminEvent extends Model {
 protected $fillable=['actor','action','device_id','details'];
 protected $casts=['details'=>'array'];
}
