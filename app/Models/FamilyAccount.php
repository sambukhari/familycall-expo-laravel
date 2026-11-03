<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyAccount extends Model
{
    protected $fillable = ['family_id', 'username', 'password', 'device_type', 'active'];
    protected $hidden = ['password'];
    protected $casts = ['active' => 'boolean', 'family_id' => 'integer', 'password' => 'hashed'];

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function tokens()
    {
        return $this->hasMany(FamilyAccountToken::class);
    }
}
