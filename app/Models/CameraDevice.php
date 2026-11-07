<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CameraDevice extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['token_hash', 'pair_code_hash'];

    protected $casts = [
        'armed' => 'boolean', 'camera_available' => 'boolean',
        'pair_expires_at' => 'datetime', 'paired_at' => 'datetime',
        'revoked_at' => 'datetime', 'last_seen_at' => 'datetime',
    ];

    public function sessions()
    {
        return $this->hasMany(CameraViewSession::class);
    }

    public function account()
    {
        return $this->belongsTo(FamilyAccount::class, 'family_account_id');
    }
}
