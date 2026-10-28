<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FamilyDeviceToken extends Model {
    protected $fillable = ['device_id','token','platform'];
    protected $hidden = ['token'];
}
