<?php

namespace App\Support;

/**
 * README.md "3. 전투용 파생 스탯" 공식을 코드로 옮긴 것 - RPGProject/data가 쓰는
 * STR/VIT/MND/DEX/AGI/LUK/INT(+HP/MP) 9스탯을 units/battle_units가 실제로 쓰는
 * MZ 표준 파생 스탯(max_hp/max_mp/atk/def/mat/mdf/spd/luk)으로 변환한다.
 *
 * 장비 보너스는 이 클래스가 아니라 WeaponSeeder/ArmorSeeder가 미리 같은 규칙으로
 * MZ 8스탯 배열(mz_weapons/mz_armors.params)로 접어 넣는다 - 그래야
 * BattleEngine::equipmentStatsFor()가 지금처럼 그 배열을 그대로 합산하기만 하면 되고,
 * 엔진 코드를 손댈 필요가 없다.
 */
class StatFormula
{
    /** HIT = base + (1-base) * DEX/(DEX+K) - 레벨1의 낮은 DEX에서도 절반 이상은 맞게. */
    private const HIT_BASE = 0.80;

    private const HIT_K = 40;

    /** EVA/MEV = AGI/(AGI+K) - 0에서 시작해서 완만하게 증가(회피는 흔치 않아야 함). */
    private const EVA_K = 300;

    private const MEV_K = 300;

    /** CRI = (DEX+LUK)/(DEX+LUK+K). */
    private const CRI_K = 350;

    /** CEV = LUK/(LUK+K). */
    private const CEV_K = 500;

    /**
     * @param  array{hp:int,mp:int,str:int,vit:int,mnd:int,dex:int,agi:int,luk:int,int:int}  $raw
     * @return array{max_hp:int,max_mp:int,atk:int,def:int,mat:int,mdf:int,spd:int,luk:int}
     */
    public static function deriveCombatStats(array $raw): array
    {
        return [
            'max_hp' => max(1, (int) $raw['hp']),
            'max_mp' => max(0, (int) $raw['mp']),
            'atk' => max(0, (int) $raw['str']),
            'def' => max(0, (int) $raw['vit']),
            'mat' => max(0, (int) $raw['int']),
            'mdf' => max(0, (int) $raw['mnd']),
            'spd' => max(1, (int) $raw['agi']),
            'luk' => max(0, (int) $raw['luk']),
        ];
    }

    /**
     * DEX/AGI/LUK로부터 xparam(code=22) 트레잇을 합성한다 - dataId는
     * BattleEngine::XPARAM_* 상수와 동일(0=HIT/1=EVA/2=CRI/3=CEV/4=MEV).
     *
     * @return array<int, array{code:int,dataId:int,value:float}>
     */
    public static function xparamTraits(int $dex, int $agi, int $luk): array
    {
        $hit = self::HIT_BASE + (1 - self::HIT_BASE) * $dex / ($dex + self::HIT_K);
        $eva = $agi / ($agi + self::EVA_K);
        $cri = ($dex + $luk) / ($dex + $luk + self::CRI_K);
        $cev = $luk / ($luk + self::CEV_K);
        $mev = $agi / ($agi + self::MEV_K);

        return [
            ['code' => 22, 'dataId' => 0, 'value' => round($hit, 4)],
            ['code' => 22, 'dataId' => 1, 'value' => round($eva, 4)],
            ['code' => 22, 'dataId' => 2, 'value' => round($cri, 4)],
            ['code' => 22, 'dataId' => 3, 'value' => round($cev, 4)],
            ['code' => 22, 'dataId' => 4, 'value' => round($mev, 4)],
        ];
    }

    /**
     * 장비(무기/방어구) EquipParams(hp/mp/str/vit/mnd/dex/agi/luk/int 원시 보너스 +
     * atk/def/matk/mdef 직접 보너스)를 MZ 8스탯 배열([max_hp,max_mp,atk,def,mat,mdf,
     * agi,luk] 순서)로 접는다 - 원시 스탯 보너스는 같은 파생 규칙(STR→ATK 등)으로
     * 바로 합산하고, 직접 보너스는 그 위에 더한다. WeaponSeeder/ArmorSeeder 전용.
     *
     * @param  array<string, int>  $p  EquipParams 원본(hp,mp,str,vit,mnd,dex,agi,luk,int,atk,def,matk,mdef,...)
     * @return array<int, int> [max_hp,max_mp,atk,def,mat,mdf,agi,luk]
     */
    public static function equipParamsToMzArray(array $p): array
    {
        $g = fn (string $key) => (int) ($p[$key] ?? 0);

        return [
            $g('hp'),
            $g('mp'),
            $g('str') + $g('atk'),
            $g('vit') + $g('def'),
            $g('int') + $g('matk'),
            $g('mnd') + $g('mdef'),
            $g('agi'),
            $g('luk'),
        ];
    }

    /**
     * 장비 EquipParams의 hit/eva/crit 직접 보너스를 xparam 트레잇으로 변환 -
     * WeaponSeeder/ArmorSeeder가 mz_weapons/mz_armors.traits에 합쳐 넣는다.
     * critDamage/statusHit/statusRes는 BattleEngine에 대응하는 xparam 슬롯이 아직
     * 없어(코드 미구현) 지금은 반영하지 않는다 - 후속 작업 필요.
     *
     * @param  array<string, int>  $p
     * @return array<int, array{code:int,dataId:int,value:float}>
     */
    public static function equipXparamTraits(array $p): array
    {
        $traits = [];
        if (($hit = (int) ($p['hit'] ?? 0)) !== 0) {
            $traits[] = ['code' => 22, 'dataId' => 0, 'value' => $hit / 100];
        }
        if (($eva = (int) ($p['eva'] ?? 0)) !== 0) {
            $traits[] = ['code' => 22, 'dataId' => 1, 'value' => $eva / 100];
        }
        if (($crit = (int) ($p['crit'] ?? 0)) !== 0) {
            $traits[] = ['code' => 22, 'dataId' => 2, 'value' => $crit / 100];
        }

        return $traits;
    }
}
