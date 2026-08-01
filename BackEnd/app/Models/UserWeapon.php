<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWeapon extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'mz_weapon_id', 'quantity'];

    public function weapon()
    {
        return $this->belongsTo(MzWeapon::class, 'mz_weapon_id');
    }
}
