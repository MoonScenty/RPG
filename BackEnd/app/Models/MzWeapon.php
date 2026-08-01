<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzWeapon extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'name', 'wtype_id', 'etype_id', 'animation_id', 'icon_index',
        'price', 'description', 'note', 'params', 'traits', 'tags',
    ];

    protected $casts = ['params' => 'array', 'traits' => 'array', 'tags' => 'array'];

    /**
     * params([MHP,MMP,ATK,DEF,MAT,MDF,AGI,LUK], MZ 표준 순서)를 MzClass::statAtLevel()과
     * 같은 키로 변환 - ActorSeeder가 직업 곡선 스탯에 이 보너스를 더해서 units에 최종
     * 스냅샷을 저장한다.
     *
     * @return array{max_hp:int,max_mp:int,atk:int,def:int,mat:int,mdf:int,spd:int,luk:int}
     */
    public function statBonuses(): array
    {
        $p = $this->params ?? [];
        $at = fn (int $paramId) => $p[$paramId] ?? 0;

        return [
            'max_hp' => $at(0),
            'max_mp' => $at(1),
            'atk' => $at(2),
            'def' => $at(3),
            'mat' => $at(4),
            'mdf' => $at(5),
            'spd' => $at(6),
            'luk' => $at(7),
        ];
    }
}
