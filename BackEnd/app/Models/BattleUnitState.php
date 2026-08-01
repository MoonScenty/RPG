<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BattleUnitState extends Model
{
    public $timestamps = false;

    protected $fillable = ['battle_unit_id', 'state_id', 'remaining_turns', 'applied_turn'];

    public function battleUnit()
    {
        return $this->belongsTo(BattleUnit::class);
    }

    public function state()
    {
        return $this->belongsTo(MzState::class, 'state_id');
    }
}
