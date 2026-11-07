<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CameraOwnerKey extends Model
{
    protected $guarded = [];

    protected $hidden = ['secret', 'last_counter'];

    protected $casts = ['secret' => 'encrypted', 'confirmed_at' => 'datetime', 'last_counter' => 'integer'];
}
