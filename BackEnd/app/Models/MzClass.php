<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzClass extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['id', 'name', 'note', 'params', 'traits', 'party_hud_icon'];
    protected $casts = ['params' => 'array', 'traits' => 'array'];

    public function skills()
    {
        return $this->hasMany(MzSkill::class, 'class_id');
    }

    /**
     * Classes.json의 params는 [paramId][level] 2차원 배열(paramId: 0=MHP,1=MMP,
     * 2=ATK,3=DEF,4=MAT,5=MDF,6=AGI,7=LUK, level: 1-99). 우리 게임엔 레벨업이
     * 없어서 액터의 initialLevel 한 시점만 뽑아 units 스탯 스냅샷으로 쓴다.
     *
     * @return array{max_hp:int,max_mp:int,atk:int,def:int,mat:int,mdf:int,spd:int,luk:int}
     */
    public function statAtLevel(int $level): array
    {
        $p = $this->params ?? [];
        $at = fn (int $paramId) => $p[$paramId][$level] ?? $p[$paramId][1] ?? 0;

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
