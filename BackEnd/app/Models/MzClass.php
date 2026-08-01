<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzClass extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['id', 'name', 'note', 'icon_index', 'exp_curve', 'param_curve', 'traits', 'party_hud_icon'];
    protected $casts = ['exp_curve' => 'array', 'param_curve' => 'array', 'traits' => 'array'];

    public function skills()
    {
        return $this->hasMany(MzSkill::class, 'class_id');
    }

    /**
     * Classes.json의 param_curve는 레벨별 행 배열([{level,hp,mp,str,vit,mnd,dex,agi,
     * luk,int}, ...])이다 - 우리 게임엔 레벨업이 없어서 액터의 initialLevel 한 시점만
     * 뽑아 쓴다. 여기서 나오는 건 STR/VIT/MND/DEX/AGI/LUK/INT "원시" 스탯이지 전투용
     * ATK/DEF/MAT/MDF가 아니다 - 장비 보너스까지 합쳐 파생 스탯(ATK=STR+무기 등)을
     * 실제로 계산하는 건 StatFormula::deriveCombatStats()가 담당한다.
     *
     * @return array{hp:int,mp:int,str:int,vit:int,mnd:int,dex:int,agi:int,luk:int,int:int}
     */
    public function rawStatsAtLevel(int $level): array
    {
        $rows = $this->param_curve ?? [];
        $row = collect($rows)->firstWhere('level', $level) ?? collect($rows)->first() ?? [];

        return [
            'hp' => (int) ($row['hp'] ?? 0),
            'mp' => (int) ($row['mp'] ?? 0),
            'str' => (int) ($row['str'] ?? 0),
            'vit' => (int) ($row['vit'] ?? 0),
            'mnd' => (int) ($row['mnd'] ?? 0),
            'dex' => (int) ($row['dex'] ?? 0),
            'agi' => (int) ($row['agi'] ?? 0),
            'luk' => (int) ($row['luk'] ?? 0),
            'int' => (int) ($row['int'] ?? 0),
        ];
    }
}
