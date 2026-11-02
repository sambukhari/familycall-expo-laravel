<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FamilyMessage extends Model {
 protected $fillable=['sender_id','recipient_id','body','read_at'];
 protected $casts=['read_at'=>'datetime'];
}
