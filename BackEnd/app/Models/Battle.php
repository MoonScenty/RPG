<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Battle extends Model
{
    protected $fillable = ['user_id', 'status', 'winner_side', 'turn_number'];

    public function units()
    {
        return $this->hasMany(BattleUnit::class);
    }

    public function logs()
    {
        return $this->hasMany(BattleLog::class)->orderBy('id');
    }
}
