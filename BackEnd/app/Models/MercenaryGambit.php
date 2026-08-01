<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MercenaryGambit extends Model
{
    public const PRESET_COUNT = 5;

    public $timestamps = false;

    protected $fillable = [
        'user_mercenary_id', 'preset_number', 'priority',
        'condition_kind', 'condition_scope', 'condition_stat', 'condition_value_type',
        'condition_operator', 'condition_value', 'condition_debuff_key', 'condition_position',
        'action_type', 'action_skill_key',
    ];
}
