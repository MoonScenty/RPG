<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserArmor extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'mz_armor_id', 'quantity'];

    public function armor()
    {
        return $this->belongsTo(MzArmor::class, 'mz_armor_id');
    }
}
