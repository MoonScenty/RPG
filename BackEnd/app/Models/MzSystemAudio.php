<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzSystemAudio extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['id', 'battle_bgm', 'victory_me', 'defeat_me', 'attack_motions'];
    protected $casts = ['attack_motions' => 'array'];

    /** 항상 id=1 한 행만 존재하는 전역 설정 - 시더가 만들어둔 그 행을 그대로 반환. */
    public static function current(): ?self
    {
        return self::find(1);
    }

    /**
     * System.json attackMotions[wtypeId].type을 SV 배틀러 모션 이름으로 변환
     * (rmmz_objects.js의 Game_Actor.performAttack() 기준: 0=thrust, 1=swing,
     * 2=missile). 기본 공격 모션은 thrust - 못 찾아도 thrust로 처리한다.
     */
    public function motionForWeaponType(int $wtypeId): string
    {
        $type = $this->attack_motions[$wtypeId]['type'] ?? 0;

        return match ($type) {
            1 => 'swing',
            2 => 'missile',
            default => 'thrust',
        };
    }
}
