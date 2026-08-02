<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name', 'type', 'class_id', 'mz_enemy_id', 'mz_actor_id', 'max_hp', 'max_mp', 'atk', 'def', 'mat', 'mdf', 'spd', 'luk',
        'sprite', 'hud_sprite', 'attack_motion', 'weapon_animation_id', 'equip_traits',
        'dragonbones_skeleton', 'dragonbones_atlas', 'dragonbones_motions', 'dragonbones_scale',
    ];

    protected $casts = ['dragonbones_motions' => 'array', 'equip_traits' => 'array'];

    public function mzClass()
    {
        return $this->belongsTo(MzClass::class, 'class_id');
    }

    public function mzEnemy()
    {
        return $this->belongsTo(MzEnemy::class, 'mz_enemy_id');
    }

    public function mzActor()
    {
        return $this->belongsTo(MzActor::class, 'mz_actor_id');
    }

    /**
     * Actors.json에 원래 들려있던 기본 무기(equips[0]) id - 없으면(mz_actor_id
     * 미설정이거나 equips[0]이 0) null. MercenaryController::purchase()(신규 영입)와
     * EquipmentBackfillSeeder(이 필드가 생기기 전에 이미 영입된 기존 계정 보정) 둘
     * 다에서 쓰는 공용 로직이라 모델에 둔다.
     */
    public function defaultWeaponId(): ?int
    {
        if ($this->mz_actor_id === null) {
            return null;
        }

        $weaponId = $this->mzActor?->equips[0] ?? 0;

        return $weaponId > 0 ? $weaponId : null;
    }
}
