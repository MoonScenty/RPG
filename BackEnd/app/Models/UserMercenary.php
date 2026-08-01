<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMercenary extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'unit_id', 'active_gambit_preset',
        'weapon_id', 'shield_id', 'body_armor_id', 'accessory_id',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function weapon()
    {
        return $this->belongsTo(MzWeapon::class, 'weapon_id');
    }

    public function shield()
    {
        return $this->belongsTo(MzArmor::class, 'shield_id');
    }

    public function bodyArmor()
    {
        return $this->belongsTo(MzArmor::class, 'body_armor_id');
    }

    public function accessory()
    {
        return $this->belongsTo(MzArmor::class, 'accessory_id');
    }
}
