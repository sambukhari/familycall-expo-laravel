<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CameraSignal extends Model
{
    protected $guarded = [];

    protected $casts = ['payload' => 'array'];
}
