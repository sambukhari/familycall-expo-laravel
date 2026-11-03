<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Family extends Model
{
    protected $fillable = ['name'];

    public function accounts()
    {
        return $this->hasMany(FamilyAccount::class);
    }
}
