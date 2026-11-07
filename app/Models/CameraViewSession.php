<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CameraViewSession extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['viewer_hash'];

    protected $casts = ['offer' => 'array', 'answer' => 'array', 'viewer_seen_at' => 'datetime', 'expires_at' => 'datetime', 'ended_at' => 'datetime'];

    public function device()
    {
        return $this->belongsTo(CameraDevice::class, 'camera_device_id');
    }

    public function signals()
    {
        return $this->hasMany(CameraSignal::class);
    }
}
