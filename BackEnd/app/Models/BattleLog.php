<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BattleLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'battle_id', 'turn_number', 'actor_battle_unit_id', 'target_battle_unit_id',
        'action_type', 'damage', 'is_critical', 'hit_outcome', 'target_hp_after', 'targets', 'circle_image_name', 'circle_image_scale',
        'skill_motion', 'skill_animation_id', 'item_name',
    ];

    protected $casts = [
        'targets' => 'array',
        'is_critical' => 'boolean',
    ];

    public function actor()
    {
        return $this->belongsTo(BattleUnit::class, 'actor_battle_unit_id');
    }

    public function target()
    {
        return $this->belongsTo(BattleUnit::class, 'target_battle_unit_id');
    }
}
