<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BattleUnit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'battle_id', 'unit_id', 'side', 'slot', 'class_id',
        'max_hp', 'max_mp', 'atk', 'def', 'mat', 'mdf', 'spd', 'luk', 'attack_motion', 'weapon_effect_name', 'equip_traits',
        'dragonbones_skeleton', 'dragonbones_atlas', 'dragonbones_motions', 'dragonbones_scale',
        'current_hp', 'current_mp',
        'casting_skill_id', 'casting_target_battle_unit_id', 'casting_turns_remaining', 'skill_cooldowns',
        'atb_gauge',
    ];

    protected $casts = ['skill_cooldowns' => 'array', 'dragonbones_motions' => 'array', 'equip_traits' => 'array'];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function mzClass()
    {
        return $this->belongsTo(MzClass::class, 'class_id');
    }

    public function states()
    {
        return $this->hasMany(BattleUnitState::class);
    }

    public function isAlive(): bool
    {
        return $this->current_hp > 0;
    }

    public function isCasting(): bool
    {
        return ($this->casting_turns_remaining ?? 0) > 0;
    }

    /** 스킬이 아직 쿨다운 중인지 - skill_cooldowns는 {skill_id: 사용가능해지는 turn_number}. */
    public function skillOnCooldown(int $skillId, int $currentTurn): bool
    {
        $readyAt = ($this->skill_cooldowns ?? [])[$skillId] ?? null;

        return $readyAt !== null && $currentTurn < $readyAt;
    }
}
