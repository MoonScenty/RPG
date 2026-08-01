<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzTroopMember extends Model
{
    public $timestamps = false;

    protected $fillable = ['troop_id', 'enemy_id', 'position'];

    public function enemy()
    {
        return $this->belongsTo(MzEnemy::class, 'enemy_id');
    }
}
