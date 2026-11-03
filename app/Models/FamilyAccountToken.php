<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyAccountToken extends Model
{
    protected $fillable = ['family_account_id', 'token_hash', 'last_used_at', 'expires_at'];
    protected $hidden = ['token_hash'];
    protected $casts = ['last_used_at' => 'datetime', 'expires_at' => 'datetime'];

    public function account()
    {
        return $this->belongsTo(FamilyAccount::class, 'family_account_id');
    }
}
