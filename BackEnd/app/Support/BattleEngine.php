<?php

namespace App\Support;

use App\Models\Battle;
use App\Models\BattleLog;
use App\Models\BattleUnit;
use App\Models\BattleUnitState;
use App\Models\FormationSlot;
use App\Models\MercenaryGambit;
use App\Models\MzAnimation;
use App\Models\MzArmor;
use App\Models\MzClass;
use App\Models\MzItem;
use App\Models\MzSkill;
use App\Models\MzState;
use App\Models\MzSystemAudio;
use App\Models\MzTroop;
use App\Models\MzWeapon;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserItem;
use App\Models\UserMercenary;
use Illuminate\Support\Collection;

/**
 * 시험전투 전용 - 계정당 진행 중인 전투 1개, 상대는 mz_project Troops.json의 고정
 * 트룹 1번("고블린*2" 등, MzImportSeeder가 units에 자동 동기화). 아군은
 * mz_skills(직업 스킬트리)를 실제로 쓴다. 광역기(scope 2/8/10)는 해당 진영 전원에게
 * 개별로 포뮬러/이펙트를 적용하고, 결과를 battle_logs.targets(json 배열)에 담아
 * 한 턴(한 행)으로 기록한다(resolveAoeSkillUse). 캐스팅(<Casting: n>)과 광역기를
 * 함께 쓰는 조합도 지원 - 대기 중엔 대표 대상을 저장하지 않고, 발동 시점에 대상
 * 풀을 새로 뽑는다(tickCasting).
 */
class BattleEngine
{
    /** 치명타 피해 배율 - MZ 기본값(3배)은 이 프로젝트 스탯 스케일엔 너무 크게 튀어서 완화. */
    private const CRITICAL_MULTIPLIER = 1.5;

    /** MZ 표준 "위독" 기준(Game_BattlerBase.isDying, HP 25% 이하) - GuardAlly(대신 맞기) 판정에 씀. */
    private const DYING_HP_RATIO = 0.25;

    /**
     * MZ 표준 xparam(추가 특성치) id - Classes.json/Weapons.json/Armors.json/States.json/
     * Enemies.json의 traits[]에서 code=22(XPARAM)인 항목의 dataId가 이 값이다.
     * value는 전부 가산형(트레잇이 없으면 0)이라 traitsSum 방식으로 그대로 합산한다.
     */
    private const XPARAM_HIT = 0; // 명중률

    private const XPARAM_EVA = 1; // 회피율(물리)

    private const XPARAM_CRI = 2; // 치명타율

    private const XPARAM_CEV = 3; // 치명타 회피율

    private const XPARAM_MEV = 4; // 마법 회피율

    private const XPARAM_MRF = 5; // 마법 반사율

    private const XPARAM_CNT = 6; // 반격률

    private const XPARAM_HRG = 7; // HP 재생률(매 턴, 최대HP 대비 비율 - 음수면 독처럼 피해)

    private const XPARAM_MRG = 8; // MP 재생률(매 턴, 최대MP 대비 비율)

    /**
     * MZ 표준 sparam(특수 특성치) id - code=23(SPARAM). xparam과 달리 곱연산이라
     * 트레잇이 하나도 없으면 기본값 1.0(100%, 변화 없음)이다.
     */
    private const SPARAM_TGR = 0; // 타겟률(0이면 타겟팅 대상에서 제외 - "숨기" 등)

    private const SPARAM_MCR = 4; // MP 소모율

    private const SPARAM_PDR = 6; // 받는 물리 피해율

    private const SPARAM_MDR = 7; // 받는 마법 피해율

    /** 진행 중인 전투가 있으면 그대로, 없으면 활성 진형으로 새로 만든다. */
    public function createOrResume(User $user): Battle
    {
        $existing = Battle::where('user_id', $user->id)->where('status', 'in_progress')->first();
        if ($existing !== null) {
            return $existing;
        }

        $battle = Battle::create(['user_id' => $user->id, 'status' => 'in_progress', 'turn_number' => 0]);

        $allySlots = FormationSlot::where('user_id', $user->id)
            ->where('preset_number', $user->active_formation_preset)
            ->get();
        $allyUnits = Unit::whereIn('id', $allySlots->pluck('unit_id'))->get()->keyBy('id');

        foreach ($allySlots as $slotRow) {
            $unit = $allyUnits->get($slotRow->unit_id);
            if ($unit === null) {
                continue;
            }
            $this->spawn($battle, $unit, 'ally', (int) $slotRow->slot);
        }

        $this->spawnEnemyTroop($battle);

        return $battle;
    }

    /** 시험전투 고정 트룹(id 1) 전체를 스폰 - mz_troops의 6슬롯 컬럼(memberSlots())을 그대로 슬롯 1-6에 배치. */
    private function spawnEnemyTroop(Battle $battle): void
    {
        $troop = MzTroop::find(1);
        if ($troop === null) {
            return;
        }

        $slots = $troop->memberSlots();
        $unitsByEnemyId = Unit::where('type', 'enemy')->whereIn('mz_enemy_id', array_unique($slots))->get()->keyBy('mz_enemy_id');

        foreach ($slots as $position => $enemyId) {
            $unit = $unitsByEnemyId->get($enemyId);
            if ($unit === null) {
                continue;
            }
            $this->spawn($battle, $unit, 'enemy', $position);
        }
    }

    private function spawn(Battle $battle, Unit $unit, string $side, int $slot): BattleUnit
    {
        $stats = [
            'max_hp' => $unit->max_hp,
            'max_mp' => $unit->max_mp,
            'atk' => $unit->atk,
            'def' => $unit->def,
            'mat' => $unit->mat,
            'mdf' => $unit->mdf,
            'spd' => $unit->spd,
            'luk' => $unit->luk,
        ];
        $attackMotion = $unit->attack_motion;
        $weaponEffectName = $unit->weapon_effect_name;
        $equipTraits = $unit->equip_traits ?? [];

        // 아군만 유저별 장비 슬롯(user_mercenaries)이 있다 - 적은 units 스탯이 그대로 최종값.
        if ($side === 'ally') {
            $mercenary = UserMercenary::where('user_id', $battle->user_id)->where('unit_id', $unit->id)->first();
            if ($mercenary !== null) {
                $equipment = $this->equipmentStatsFor($mercenary);
                foreach ($equipment['bonus'] as $key => $value) {
                    $stats[$key] += $value;
                }
                $attackMotion = $equipment['attack_motion'];
                $weaponEffectName = $equipment['weapon_effect_name'];
                $equipTraits = array_merge($equipTraits, $equipment['traits']);
            }
        }

        return BattleUnit::create([
            'battle_id' => $battle->id,
            'unit_id' => $unit->id,
            'side' => $side,
            'slot' => $slot,
            'class_id' => $unit->class_id,
            ...$stats,
            'attack_motion' => $attackMotion,
            'weapon_effect_name' => $weaponEffectName,
            'equip_traits' => $equipTraits,
            'dragonbones_skeleton' => $unit->dragonbones_skeleton,
            'dragonbones_atlas' => $unit->dragonbones_atlas,
            'dragonbones_motions' => $unit->dragonbones_motions,
            'dragonbones_scale' => $unit->dragonbones_scale,
            'current_hp' => $stats['max_hp'],
            'current_mp' => $stats['max_mp'],
        ]);
    }

    /**
     * user_mercenaries의 장비 4슬롯(무기/방패/몸/장신구)에서 스탯 보너스+트레잇을
     * 합산하고, 무기의 wtypeId/animationId로 공격 모션과 일반 공격 이펙트를 정한다 -
     * ActorSeeder가 예전에 시딩 시점에 하던 일을 이제 스폰 시점에 유저 장비 기준으로
     * 매번 계산한다(EquipmentController가 장비를 바꿀 수 있게 됐으므로).
     *
     * @return array{bonus: array<string,int>, traits: array, attack_motion: string, weapon_effect_name: ?string}
     */
    private function equipmentStatsFor(UserMercenary $mercenary): array
    {
        $bonus = ['max_hp' => 0, 'max_mp' => 0, 'atk' => 0, 'def' => 0, 'mat' => 0, 'mdf' => 0, 'spd' => 0, 'luk' => 0];
        $traits = [];
        $wtypeId = 0;
        $weaponEffectName = null;

        if ($mercenary->weapon_id !== null) {
            $weapon = MzWeapon::find($mercenary->weapon_id);
            if ($weapon !== null) {
                foreach ($weapon->statBonuses() as $key => $value) {
                    $bonus[$key] += $value;
                }
                array_push($traits, ...($weapon->traits ?? []));
                $wtypeId = $weapon->wtype_id;
                if ($weapon->animation_id > 0) {
                    $weaponEffectName = MzAnimation::find($weapon->animation_id)?->effect_name;
                }
            }
        }

        foreach ([$mercenary->shield_id, $mercenary->body_armor_id, $mercenary->accessory_id] as $armorId) {
            if ($armorId === null) {
                continue;
            }
            $armor = MzArmor::find($armorId);
            if ($armor === null) {
                continue;
            }
            foreach ($armor->statBonuses() as $key => $value) {
                $bonus[$key] += $value;
            }
            array_push($traits, ...($armor->traits ?? []));
        }

        return [
            'bonus' => $bonus,
            'traits' => $traits,
            'attack_motion' => MzSystemAudio::current()?->motionForWeaponType($wtypeId) ?? 'thrust',
            'weapon_effect_name' => $weaponEffectName,
        ];
    }

    /** @return array{finished: bool} */
    public function advanceTurn(Battle $battle): array
    {
        if ($battle->status === 'finished') {
            return $this->getState($battle) + ['finished' => true];
        }

        $units = $battle->units()->get();
        $living = $units->filter(fn (BattleUnit $u) => $u->isAlive());

        if ($living->isEmpty()) {
            $this->finish($battle, null);

            return $this->getState($battle) + ['finished' => true];
        }

        $actor = $this->pickReadyActor($living);

        $this->act($battle, $actor, $units);

        $battle->turn_number++;
        $battle->save();

        $freshUnits = $battle->units()->get();
        $allyAlive = $freshUnits->where('side', 'ally')->contains(fn (BattleUnit $u) => $u->isAlive());
        $enemyAlive = $freshUnits->where('side', 'enemy')->contains(fn (BattleUnit $u) => $u->isAlive());

        $finished = false;
        if (! $allyAlive || ! $enemyAlive) {
            $this->finish($battle, ! $allyAlive ? 'enemy' : 'ally');
            $finished = true;
        }

        return $this->getState($battle) + ['finished' => $finished];
    }

    /**
     * 진짜 ATB(Active Time Battle) 게이지 시뮬레이션 - 살아있는 유닛 전원의
     * atb_gauge를 spd만큼(최소 1) 매 틱 증가시키다가 100 이상에 먼저 도달하는
     * 유닛을 행동시킨다(동시 도달 시 게이지가 높은 쪽, 그다음 spd가 높은 쪽 우선 -
     * 기존 라운드로빈의 "spd 내림차순" 우선순위를 동률 처리에 그대로 이어받음).
     * 행동하는 유닛은 게이지에서 100을 차감(초과분은 다음 턴으로 이월)하고, 나머지
     * 유닛도 이번 틱까지 쌓인 게이지를 그대로 저장해 다음 advanceTurn() 호출에서
     * 이어서 채워진다 - <GaugeBoost: n>은 이 저장된 값 위에 n을 더하는 방식으로
     * 구현된다(resolveSkillUse/resolveAoeSkillUse). spd가 비정상적으로 0이어도
     * 무한루프에 빠지지 않도록 틱당 최소 증가량은 1로 보정.
     */
    private function pickReadyActor(Collection $living): BattleUnit
    {
        $gauges = $living->mapWithKeys(fn (BattleUnit $u) => [$u->id => $u->atb_gauge])->all();
        $actor = null;

        while ($actor === null) {
            foreach ($living as $u) {
                $gauges[$u->id] += max(1, (int) round($this->effectiveSpd($u)));
            }

            $ready = $living->filter(fn (BattleUnit $u) => $gauges[$u->id] >= 100);
            if ($ready->isNotEmpty()) {
                $actor = $ready->sortByDesc(fn (BattleUnit $u) => $gauges[$u->id] * 1000 + $this->effectiveSpd($u))->first();
            }
        }

        foreach ($living as $u) {
            $u->atb_gauge = $gauges[$u->id] - ($u->id === $actor->id ? 100 : 0);
            $u->save();
        }

        return $actor;
    }

    private function finish(Battle $battle, ?string $winnerSide): void
    {
        $battle->status = 'finished';
        $battle->winner_side = $winnerSide;
        $battle->save();
    }

    private function act(Battle $battle, BattleUnit $actor, Collection $allUnits): void
    {
        if ($actor->isCasting()) {
            $this->tickCasting($battle, $actor, $allUnits);

            return;
        }

        $this->tickStatuses($actor);
        if (! $actor->fresh()->isAlive()) {
            $this->log($battle, $actor, null, 'cancel', null, null);

            return;
        }

        [$actionType, $actionKey, $target] = $actor->side === 'ally'
            ? $this->decideAllyAction($battle, $actor, $allUnits)
            : $this->decideEnemyAction($battle, $actor, $allUnits);

        if ($actionType === 'cancel' || $target === null) {
            $this->log($battle, $actor, null, 'cancel', null, null);

            return;
        }

        if ($actionType === 'item') {
            $item = GambitCatalog::item($actionKey);
            if ($item !== null) {
                $this->resolveItemUse($battle, $actor, $target, $item);
            } else {
                $this->log($battle, $actor, null, 'cancel', null, null);
            }

            return;
        }

        $skill = $actionType === 'skill' ? GambitCatalog::skill($actionKey) : null;

        if ($skill !== null && ($skill->tags['casting_turns'] ?? 0) > 0) {
            $this->startCasting($actor, $target, $skill);
            $this->log($battle, $actor, $target, 'casting', null, null, $skill);

            return;
        }

        if ($skill !== null) {
            $this->resolveSkillUse($battle, $actor, $target, $skill, $allUnits);

            return;
        }

        // 기본 공격은 mz_skills를 안 거치는 하드코딩 경로지만, MZ 기본 데이터의 "공격"
        // 스킬(id 1)도 늘 hitType=1(물리)/successRate=100/critical:true라 여기서도
        // 그대로 취급한다. elementId도 마찬가지로 스킬의 고정값이 아니라 -1(공격자의
        // 무기 ATTACK_ELEMENT를 그대로 물려받음, MZ 표준 Game_Action.calcElementRate)로
        // 취급한다.
        $this->performBasicAttack($battle, $actor, $target);
    }

    /**
     * 기본 공격 1회(hitType=1, successRate=100, critical=true 고정) - 매 턴의 기본
     * 공격 경로와 반격(CNT)이 되받아치는 공격 둘 다 이 메서드를 공유한다.
     * $allowCounterReflect=false는 반격 자신이 다시 반격당하지 않게 막는 용도
     * (MZ의 BattleManager.invokeAction이 재귀 호출하지 않는 것과 동일).
     */
    private function performBasicAttack(Battle $battle, BattleUnit $actor, BattleUnit $target, bool $allowCounterReflect = true): void
    {
        if ($allowCounterReflect && $this->checkCounter($battle, $actor, $target)) {
            $this->log($battle, $actor, $target->fresh(), 'attack', null, $target->fresh()->current_hp, null, null, null, 'evaded');

            return;
        }

        $hitOutcome = $this->rollHit($actor, $target->fresh(), 1, 100);
        if ($hitOutcome !== 'hit') {
            $freshTarget = $target->fresh();
            $this->log($battle, $actor, $freshTarget, 'attack', null, $freshTarget->current_hp, null, null, null, $hitOutcome);

            return;
        }

        $isCritical = $this->rollCritical($actor, $target->fresh(), true);
        $rawDamage = max(1, (int) round($this->effectiveAtk($actor) - $this->effectiveDef($target->fresh())));
        if ($isCritical) {
            $rawDamage = (int) round($rawDamage * self::CRITICAL_MULTIPLIER);
        }
        $damage = $this->applyIncomingDamageModifiers($actor, $target, $rawDamage, 1, -1);
        $freshTarget = $target->fresh();
        $freshTarget->current_hp = max(0, $freshTarget->current_hp - $damage);
        $freshTarget->save();

        $this->log($battle, $actor, $freshTarget, 'attack', $damage, $freshTarget->current_hp, null, null, $isCritical, 'hit');
    }

    /**
     * $skill을 넘기면 그 스킬의 태그를 그대로 로그 행에 박아둔다(mz_skills를 다시
     * 조회할 필요 없이) - <CircleImage>는 캐스팅 턴에서 시전자 발밑 이미지로,
     * <SkillMotion>은 스킬 사용 턴에서 재생할 SV 모션 이름으로 쓰인다. 한 스킬에
     * 둘 다 있어도 되고(캐스팅 턴엔 CircleImage만, 실제 발동 턴엔 SkillMotion만
     * 프론트가 사용 - action_type으로 구분), 서로 안 쓰는 필드는 그냥 null로 남는다.
     *
     * $targets는 광역기 전용(resolveAoeSkillUse) - 대상이 여럿이라 단일
     * target_battle_unit_id/damage/target_hp_after로는 표현이 안 돼서
     * [{battle_unit_id, damage, hp_after, critical}, ...] 배열을 그대로 이 컬럼에
     * 담는다(단일 대상 행은 $isCritical을 대신 쓰고 이 배열은 null).
     */
    private function log(
        Battle $battle,
        BattleUnit $actor,
        ?BattleUnit $target,
        string $actionType,
        ?int $damage,
        ?int $targetHpAfter,
        ?MzSkill $skill = null,
        ?array $targets = null,
        ?bool $isCritical = null,
        ?string $hitOutcome = null,
        ?string $forcedMotion = null,
        ?string $itemName = null,
    ): void {
        $circleImage = $skill?->tags['circle_image'] ?? null;

        BattleLog::create([
            'battle_id' => $battle->id,
            'turn_number' => $battle->turn_number,
            'actor_battle_unit_id' => $actor->id,
            'target_battle_unit_id' => $target?->id,
            'action_type' => $actionType,
            'damage' => $damage,
            'is_critical' => $isCritical,
            'hit_outcome' => $hitOutcome,
            'target_hp_after' => $targetHpAfter,
            'targets' => $targets,
            'skill_motion' => $forcedMotion ?? ($skill?->tags['skill_motion'] ?? null),
            'circle_image_name' => $circleImage['name'] ?? null,
            'circle_image_scale' => $circleImage['scale'] ?? null,
            'item_name' => $itemName,
        ]);
    }

    /** @return array{0: string, 1: ?int, 2: ?BattleUnit} [action_type, skill_id, target] */
    private function decideEnemyAction(Battle $battle, BattleUnit $actor, Collection $allUnits): array
    {
        $skill = $this->pickEnemySkill($battle, $actor);
        if ($skill !== null) {
            $target = $this->pickTarget($skill, $actor, $allUnits);
            $requireTargetState = $skill->tags['require_target_state'] ?? null;
            if ($target !== null && ($requireTargetState === null || $this->hasState($target, $requireTargetState))) {
                return ['skill', $skill->id, $target];
            }
        }

        $target = $this->pickTarget(null, $actor, $allUnits);

        return $target !== null ? ['attack', null, $target] : ['cancel', null, null];
    }

    /**
     * mz_enemies.actions(MZ 표준 조건부 액션 패턴, Enemies.json 그대로 보관됨)를 실제로
     * 해석해서 스킬을 고른다. conditionType 0(항상)/2(자기 HP%)/3(자기 MP%)/4(자기 상태
     * 보유)만 지원 - 1(턴 수, `$gameTroop.turnCount()` 기준이라 유닛별 행동인 이
     * 엔진의 turn_number와 개념이 안 맞음)/5(파티 레벨, 레벨 시스템 없음)/6(스위치,
     * 게임 스위치 없음)는 대응 개념이 없어 항상 조건 불충족으로 취급한다.
     *
     * MZ와 동일하게 조건을 통과한 후보 중 rating(1-9)이 최고치-3 이내인 것만 남겨서
     * rating 비례 가중치로 하나를 무작위로 고른다(`Game_Enemy.selectAction`). skillId
     * 1은 MZ 표준 "기본 공격" 예약값이라 스킬 목록엔 없지만, 다른 스킬과 같은 확률
     * 테이블에 올라가서 뽑히면 null을 반환해 호출부가 기본 공격으로 처리하게 한다.
     */
    private function pickEnemySkill(Battle $battle, BattleUnit $actor): ?MzSkill
    {
        $actions = $actor->unit->mzEnemy?->actions ?? [];
        if ($actions === []) {
            return null;
        }

        $candidates = [];
        foreach ($actions as $action) {
            $skillId = (int) ($action['skillId'] ?? 0);
            if ($skillId < 1 || ! $this->enemyActionConditionMet($actor, $action)) {
                continue;
            }

            if ($skillId > 1) {
                $skill = GambitCatalog::skill($skillId);
                if ($skill === null || ! $this->skillUsableByActor($actor, $skill, $battle)) {
                    continue; // 스킬이 없거나(미임포트) MP 부족/쿨다운/위치/자기상태 조건 불충족
                }
            }

            $candidates[] = ['skill_id' => $skillId, 'rating' => max(1, (int) ($action['rating'] ?? 5))];
        }

        if ($candidates === []) {
            return null;
        }

        $ratingZero = max(array_column($candidates, 'rating')) - 3;
        $weighted = array_values(array_filter($candidates, fn (array $c) => $c['rating'] > $ratingZero));

        $sum = array_sum(array_map(fn (array $c) => $c['rating'] - $ratingZero, $weighted));
        $chosen = $weighted[0];
        if ($sum > 0) {
            $roll = random_int(0, $sum - 1);
            foreach ($weighted as $c) {
                $roll -= $c['rating'] - $ratingZero;
                if ($roll < 0) {
                    $chosen = $c;
                    break;
                }
            }
        }

        return $chosen['skill_id'] > 1 ? GambitCatalog::skill($chosen['skill_id']) : null;
    }

    private function enemyActionConditionMet(BattleUnit $actor, array $action): bool
    {
        $type = (int) ($action['conditionType'] ?? 0);
        $p1 = (float) ($action['conditionParam1'] ?? 0);
        $p2 = (float) ($action['conditionParam2'] ?? 0);

        return match ($type) {
            0 => true,
            2 => $this->rateInRange($actor->current_hp, $actor->max_hp, $p1, $p2),
            3 => $this->rateInRange($actor->current_mp, $actor->max_mp, $p1, $p2),
            4 => $this->hasStateId($actor, (int) $p1),
            default => false,
        };
    }

    /** $minPct/$maxPct는 MZ 에디터와 동일하게 0-100 퍼센트 단위. */
    private function rateInRange(int $current, int $max, float $minPct, float $maxPct): bool
    {
        $rate = $max > 0 ? $current / $max * 100 : 0.0;

        return $rate >= $minPct && $rate <= $maxPct;
    }

    private function hasStateId(BattleUnit $unit, int $stateId): bool
    {
        return $this->statesFor($unit)->contains(fn (BattleUnitState $bus) => $bus->state_id === $stateId);
    }

    /** gambit(아군)/mz_enemies.actions(적) 양쪽에서 공유하는 "이 스킬을 지금 쓸 수 있는가" 게이트(대상 무관, 시전자 조건만). */
    private function skillUsableByActor(BattleUnit $actor, MzSkill $skill, Battle $battle): bool
    {
        if ($actor->current_mp < $skill->mp_cost) {
            return false;
        }
        if ($actor->skillOnCooldown($skill->id, $battle->turn_number)) {
            return false;
        }
        $requiredPosition = $skill->tags['require_position'] ?? null;
        if ($requiredPosition !== null && $requiredPosition !== ($actor->slot <= 3 ? 'front' : 'back')) {
            return false;
        }
        $requireSelfState = $skill->tags['require_self_state'] ?? null;
        if ($requireSelfState !== null && ! $this->hasState($actor, $requireSelfState)) {
            return false;
        }
        // 스킬 타입 봉인(STYPE_SEAL, code=42) - "침묵" 등이 이 스킬의 stype_id(1=마법/
        // 2=필살기)를 봉인 중이면 사용 불가. stype_id=0("공용" 취급 스킬)은 애초에
        // 봉인 대상 데이터가 없어 항상 통과.
        if ($skill->stype_id !== 0 && $this->sealedSkillTypesFor($actor)->contains($skill->stype_id)) {
            return false;
        }

        return true;
    }

    /** code=42(STYPE_SEAL)인 트레잇의 dataId(봉인된 스킬 타입 id) 전부. */
    private function sealedSkillTypesFor(BattleUnit $unit): Collection
    {
        $ids = [];
        foreach ($this->traitSourcesFor($unit) as $traits) {
            foreach ($traits as $trait) {
                if (($trait['code'] ?? null) === 42) {
                    $ids[] = (int) $trait['dataId'];
                }
            }
        }

        return collect($ids);
    }

    /** @return array{0: string, 1: ?int, 2: ?BattleUnit} [action_type, skill_id 또는 item_id, target] */
    private function decideAllyAction(Battle $battle, BattleUnit $actor, Collection $allUnits): array
    {
        $userMercenary = UserMercenary::where('user_id', $battle->user_id)
            ->where('unit_id', $actor->unit_id)
            ->first();

        if ($userMercenary === null) {
            return $this->decideEnemyAction($battle, $actor, $allUnits);
        }

        $rules = MercenaryGambit::where('user_mercenary_id', $userMercenary->id)
            ->where('preset_number', $userMercenary->active_gambit_preset)
            ->orderBy('priority')
            ->get();

        $isFirstAction = ! BattleLog::where('battle_id', $actor->battle_id)
            ->where('actor_battle_unit_id', $actor->id)
            ->exists();

        foreach ($rules as $rule) {
            if (! $this->conditionMet($rule, $actor, $allUnits, $isFirstAction)) {
                continue;
            }

            if ($rule->action_type === 'cancel') {
                return ['cancel', null, null];
            }

            if ($rule->action_type === 'attack') {
                $target = $this->pickTarget(null, $actor, $allUnits);
                if ($target === null) {
                    continue;
                }

                return ['attack', null, $target];
            }

            if ($rule->action_type === 'item') {
                $item = GambitCatalog::item($rule->action_skill_key);
                if ($item === null) {
                    continue; // 아이템이 삭제됐거나 잘못된 키 - 다음 규칙으로
                }

                if (! $this->hasItemStock($battle, $item)) {
                    continue; // 재고 없음 - MP 부족한 스킬과 동일하게 다음 규칙으로
                }

                $target = $this->pickItemTarget($item, $actor, $allUnits);
                if ($target === null) {
                    continue; // 대상 없음(치료 대상 없음/다른 아군 없음 등) - 다음 규칙으로
                }

                return ['item', $item->id, $target];
            }

            // 'skill'
            $skill = GambitCatalog::skill($rule->action_skill_key);
            if ($skill === null || ! $this->skillUsableByActor($actor, $skill, $battle)) {
                continue; // 스킬이 없거나 MP 부족/쿨다운/위치/자기상태 조건 불충족 - 다음 규칙으로
            }

            $target = $this->pickTarget($skill, $actor, $allUnits);
            if ($target === null) {
                continue;
            }

            $requireTargetState = $skill->tags['require_target_state'] ?? null;
            if ($requireTargetState !== null && ! $this->hasState($target, $requireTargetState)) {
                continue;
            }

            return ['skill', $skill->id, $target];
        }

        return $this->decideEnemyAction($battle, $actor, $allUnits);
    }

    private function conditionMet(MercenaryGambit $rule, BattleUnit $actor, Collection $allUnits, bool $isFirstAction): bool
    {
        return match ($rule->condition_kind) {
            'always' => true,
            'first_action' => $isFirstAction,
            'position' => $rule->condition_position === ($actor->slot <= 3 ? 'front' : 'back'),
            'stat' => $this->statConditionMet($rule, $actor, $allUnits),
            'debuff' => $this->debuffConditionMet($rule, $actor, $allUnits),
            'dead_ally' => $this->deadAllyConditionMet($actor, $allUnits),
            default => false,
        };
    }

    /** 자기 자신 제외 같은 진영에 전투불능(사망) 유닛이 하나라도 있는지 - 소생술 등을 조건부로 쓰기 위함. */
    private function deadAllyConditionMet(BattleUnit $actor, Collection $allUnits): bool
    {
        return $allUnits->where('side', $actor->side)
            ->where('id', '!=', $actor->id)
            ->contains(fn (BattleUnit $u) => ! $u->fresh()->isAlive());
    }

    private function statConditionMet(MercenaryGambit $rule, BattleUnit $actor, Collection $allUnits): bool
    {
        $candidates = match ($rule->condition_scope) {
            'self' => collect([$actor]),
            'ally' => $allUnits->where('side', $actor->side)->where('id', '!=', $actor->id),
            default => collect(),
        };

        foreach ($candidates as $candidate) {
            $candidate = $candidate->fresh();
            if (! $candidate->isAlive()) {
                continue;
            }

            $current = $rule->condition_stat === 'hp' ? $candidate->current_hp : $candidate->current_mp;
            $max = $rule->condition_stat === 'hp' ? $candidate->max_hp : $candidate->max_mp;
            $value = $rule->condition_value_type === 'percent' ? (int) round($current / max($max, 1) * 100) : $current;

            $ok = $rule->condition_operator === 'gte' ? $value >= $rule->condition_value : $value <= $rule->condition_value;
            if ($ok) {
                return true;
            }
        }

        return false;
    }

    private function debuffConditionMet(MercenaryGambit $rule, BattleUnit $actor, Collection $allUnits): bool
    {
        $candidates = match ($rule->condition_scope) {
            'self' => collect([$actor]),
            'ally' => $allUnits->where('side', $actor->side)->where('id', '!=', $actor->id),
            'enemy' => $allUnits->where('side', $actor->side === 'ally' ? 'enemy' : 'ally'),
            default => collect(),
        };

        foreach ($candidates as $candidate) {
            if ($candidate->fresh()->isAlive() && $this->hasState($candidate, (string) $rule->condition_debuff_key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * scope(self/ally/enemy)에 맞는 대상 풀에서 하나 고른다. $skill이 null이면
     * 기본 공격(적 진영, 최저 HP 우선, Taunt 있으면 그쪽 우선) 취급.
     *
     * scope 9/10은 MZ 표준 "1 아군(전투불능)"/"전체 아군(전투불능)" - 소생술처럼
     * 죽은 아군을 부활시키는 스킬 전용이라, 이 경우엔 반대로 죽은 유닛만 대상 풀에
     * 넣는다(그 외 scope는 기존처럼 살아있는 유닛만).
     */
    private function pickTarget(?MzSkill $skill, BattleUnit $actor, Collection $allUnits): ?BattleUnit
    {
        $side = $skill?->targetSide() ?? 'enemy';

        if ($side === 'self') {
            return $actor;
        }

        $poolSide = $side === 'ally' ? $actor->side : ($actor->side === 'ally' ? 'enemy' : 'ally');
        $targetsDead = in_array($skill?->scope, [9, 10], true);
        $pool = $allUnits->where('side', $poolSide)->filter(
            fn (BattleUnit $u) => $targetsDead ? ! $u->fresh()->isAlive() : $u->fresh()->isAlive(),
        );
        $pool = $this->restrictToRow($pool, $skill);
        $pool = $this->excludeHidden($pool);

        if ($pool->isEmpty()) {
            return null;
        }

        if ($side === 'ally') {
            return $pool->sortBy(function (BattleUnit $u) {
                $fresh = $u->fresh();

                return $fresh->max_hp > 0 ? $fresh->current_hp / $fresh->max_hp : 0;
            })->first();
        }

        $taunting = $pool->filter(fn (BattleUnit $u) => $this->hasTaunt($u));
        $candidates = $taunting->isNotEmpty() ? $taunting : $pool;

        $picked = $candidates->sortBy(fn (BattleUnit $u) => $u->fresh()->current_hp)->first();

        return $this->applyGuardSubstitute($picked, $pool);
    }

    /**
     * <GuardAlly> 상태(States.json 트레잇 code62/dataId2 "대신 맞기"에 대응) - 원래
     * 뽑힌 대상이 위독(HP 25% 이하)하고, 같은 풀(같은 진영) 안에 그 상태를 가진(자신은
     * 위독하지 않은) 유닛이 있으면 대신 맞아준다. Taunt로 이미 확정된 대상이라도
     * 위독하면 대신 맞아줌 - MZ도 타겟이 정해진 뒤 별도 단계로 대신맞기를 체크하는
     * 순서와 동일하다. 광역기(pickTargets())는 전체를 다 때리는 개념이라 "대신 맞을
     * 대상 하나"가 없어서 적용하지 않는다(MZ도 전체 대상 스코프엔 이 체크가 없음).
     */
    private function applyGuardSubstitute(?BattleUnit $picked, Collection $pool): ?BattleUnit
    {
        if ($picked === null || ! $this->isDying($picked->fresh())) {
            return $picked;
        }

        $guardian = $pool->first(
            fn (BattleUnit $u) => $u->id !== $picked->id && $this->hasGuardAlly($u) && ! $this->isDying($u->fresh()),
        );

        return $guardian ?? $picked;
    }

    private function isDying(BattleUnit $unit): bool
    {
        return $unit->max_hp > 0 && ($unit->current_hp / $unit->max_hp) <= self::DYING_HP_RATIO;
    }

    private function hasGuardAlly(BattleUnit $unit): bool
    {
        return $this->statesFor($unit)->contains(fn (BattleUnitState $bus) => $bus->state->tags['guard_ally'] ?? false);
    }

    /**
     * SPARAM(TGR, code=23 dataId=0)이 0인("숨기" 등) 유닛을 단일 대상 후보 풀에서
     * 뺀다 - MZ도 TGR은 단일 대상 스코프의 무작위 선택에만 관여하고 전체 대상 스코프
     * (광역기, pickTargets())엔 적용되지 않는다. 제외한 결과 풀이 통째로 비면(숨은
     * 유닛이 그 진영의 마지막 생존자인 경우 등) 원래 풀을 그대로 돌려준다 - 누군가는
     * 반드시 맞아야 하므로.
     */
    private function excludeHidden(Collection $pool): Collection
    {
        $visible = $pool->filter(fn (BattleUnit $u) => $this->sparam($u, self::SPARAM_TGR) !== 0.0);

        return $visible->isNotEmpty() ? $visible : $pool;
    }

    /**
     * <TargetFrontRow>/<TargetBackRow> 노트태그 - 대상 풀을 슬롯 1-3(전열)/4-6(후열)로
     * 좁힌다. MZ 표준 scope엔 "적 전열만"/"적 후열만" 같은 개념이 없어서(scope 2 "전체
     * 적"만 있음) 이 태그로 그 위에 얹었다 - scope 2/8(전체 적/아군)에 붙이면 "적 전방
     * 광역"/"적 후방 광역" 같은 커스텀 범위 스킬을 만들 수 있다. pickTarget()(단일
     * 대상)에도 같이 적용해서, "전방 광역인데 전열이 전멸"인 경우 gambit이 대상 없음을
     * 정확히 감지하고 다음 규칙으로 넘어가게 한다(pickTargets()만 좁히면 이 둘의 판단이
     * 어긋나 스킬을 쓰고도 아무도 안 맞는 빈 턴이 생길 수 있음). 둘 다 없으면 원래 풀
     * 그대로.
     */
    private function restrictToRow(Collection $pool, ?MzSkill $skill): Collection
    {
        $tags = $skill?->tags ?? [];
        if ($tags['target_front_row'] ?? false) {
            return $pool->filter(fn (BattleUnit $u) => $u->slot <= 3);
        }
        if ($tags['target_back_row'] ?? false) {
            return $pool->filter(fn (BattleUnit $u) => $u->slot > 3);
        }

        return $pool;
    }

    /**
     * 광역기(scope 2/8/10) 전용 - pickTarget()과 같은 side/생사/전후열 판정을
     * 재사용하되 하나만 고르지 않고 대상 풀 전체를 돌려준다. Taunt는 단일 대상 어그로
     * 개념이라 전체를 때리는 광역기엔 적용하지 않는다.
     */
    private function pickTargets(MzSkill $skill, BattleUnit $actor, Collection $allUnits): Collection
    {
        $side = $skill->targetSide();
        if ($side === 'self') {
            return collect([$actor]);
        }

        $poolSide = $side === 'ally' ? $actor->side : ($actor->side === 'ally' ? 'enemy' : 'ally');
        $targetsDead = $skill->scope === 10;

        $pool = $allUnits->where('side', $poolSide)->filter(
            fn (BattleUnit $u) => $targetsDead ? ! $u->fresh()->isAlive() : $u->fresh()->isAlive(),
        );

        return $this->restrictToRow($pool, $skill)->values();
    }

    /** user_items 재고(계정=용병단 공유) - 0개면 이 gambit 규칙은 MP 부족한 스킬과 동일하게 건너뛴다. */
    private function hasItemStock(Battle $battle, MzItem $item): bool
    {
        return UserItem::where('user_id', $battle->user_id)->where('mz_item_id', $item->id)->value('quantity') > 0;
    }

    /** 아이템 사용에 성공했을 때만 호출 - 재고를 1개 차감한다(0 밑으로는 안 내려감). */
    private function consumeItemStock(Battle $battle, MzItem $item): void
    {
        $owned = UserItem::where('user_id', $battle->user_id)->where('mz_item_id', $item->id)->first();
        if ($owned !== null) {
            $owned->quantity = max(0, $owned->quantity - 1);
            $owned->save();
        }
    }

    /**
     * 소모 아이템 전용 대상 선정 - 이 프로젝트가 만든 아이템은 전부 scope 7(1 아군)
     * 아니면 11(자신)이라 pickTarget()의 Taunt/GuardAlly/TGR/전후열 같은 복잡한 규칙이
     * 필요 없다. remove_states가 있는(=상태이상 치료용) 아이템은 그 상태를 실제로
     * 가진 아군만 후보로 좁힌다(없으면 사용 불가 - null 반환) - 그래야 해독제를
     * 아무한테나 낭비하지 않는다. exclude_self(<ExcludeSelf>, "다른 아군이 사용해줘야
     * 함" 계열)는 시전자 자신을 후보에서 뺀다. 남은 후보 중에선 이 아이템이 회복하는
     * 자원(HP 물약이면 HP%, MP 물약이면 MP%) 비율이 가장 낮은 아군을 우선한다.
     */
    private function pickItemTarget(MzItem $item, BattleUnit $actor, Collection $allUnits): ?BattleUnit
    {
        if ($item->targetSide() === 'self') {
            return $actor;
        }

        $tags = $item->tags;
        $pool = $allUnits->where('side', $actor->side)->filter(fn (BattleUnit $u) => $u->fresh()->isAlive());

        if ($tags['exclude_self'] ?? false) {
            $pool = $pool->where('id', '!=', $actor->id);
        }

        $requiredState = $tags['remove_states'][0]['state'] ?? null;
        if ($requiredState !== null) {
            $pool = $pool->filter(fn (BattleUnit $u) => $this->hasState($u, $requiredState));
        }

        if ($pool->isEmpty()) {
            return null;
        }

        $recoversMp = ($tags['recover_mp']['rate'] ?? 0) > 0 || ($tags['recover_mp']['flat'] ?? 0) > 0;

        return $pool->sortBy(function (BattleUnit $u) use ($recoversMp) {
            $fresh = $u->fresh();
            if ($recoversMp) {
                return $fresh->max_mp > 0 ? $fresh->current_mp / $fresh->max_mp : 0;
            }

            return $fresh->max_hp > 0 ? $fresh->current_hp / $fresh->max_hp : 0;
        })->first();
    }

    /**
     * 소모 아이템 사용 - 스킬과 달리 MP 소모/쿨다운/명중 판정이 없다(전부 확정
     * 성공). 대신 decideAllyAction()에서 이미 hasItemStock()으로 재고를 확인했고,
     * 여기선 실제로 소모(consumeItemStock(), user_items.quantity -1)한다. HP/MP
     * 회복(effects code11/12에서 온 recover_hp/recover_mp, 비율+고정값
     * 합산), 상태 해제(remove_states, 확률 굴림), 상태 부여(add_states, 확률 굴림 +
     * apply_self_state, <ApplySelfState: 상태명, 턴수> 노트태그로 시전자 자신에게
     * 커스텀 지속시간으로 부여 - "저항의 물약"이 상태 자신의 기본 지속시간(5턴)과
     * 다르게 2턴만 걸고 싶어서 만든 오버라이드) 순으로 적용한다. 로그의 damage는
     * HP 회복량을 음수로 기록(스킬 회복과 동일 컨벤션) - MP 회복은 별도 로그 필드가
     * 없어 HP 바처럼 숫자로 안 뜨고 다음 상태 갱신 때 MP 바로만 반영된다.
     */
    private function resolveItemUse(Battle $battle, BattleUnit $actor, BattleUnit $target, MzItem $item): void
    {
        $this->consumeItemStock($battle, $item);

        $tags = $item->tags;
        $target = $target->fresh();
        $healedHp = 0;

        $hpRate = $tags['recover_hp']['rate'] ?? 0.0;
        $hpFlat = $tags['recover_hp']['flat'] ?? 0;
        if ($hpRate > 0 || $hpFlat > 0) {
            $amount = (int) round($target->max_hp * $hpRate) + $hpFlat;
            $before = $target->current_hp;
            $target->current_hp = min($target->max_hp, $target->current_hp + $amount);
            $healedHp = $target->current_hp - $before;
            $target->save();
        }

        $mpRate = $tags['recover_mp']['rate'] ?? 0.0;
        $mpFlat = $tags['recover_mp']['flat'] ?? 0;
        if ($mpRate > 0 || $mpFlat > 0) {
            $amount = (int) round($target->max_mp * $mpRate) + $mpFlat;
            $target->current_mp = min($target->max_mp, $target->current_mp + $amount);
            $target->save();
        }

        foreach ($tags['remove_states'] ?? [] as $entry) {
            if (mt_rand() / mt_getrandmax() <= $entry['chance']) {
                $this->removeState($target, $entry['state']);
            }
        }
        foreach ($tags['add_states'] ?? [] as $entry) {
            $chance = $entry['chance'] * $this->stateRateMultiplier($target, $entry['state']);
            if (mt_rand() / mt_getrandmax() <= $chance) {
                $this->applyState($target, $entry['state'], $battle->turn_number);
            }
        }

        if (($pair = $tags['apply_self_state'] ?? null) !== null) {
            $this->applyState($actor, $pair[0], $battle->turn_number, $pair[1]);
        }

        $damage = $healedHp > 0 ? -$healedHp : null;
        $this->log($battle, $actor, $target, 'item', $damage, $target->current_hp, null, null, null, 'hit', 'item', $item->name);
    }

    /**
     * 광역기(scope 2/8/10)는 대상이 여럿이라 "대표 1명"을 저장해봐야 의미가 없고
     * (그 1명의 생사만으로 캐스팅 전체를 취소해버리면 나머지 대상들이 억울하게
     * 같이 취소됨) 어차피 발동 시점(tickCasting)에 대상 풀을 다시 뽑으므로,
     * casting_target_battle_unit_id는 단일 대상 스킬에만 기록한다.
     */
    private function startCasting(BattleUnit $actor, BattleUnit $target, MzSkill $skill): void
    {
        // MP는 캐스팅이 끝나 실제로 발동될 때 차감한다(resolveSkillUse) - 대기 중엔 아직 안 씀.
        $actor->casting_skill_id = $skill->id;
        $actor->casting_target_battle_unit_id = $skill->isAoe() ? null : $target->id;
        $actor->casting_turns_remaining = $skill->tags['casting_turns'];
        $actor->save();
    }

    private function tickCasting(Battle $battle, BattleUnit $actor, Collection $allUnits): void
    {
        $skill = GambitCatalog::skill($actor->casting_skill_id);
        $actor->casting_turns_remaining--;

        if ($actor->casting_turns_remaining > 0) {
            $actor->save();
            $target = $actor->casting_target_battle_unit_id !== null
                ? BattleUnit::find($actor->casting_target_battle_unit_id)
                : null;
            $this->log($battle, $actor, $target, 'casting', null, null, $skill);

            return;
        }

        $target = $actor->casting_target_battle_unit_id !== null
            ? BattleUnit::find($actor->casting_target_battle_unit_id)
            : null;

        $actor->casting_skill_id = null;
        $actor->casting_target_battle_unit_id = null;
        $actor->casting_turns_remaining = null;
        $actor->save();

        if ($skill === null) {
            $this->log($battle, $actor, null, 'cancel', null, null);

            return;
        }

        // 광역기: 대표 1명이 아니라 "그 시점에 유효한 대상이 하나라도 남아있는지"로
        // 판정한다(scope 10은 부활계라 반대로 죽은 대상이 남아있는지 - pickTargets()가
        // 이미 이 폴라리티를 알아서 처리). 대상이 아무도 안 남았을 때만 불발 취소.
        if ($skill->isAoe()) {
            if ($this->pickTargets($skill, $actor, $allUnits)->isEmpty()) {
                $this->log($battle, $actor, null, 'cancel', null, null); // 캐스팅 중 대상 진영이 전멸/전원 부활 등으로 불발

                return;
            }

            $this->resolveAoeSkillUse($battle, $actor, $skill, $allUnits);

            return;
        }

        // 단일 대상: scope 9(1 아군(전투불능), 소생술 전용)는 대상이 "계속 죽어있어야"
        // 정상이므로 생사 판정을 반대로 적용한다(pickTarget()과 동일한 폴라리티).
        $targetsDead = $skill->scope === 9;
        $targetInvalid = $target === null || ($targetsDead ? $target->fresh()->isAlive() : ! $target->fresh()->isAlive());

        if ($targetInvalid) {
            $this->log($battle, $actor, null, 'cancel', null, null); // 캐스팅 중 대상이 죽는(혹은 부활하는) 등으로 불발

            return;
        }

        $this->resolveSkillUse($battle, $actor, $target, $skill, $allUnits);
    }

    private function resolveSkillUse(Battle $battle, BattleUnit $actor, BattleUnit $target, MzSkill $skill, Collection $allUnits): void
    {
        if ($skill->isAoe()) {
            $this->resolveAoeSkillUse($battle, $actor, $skill, $allUnits);

            return;
        }

        // 반격(물리)/마법 반사(마법) 판정 - 반격이 발동하면 대상 쪽 효과(피해/상태
        // 부여 등)는 전부 스킵되지만(대상이 즉시 되받아침, 아래 hitOutcome='evaded'
        // 취급), MP 소모/쿨다운/자버프 등 시전자 자신에게 걸리는 효과는 MZ와 동일하게
        // 그대로 적용된다(MZ Game_Battler.useItem이 대상 결과와 무관하게 비용을 먼저
        // 치름) - 그래서 여기서 return하지 않고 아래로 계속 흘려보낸다. 마법 반사가
        // 발동하면 이 스킬의 나머지 전체가 원래 대상이 아니라 시전자 자신에게
        // 적용된다(대상만 바뀌고 명중/회피/피해는 처음부터 다시 굴러감).
        $countered = $skill->hit_type === 1 && $this->checkCounter($battle, $actor, $target);
        if (! $countered && $skill->hit_type === 2 && $this->rollChance($this->xparam($target->fresh(), self::XPARAM_MRF))) {
            $target = $actor;
        }

        $tags = $skill->tags;

        // ApplySelfStateIfSelfHas/ApplyStateIfTargetHas는 "이 스킬의 다른 효과가 적용되기
        // 전, 시전 시점의 상태"를 기준으로 판정한다(README 명시 규칙) - 미리 스냅샷.
        $actorHadStates = $this->stateNames($actor);
        $targetHadStates = $this->stateNames($target);

        // 다단히트(mz_skills.repeats > 1) - 히트마다 명중/회피/치명타/피해/effects[]
        // 확률 상태부여를 독립적으로 굴린다(MZ Game_Action.repeatTargets와 동일하게
        // "같은 대상을 repeats번 반복"). 반격/마법반사(위)는 스킬 사용 1회당 한 번만
        // 판정한다 - 다단히트라고 매 히트마다 따로 반격당하지는 않는다(의도적 단순화).
        $repeats = max(1, $skill->repeats);
        $anyHit = false;
        $lastOutcome = $countered ? 'evaded' : 'missed';
        $totalDamage = 0;
        $isCritical = null;
        $freshTarget = $target->fresh();

        if (! $countered) {
            for ($i = 0; $i < $repeats; $i++) {
                $result = $this->resolveOneHit($battle, $actor, $freshTarget, $skill);
                $freshTarget = $result['target'];
                $lastOutcome = $result['outcome'];

                if ($result['outcome'] === 'hit') {
                    $anyHit = true;
                    if ($result['critical'] !== null) {
                        $isCritical = ($isCritical ?? false) || $result['critical'];
                    }
                    if ($result['damage'] !== null) {
                        $totalDamage += $result['damage'];
                    }
                }

                if (! $freshTarget->isAlive()) {
                    break; // 이미 쓰러진 상대에게 남은 히트를 더 때릴 이유가 없다
                }
            }
        }

        $hitOutcome = $anyHit ? 'hit' : $lastOutcome;
        $damage = $anyHit ? $totalDamage : null;
        $targetHpAfter = $freshTarget->current_hp;
        $target = $freshTarget;

        $mpCost = (int) round($skill->mp_cost * $this->sparam($actor, self::SPARAM_MCR));
        $actor->current_mp = max(0, $actor->current_mp - $mpCost);
        if (($mpRecover = $tags['mp_recover'] ?? null) !== null) {
            $actor->current_mp = min($actor->max_mp, $actor->current_mp + $mpRecover);
        }
        if ($tags['consume_all_mp'] ?? false) {
            $actor->current_mp = 0;
        }
        // 마법 반사로 이 스킬이 시전자 자신에게 적용됐을 수 있어(위 $target = $actor)
        // HP만 최신값으로 다시 동기화한다 - 안 그러면 아래 $actor->save()가 방금 반사로
        // 깎인 HP를 이 메서드 시작 시점의 오래된 값으로 덮어써버린다.
        $actor->current_hp = $actor->fresh()->current_hp;
        if (($lifestealPct = $tags['lifesteal_pct'] ?? null) !== null && $damage !== null && $damage > 0) {
            $actor->current_hp = min($actor->max_hp, $actor->current_hp + (int) round($damage * $lifestealPct / 100));
        }
        if (($cooldown = $tags['cooldown_turns'] ?? null) !== null) {
            $cooldowns = $actor->skill_cooldowns ?? [];
            $cooldowns[$skill->id] = $battle->turn_number + $cooldown;
            $actor->skill_cooldowns = $cooldowns;
        }
        if (($gaugeBoost = $tags['gauge_boost'] ?? null) !== null) {
            $actor->atb_gauge += $gaugeBoost;
        }
        $actor->save();

        foreach ($tags['remove_self_states'] ?? [] as $name) {
            $this->removeState($actor, $name);
        }
        if (($applySelf = $tags['apply_self_state'] ?? null) !== null) {
            $this->applyState($actor, $applySelf, $battle->turn_number);
        }
        if (($pair = $tags['apply_self_if_has'] ?? null) !== null && in_array($pair[0], $actorHadStates, true)) {
            $this->applyState($actor, $pair[1], $battle->turn_number);
        }

        // 대상 쪽 효과는 명중했을 때만(다단히트는 하나라도 맞았으면 적용) - MZ도
        // 빗나가거나 회피당하면 상태 부여/제거 포함 그 행동의 대상측 효과 전체가
        // 무산된다(result.isHit() 게이트와 동일한 취급). 확률 기반(target_remove_states/
        // target_add_states, effects[] 유래)은 히트마다 독립적으로 굴려야 해서
        // resolveOneHit() 안으로 옮겼고, 여기 남은 건 항상 100% 확정인 note 태그
        // 효과(스킬 사용 1회당 한 번만 적용되면 되는 것들)뿐이다.
        if ($hitOutcome === 'hit') {
            foreach ($tags['remove_target_states'] ?? [] as $name) {
                $this->removeState($target, $name);
            }
            if (($pair = $tags['apply_target_if_has'] ?? null) !== null && in_array($pair[0], $targetHadStates, true)) {
                $this->applyState($target, $pair[1], $battle->turn_number);
            }
        }
        if ($tags['swap_position'] ?? false) {
            $this->resolveSwapPosition($actor, $allUnits);
        }

        $this->log($battle, $actor, $target, 'skill', $damage, $targetHpAfter, $skill, null, $isCritical, $hitOutcome);
    }

    /**
     * <SwapPosition>(공용 스킬 "자리 변경" 전용) - 시전자를 반대 줄(전열<->후열)로
     * 옮긴다. 그 줄에 빈 슬롯(1-6 중 아직 아무도 없는 슬롯)이 있으면 그리로 이동,
     * 없으면(꽉 찬 6인 적 트룹 등) 그 줄에 있는 같은 진영 유닛 1명과 무작위로 자리를
     * 맞바꾼다. 죽은 유닛도 그 자리를 "차지하고 있는" 것으로 취급한다(부활 후에도
     * 같은 자리에 남아있어야 하므로 - dead pose 유지와 일관).
     */
    private function resolveSwapPosition(BattleUnit $actor, Collection $allUnits): void
    {
        $targetRow = $actor->slot <= 3 ? [4, 5, 6] : [1, 2, 3];
        $rowMates = $allUnits->where('side', $actor->side)->where('id', '!=', $actor->id)
            ->filter(fn (BattleUnit $u) => in_array($u->slot, $targetRow, true));

        $occupiedSlots = $rowMates->pluck('slot')->all();
        $freeSlot = collect($targetRow)->first(fn (int $slot) => ! in_array($slot, $occupiedSlots, true));

        if ($freeSlot !== null) {
            $actor->slot = $freeSlot;
            $actor->save();

            return;
        }

        $swapWith = $rowMates->random();
        $oldSlot = $actor->slot;
        $actor->slot = $swapWith->slot;
        $swapWith->slot = $oldSlot;
        $actor->save();
        $swapWith->save();
    }

    /**
     * 광역기(scope 2/8/10) 전용 - pickTargets()로 얻은 풀 전체에 포뮬러/이펙트를
     * 개별 적용한다. 시전자 자신에게 거는 효과(MP소모/쿨다운/ApplySelfState류)는
     * 대상 수와 무관하게 1회만 적용하고, 대상별 효과(명중/회피 판정, 피해/회복,
     * RemoveTargetState/target_remove_states/ApplyStateIfTargetHas/target_add_states/
     * 치명타 판정)는 대상마다 각각 굴린다 - 같은 광역기라도 누구는 맞고 누구는
     * 피할 수 있다. 흡혈은 전체 피해 합산분을 시전자에게 1회로 합산 적용한다.
     */
    private function resolveAoeSkillUse(Battle $battle, BattleUnit $actor, MzSkill $skill, Collection $allUnits): void
    {
        $tags = $skill->tags;
        $actorHadStates = $this->stateNames($actor);
        $pool = $this->pickTargets($skill, $actor, $allUnits);

        $mpCost = (int) round($skill->mp_cost * $this->sparam($actor, self::SPARAM_MCR));
        $actor->current_mp = max(0, $actor->current_mp - $mpCost);
        if (($mpRecover = $tags['mp_recover'] ?? null) !== null) {
            $actor->current_mp = min($actor->max_mp, $actor->current_mp + $mpRecover);
        }
        if ($tags['consume_all_mp'] ?? false) {
            $actor->current_mp = 0;
        }
        if (($cooldown = $tags['cooldown_turns'] ?? null) !== null) {
            $cooldowns = $actor->skill_cooldowns ?? [];
            $cooldowns[$skill->id] = $battle->turn_number + $cooldown;
            $actor->skill_cooldowns = $cooldowns;
        }
        if (($gaugeBoost = $tags['gauge_boost'] ?? null) !== null) {
            $actor->atb_gauge += $gaugeBoost;
        }

        foreach ($tags['remove_self_states'] ?? [] as $name) {
            $this->removeState($actor, $name);
        }
        if (($applySelf = $tags['apply_self_state'] ?? null) !== null) {
            $this->applyState($actor, $applySelf, $battle->turn_number);
        }
        if (($pair = $tags['apply_self_if_has'] ?? null) !== null && in_array($pair[0], $actorHadStates, true)) {
            $this->applyState($actor, $pair[1], $battle->turn_number);
        }

        $lifestealPct = $tags['lifesteal_pct'] ?? null;
        $totalLifesteal = 0;
        $results = [];

        foreach ($pool as $poolUnit) {
            $target = $poolUnit->fresh();

            // 반격/마법 반사 - 단일 대상(resolveSkillUse)과 동일한 규칙을 대상마다 각자
            // 독립적으로 굴린다(광역기라 여러 명이 동시에 반격/반사할 수 있음).
            if ($skill->hit_type === 1 && $this->checkCounter($battle, $actor, $target)) {
                $results[] = ['battle_unit_id' => $target->id, 'damage' => null, 'hp_after' => $target->fresh()->current_hp, 'critical' => null, 'hit_outcome' => 'evaded'];

                continue;
            }
            if ($skill->hit_type === 2 && $this->rollChance($this->xparam($target, self::XPARAM_MRF))) {
                $target = $actor;
            }

            $targetHadStates = $this->stateNames($target);

            // 다단히트 - resolveSkillUse()와 동일하게 대상 하나당 repeats번 독립적으로
            // 굴린다(반격/마법반사는 위에서 대상당 1회만 판정, 다단히트라고 매 히트
            // 따로 반격당하진 않음).
            $repeats = max(1, $skill->repeats);
            $anyHit = false;
            $hitOutcome = 'missed';
            $totalDamage = 0;
            $isCritical = null;

            for ($i = 0; $i < $repeats; $i++) {
                $result = $this->resolveOneHit($battle, $actor, $target, $skill);
                $target = $result['target'];
                $hitOutcome = $result['outcome'];

                if ($result['outcome'] === 'hit') {
                    $anyHit = true;
                    if ($result['critical'] !== null) {
                        $isCritical = ($isCritical ?? false) || $result['critical'];
                    }
                    if ($result['damage'] !== null) {
                        $totalDamage += $result['damage'];
                        if ($lifestealPct !== null && $result['damage'] > 0) {
                            $totalLifesteal += (int) round($result['damage'] * $lifestealPct / 100);
                        }
                    }
                }

                if (! $target->isAlive()) {
                    break; // 이미 쓰러진 상대에게 남은 히트를 더 때릴 이유가 없다
                }
            }

            $hitOutcome = $anyHit ? 'hit' : $hitOutcome;
            $damage = $anyHit ? $totalDamage : null;

            if ($hitOutcome === 'hit') {
                foreach ($tags['remove_target_states'] ?? [] as $name) {
                    $this->removeState($target, $name);
                }
                if (($pair = $tags['apply_target_if_has'] ?? null) !== null && in_array($pair[0], $targetHadStates, true)) {
                    $this->applyState($target, $pair[1], $battle->turn_number);
                }
            }

            $results[] = [
                'battle_unit_id' => $target->id, 'damage' => $damage, 'hp_after' => $target->current_hp,
                'critical' => $isCritical, 'hit_outcome' => $hitOutcome,
            ];
        }

        // 마법 반사로 이 스킬이 시전자 자신에게도 적용됐을 수 있어(위 $target = $actor)
        // HP만 최신값으로 다시 동기화한다 - 안 그러면 아래 $actor->save()가 방금 반사로
        // 깎인 HP를 이 메서드 시작 시점의 오래된 값으로 덮어써버린다.
        $actor->current_hp = $actor->fresh()->current_hp;
        if ($totalLifesteal > 0) {
            $actor->current_hp = min($actor->max_hp, $actor->current_hp + $totalLifesteal);
        }
        $actor->save();

        $this->log($battle, $actor, null, 'skill', null, null, $skill, $results);
    }

    /**
     * 명중 판정 -> (명중 시) 포뮬러/분산/치명타/원소·PDR·MDR·보호막 등 피해(또는 회복)
     * 적용 -> 대상측 effects[] 확률 상태 부여·해제(target_add_states/target_remove_states)
     * 까지 "히트 1회"를 통째로 처리한다. 단일 대상 스킬의 다단히트(mz_skills.repeats)와
     * 광역기의 대상별 처리 둘 다 이 메서드를 반복 호출해서 구현한다(다단히트+광역기
     * 조합도 대상마다 repeats번씩 이 메서드가 돈다) - MZ의 Game_Action.repeatTargets가
     * 같은 대상을 repeats번 늘어놓고 각각 독립적으로 apply()하는 방식 그대로다.
     * 반격/마법 반사 판정은 스킬 사용 1회당 한 번만 굴리도록 호출부가 이 메서드 밖에서
     * 먼저 처리한다(다단히트라고 매 히트 따로 반격당하지는 않음 - 의도적 단순화).
     *
     * @return array{outcome: string, damage: ?int, critical: ?bool, target: BattleUnit}
     */
    private function resolveOneHit(Battle $battle, BattleUnit $actor, BattleUnit $target, MzSkill $skill): array
    {
        $target = $target->fresh();
        $hitOutcome = $this->rollHit($actor, $target, $skill->hit_type, $skill->success_rate);

        if ($hitOutcome !== 'hit') {
            return ['outcome' => $hitOutcome, 'damage' => null, 'critical' => null, 'target' => $target];
        }

        $aVars = ['atk' => $this->effectiveAtk($actor), 'def' => $this->effectiveDef($actor), 'mat' => $this->effectiveMat($actor), 'mdf' => $this->effectiveMdf($actor), 'mhp' => $actor->max_hp, 'mp' => $actor->current_mp];
        $bVars = ['atk' => $this->effectiveAtk($target), 'def' => $this->effectiveDef($target), 'mat' => $this->effectiveMat($target), 'mdf' => $this->effectiveMdf($target), 'mhp' => $target->max_hp, 'mp' => $target->current_mp];

        $raw = max(0, MzFormulaEvaluator::evaluate($skill->damage_formula, $aVars, $bVars));
        $variance = $skill->variance;
        $factor = 1 + (random_int(-$variance, $variance) / 100);
        $amount = (int) round($raw * $factor);

        $damage = null;
        $isCritical = null;

        if ($skill->damage_type === 1) { // HP damage
            $isCritical = $this->rollCritical($actor, $target, $skill->critical);
            if ($isCritical) {
                $amount = (int) round($amount * self::CRITICAL_MULTIPLIER);
            }
            $amount = $this->applyIncomingDamageModifiers($actor, $target, max(1, $amount), $skill->hit_type, $skill->element_id);
            $target->current_hp = max(0, $target->current_hp - $amount);
            $target->save();
            $damage = $amount;
        } elseif ($skill->damage_type === 3) { // HP recover - signed damage 컬럼에 음수로 기록
            $target->current_hp = min($target->max_hp, $target->current_hp + $amount);
            $target->save();
            $damage = -$amount;
        }

        $tags = $skill->tags;
        foreach ($tags['target_remove_states'] ?? [] as $entry) {
            if (mt_rand() / mt_getrandmax() <= $entry['chance']) {
                $this->removeState($target, $entry['state']);
            }
        }
        foreach ($tags['target_add_states'] ?? [] as $entry) {
            $chance = $entry['chance'] * $this->stateRateMultiplier($target, $entry['state']);
            if (mt_rand() / mt_getrandmax() <= $chance) {
                $this->applyState($target, $entry['state'], $battle->turn_number);
            }
        }

        return ['outcome' => 'hit', 'damage' => $damage, 'critical' => $isCritical, 'target' => $target];
    }

    /**
     * MZ 표준 공식(Game_Action.itemCri) 그대로: 공격자 CRI × (1 - 대상 CEV). $eligible이
     * false(스킬의 damage.critical=false, 또는 피해가 아닌 효과)면 항상 false.
     */
    private function rollCritical(BattleUnit $attacker, BattleUnit $target, bool $eligible): bool
    {
        if (! $eligible) {
            return false;
        }

        $rate = $this->xparam($attacker, self::XPARAM_CRI) * (1 - $this->xparam($target, self::XPARAM_CEV));
        $rate = max(0.0, min(1.0, $rate));

        return (mt_rand() / mt_getrandmax()) < $rate;
    }

    /**
     * hitType(0=필중/1=물리/2=마법)별로 MZ 표준 2단계 판정(Game_Action.itemHit/itemEva)을
     * 재현한다: ① 빗나감 굴림 - 물리는 성공률×공격자HIT, 마법/필중은 성공률만(공격자
     * 스탯 무관). 필중은 여기서 끝(회피 불가). ② 회피 굴림(물리만 남음) - 물리는
     * 대상EVA, 마법은 대상MEV로 한 번 더 굴린다. 반환값 'hit'/'missed'/'evaded'는
     * 호출부가 그대로 로그에 남겨서 프론트가 문구/연출을 고른다.
     */
    private function rollHit(BattleUnit $actor, BattleUnit $target, int $hitType, int $successRate): string
    {
        if ($hitType === 0) {
            return 'hit';
        }

        $hitChance = $hitType === 1
            ? $successRate / 100 * $this->xparam($actor, self::XPARAM_HIT)
            : $successRate / 100;

        if ((mt_rand() / mt_getrandmax()) >= max(0.0, $hitChance)) {
            return 'missed';
        }

        $evadeChance = $hitType === 1
            ? $this->xparam($target, self::XPARAM_EVA)
            : $this->xparam($target, self::XPARAM_MEV);

        return (mt_rand() / mt_getrandmax()) < max(0.0, $evadeChance) ? 'evaded' : 'hit';
    }

    /**
     * 반격(CNT, XPARAM dataId=6) - 물리 공격/스킬에만 해당한다(MZ
     * Game_Action.itemCnt는 마법엔 절대 반격을 안 준다). 발동하면 대상이 시전자에게
     * 즉시 기본 공격으로 되받아치고(반격 자신은 다시 반격당하지 않음 -
     * performBasicAttack의 $allowCounterReflect=false), true를 돌려줘서 호출부가
     * 원래 행동을 대상에게 전혀 적용하지 않고 끝내게 한다.
     */
    private function checkCounter(Battle $battle, BattleUnit $actor, BattleUnit $target): bool
    {
        if (! $this->rollChance($this->xparam($target->fresh(), self::XPARAM_CNT))) {
            return false;
        }

        $this->performBasicAttack($battle, $target->fresh(), $actor, false);

        return true;
    }

    private function rollChance(float $rate): bool
    {
        return $rate > 0 && (mt_rand() / mt_getrandmax()) < $rate;
    }

    /**
     * 유닛의 트레잇 원본 소스 목록(xparam/sparam/상태이상 저항 판정 전부 여기서 합산) -
     * 아군은 소속 클래스, 적은 자기 자신(mz_enemies.traits)을 베이스로 쓰고, 여기에
     * 장착 중인 무기+방어구3슬롯 트레잇(equip_traits, spawn()이 그 시점의
     * user_mercenaries 장비를 기준으로 계산해 스냅샷해둔 것 - 전투 도중에는 장비를
     * 바꿀 수 없으니 그 전투 동안은 고정), 그리고 현재 걸린 상태들의 트레잇을
     * 더한다(암흑=HIT↓, 수면/마비/기절=EVA↓, 회피율 상승=EVA↑ 등
     * States.json에 이미 실제 값이 들어있음).
     *
     * @return array<int, array>
     */
    private function traitSourcesFor(BattleUnit $unit): array
    {
        $sources = [];
        $sources[] = $unit->class_id !== null
            ? (MzClass::find($unit->class_id)?->traits ?? [])
            : ($unit->unit->mzEnemy?->traits ?? []);
        $sources[] = $unit->equip_traits ?? [];

        foreach ($this->statesFor($unit) as $bus) {
            $sources[] = $bus->state->traits ?? [];
        }

        return $sources;
    }

    /** code=22(XPARAM)인 트레잇을 전부 더한다(가산형, 트레잇 없으면 0). */
    private function xparam(BattleUnit $unit, int $xparamId): float
    {
        $sum = 0.0;
        foreach ($this->traitSourcesFor($unit) as $traits) {
            foreach ($traits as $trait) {
                if (($trait['code'] ?? null) === 22 && ($trait['dataId'] ?? null) === $xparamId) {
                    $sum += (float) $trait['value'];
                }
            }
        }

        return $sum;
    }

    /**
     * code=23(SPARAM)인 트레잇을 전부 곱한다(MZ 표준 traitsPi 방식 - 트레잇이 없으면
     * 기본값 1.0). 예: MP소모율 트레잇 0.5짜리 장비 하나만 있으면 결과는 0.5(MP소모
     * 절반), 두 개 겹치면 0.25(곱연산이라 중첩될수록 강해짐 - MZ 원본과 동일 거동).
     */
    private function sparam(BattleUnit $unit, int $sparamId): float
    {
        $product = 1.0;
        foreach ($this->traitSourcesFor($unit) as $traits) {
            foreach ($traits as $trait) {
                if (($trait['code'] ?? null) === 23 && ($trait['dataId'] ?? null) === $sparamId) {
                    $product *= (float) $trait['value'];
                }
            }
        }

        return $product;
    }

    /**
     * code=21(PARAM)인 트레잇을 전부 곱한다(MZ 표준 paramRate, 기본 1.0) - dataId는
     * MZ 표준 순서(0=mhp/1=mmp/2=atk/3=def/4=mat/5=mdf/6=agi/7=luk). 능력치 상승/하락
     * 상태(공격력 상승 등)와 원소 태세(화염 태세 등의 ATK+10%)가 이 트레잇을 쓴다.
     */
    private function paramRate(BattleUnit $unit, int $paramId): float
    {
        $product = 1.0;
        foreach ($this->traitSourcesFor($unit) as $traits) {
            foreach ($traits as $trait) {
                if (($trait['code'] ?? null) === 21 && ($trait['dataId'] ?? null) === $paramId) {
                    $product *= (float) $trait['value'];
                }
            }
        }

        return $product;
    }

    /** 공식 평가/기본 공격 피해 계산에 쓰는 실효 스탯 - 기본 스탯 x paramRate(버프/디버프 상태 반영). */
    private function effectiveAtk(BattleUnit $unit): float
    {
        return $unit->atk * $this->paramRate($unit, 2);
    }

    private function effectiveDef(BattleUnit $unit): float
    {
        return $unit->def * $this->paramRate($unit, 3);
    }

    private function effectiveMat(BattleUnit $unit): float
    {
        return $unit->mat * $this->paramRate($unit, 4);
    }

    private function effectiveMdf(BattleUnit $unit): float
    {
        return $unit->mdf * $this->paramRate($unit, 5);
    }

    private function effectiveSpd(BattleUnit $unit): float
    {
        return $unit->spd * $this->paramRate($unit, 6);
    }

    /**
     * 상태이상 하나를 새로 거는 확률에 곱할 배율 - code=14(STATE_RESIST)가 그 상태 id를
     * 하나라도 가리키면 완전 면역(0.0, 확률 자체를 0으로 만들어 절대 안 걸리게),
     * 아니면 code=13(STATE_RATE, 곱연산·기본 1.0)을 전부 곱한 값을 쓴다(MZ 표준
     * Game_BattlerBase.stateRate/isStateResist 그대로). target_add_states(effects[]
     * 기반 네이티브 ADD_STATE) 확률 굴림에만 적용 - ApplySelfState류 커스텀 노트태그
     * 효과는 저항 대상이 아니다(서사적으로 항상 발동해야 하는 연출이라).
     */
    private function stateRateMultiplier(BattleUnit $unit, string $stateName): float
    {
        $state = MzState::where('name', $stateName)->first();
        if ($state === null) {
            return 1.0;
        }

        $product = 1.0;
        foreach ($this->traitSourcesFor($unit) as $traits) {
            foreach ($traits as $trait) {
                if (($trait['dataId'] ?? null) !== $state->id) {
                    continue;
                }
                if (($trait['code'] ?? null) === 14) {
                    return 0.0;
                }
                if (($trait['code'] ?? null) === 13) {
                    $product *= (float) $trait['value'];
                }
            }
        }

        return $product;
    }

    /**
     * 속성(element) 상성 - code=11(ELEMENT_RATE)을 대상 기준으로 곱한다(MZ 표준
     * Game_BattlerBase.elementRate, 트레잇 없으면 기본 1.0). elementId가 음수(-1)면
     * 이 스킬/기본 공격은 "공격자의 무기 속성을 그대로 물려받는다"는 뜻(MZ의
     * damage.elementId < 0, 예: 기본 공격)이라 attackElementsFor()로 공격자가 가진
     * ATTACK_ELEMENT(code=31) 전부를 모아 그중 대상에게 가장 유리한(배율이 가장 큰)
     * 값 하나를 쓴다(MZ Game_Action.elementsMaxRate와 동일 - 공격자가 속성을 여러 개
     * 가지고 있으면 그중 가장 잘 먹히는 속성으로 때린 것으로 침). 공격 속성이 하나도
     * 없으면(트레잇 미설정) 1.0.
     */
    private function elementRateFor(BattleUnit $attacker, BattleUnit $target, int $elementId): float
    {
        if ($elementId >= 0) {
            return $this->elementRate($target, $elementId);
        }

        $elements = $this->attackElementsFor($attacker);
        if ($elements === []) {
            return 1.0;
        }

        return max(array_map(fn (int $id) => $this->elementRate($target, $id), $elements));
    }

    /** code=11(ELEMENT_RATE)인 트레잇을 전부 곱한다(sparam과 동일한 곱연산, 기본 1.0). */
    private function elementRate(BattleUnit $target, int $elementId): float
    {
        $product = 1.0;
        foreach ($this->traitSourcesFor($target) as $traits) {
            foreach ($traits as $trait) {
                if (($trait['code'] ?? null) === 11 && ($trait['dataId'] ?? null) === $elementId) {
                    $product *= (float) $trait['value'];
                }
            }
        }

        return $product;
    }

    /** code=31(ATTACK_ELEMENT)인 트레잇의 dataId(속성 id) 전부 - 무기의 기본 속성(대부분 1=물리적), 상태의 "OO 태세"류가 추가로 부여. */
    private function attackElementsFor(BattleUnit $unit): array
    {
        $ids = [];
        foreach ($this->traitSourcesFor($unit) as $traits) {
            foreach ($traits as $trait) {
                if (($trait['code'] ?? null) === 31) {
                    $ids[] = (int) $trait['dataId'];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * 속성 상성 -> PDR/MDR(장비 SPARAM, hitType 1=물리/2=마법일 때만 - 필중(0)은
     * 물리도 마법도 아니라 적용 안 함, MZ의 isPhysical()/isMagical() 판정과 동일) ->
     * Shield(1회성 흡수) -> DamageTakenRate(배율) -> Undying(1회 한정 HP1 방어) 순으로 보정.
     */
    private function applyIncomingDamageModifiers(BattleUnit $attacker, BattleUnit $target, int $damage, int $hitType, int $elementId): int
    {
        $damage = (int) round($damage * $this->elementRateFor($attacker, $target, $elementId));

        if ($hitType === 1) {
            $damage = (int) round($damage * $this->sparam($target, self::SPARAM_PDR));
        } elseif ($hitType === 2) {
            $damage = (int) round($damage * $this->sparam($target, self::SPARAM_MDR));
        }

        $states = $this->statesFor($target);

        foreach ($states as $bus) {
            $shieldPct = $bus->state->tags['shield_pct'] ?? null;
            if ($shieldPct !== null && $damage > 0) {
                $absorb = (int) round($target->max_hp * $shieldPct / 100);
                $damage = max(0, $damage - $absorb);
                $this->removeState($target, $bus->state->name);
            }
        }

        foreach ($states as $bus) {
            $rate = $bus->state->tags['damage_taken_rate'] ?? null;
            if ($rate !== null) {
                $damage = (int) round($damage * $rate / 100);
            }
        }

        $freshTarget = $target->fresh();
        foreach ($states as $bus) {
            if (($bus->state->tags['undying'] ?? false) && $damage >= $freshTarget->current_hp) {
                $this->removeState($target, $bus->state->name);

                return max(0, $freshTarget->current_hp - 1);
            }
        }

        return max(0, $damage);
    }

    /**
     * 자기 턴 시작 시 자신에게 걸린 상태 전부를 처리한다: ① DoT/HoT 적용 ② 지속시간이
     * 있는 상태(auto_removal_timing 1/2, remaining_turns not null)는 1 차감하고
     * 0이 되면 제거. MZ는 1(행동 종료시)/2(턴 종료시, `$gameTroop` 전체 라운드 기준)를
     * 구분하지만 이 엔진은 라운드 없이 유닛별 ATB로 개별 행동하므로 두 값을 구분하지
     * 않고 전부 "이 유닛이 자기 턴을 마칠 때마다 1씩 감소"로 단순화했다. 0(턴 수로는
     * 안 사라짐)이면 remaining_turns가 애초에 null이라 여기서 건드리지 않는다 -
     * RemoveState/RemoveTargetState 태그나 Shield/Undying처럼 코드가 직접 지워야 없어짐.
     */
    private function tickStatuses(BattleUnit $actor): void
    {
        foreach ($this->statesFor($actor) as $bus) {
            $tags = $bus->state->tags;

            if (($pct = $tags['dot_percent'] ?? null) !== null) {
                $fresh = $actor->fresh();
                $fresh->current_hp = max(0, $fresh->current_hp - (int) round($fresh->max_hp * $pct / 100));
                $fresh->save();
            }
            if (($pct = $tags['hot_percent'] ?? null) !== null) {
                $fresh = $actor->fresh();
                $fresh->current_hp = min($fresh->max_hp, $fresh->current_hp + (int) round($fresh->max_hp * $pct / 100));
                $fresh->save();
            }

            if ($bus->remaining_turns !== null) {
                if ($bus->remaining_turns <= 1) {
                    $bus->delete();
                } else {
                    $bus->remaining_turns--;
                    $bus->save();
                }
            }
        }

        // HRG/MRG(XPARAM dataId 7/8) - DotPercent/HotPercent(커스텀 태그, 상태별로
        // 각자 적용)와 달리 이건 클래스/장비/상태 트레잇을 전부 합산한 유닛 단위
        // 값이라 상태 하나하나가 아니라 턴당 한 번만 계산한다(MZ Game_Battler.
        // regenerateHp/regenerateMp와 동일). "독" 상태가 이 방식(HRG 음수)으로
        // 매 턴 피해를 준다 - 커스텀 DotPercent 태그가 따로 없다.
        $hrg = $this->xparam($actor, self::XPARAM_HRG);
        if ($hrg !== 0.0) {
            $fresh = $actor->fresh();
            $fresh->current_hp = max(0, min($fresh->max_hp, $fresh->current_hp + (int) round($fresh->max_hp * $hrg)));
            $fresh->save();
        }
        $mrg = $this->xparam($actor, self::XPARAM_MRG);
        if ($mrg !== 0.0) {
            $fresh = $actor->fresh();
            $fresh->current_mp = max(0, min($fresh->max_mp, $fresh->current_mp + (int) round($fresh->max_mp * $mrg)));
            $fresh->save();
        }
    }

    private function statesFor(BattleUnit $unit): Collection
    {
        return BattleUnitState::where('battle_unit_id', $unit->id)->with('state')->get();
    }

    private function hasState(BattleUnit $unit, string $name): bool
    {
        return $this->statesFor($unit)->contains(fn (BattleUnitState $bus) => $bus->state->name === $name);
    }

    private function hasTaunt(BattleUnit $unit): bool
    {
        return $this->statesFor($unit)->contains(fn (BattleUnitState $bus) => $bus->state->tags['taunt'] ?? false);
    }

    /** @return array<int, string> */
    private function stateNames(BattleUnit $unit): array
    {
        return $this->statesFor($unit)->map(fn (BattleUnitState $bus) => $bus->state->name)->all();
    }

    /**
     * MZ의 stateMotionIndex() 재현 - 걸린 상태 중 motion이 있는 것들을
     * priority 내림차순(동률이면 state id 오름차순)으로 정렬해 1순위 것의
     * motion만 반영한다(motion 1=abnormal, 2=sleep, 3=dead는 사망 처리에서 별도로 다룬다).
     */
    private function stateMotionFor(BattleUnit $unit): ?string
    {
        $top = $this->statesFor($unit)
            ->map(fn (BattleUnitState $bus) => $bus->state)
            ->filter(fn (MzState $state) => $state->motion !== 0)
            ->sort(fn (MzState $a, MzState $b) => $b->priority <=> $a->priority ?: $a->id <=> $b->id)
            ->first();

        if ($top === null) {
            return null;
        }

        return match ($top->motion) {
            2 => 'sleep',
            1 => 'abnormal',
            default => null,
        };
    }

    /**
     * auto_removal_timing이 0(States.json 기준 "턴 수로는 안 사라짐")이면
     * remaining_turns를 null로(RemoveState류 태그나 코드가 직접 지우기 전까지 영구),
     * 그 외엔 min_turns~max_turns 사이의 무작위 턴 수를 굴려서 채운다(MZ 에디터의
     * "지속시간(가변)" 그대로 - min===max면 항상 그 값 고정).
     */
    /**
     * $turnsOverride를 주면 상태 자신의 min_turns/max_turns/auto_removal_timing을
     * 무시하고 그 턴수를 그대로 쓴다 - "저항의 물약"처럼 같은 상태를 원래 정의된
     * 지속시간과 다르게(원본은 5턴) 걸고 싶은 소모 아이템 전용 용도다. 다른 스킬이
     * 같은 상태를 정상적인(상태 자신의) 지속시간으로 거는 데는 영향 없음.
     */
    private function applyState(BattleUnit $unit, string $name, int $currentTurn, ?int $turnsOverride = null): void
    {
        $state = MzState::where('name', $name)->first();
        if ($state === null) {
            return;
        }

        $already = BattleUnitState::where('battle_unit_id', $unit->id)->where('state_id', $state->id)->exists();
        if ($already) {
            return;
        }

        $remainingTurns = $turnsOverride ?? ($state->auto_removal_timing !== 0
            ? random_int($state->min_turns, $state->max_turns)
            : null);

        BattleUnitState::create([
            'battle_unit_id' => $unit->id,
            'state_id' => $state->id,
            'remaining_turns' => $remainingTurns,
            'applied_turn' => $currentTurn,
        ]);
    }

    private function removeState(BattleUnit $unit, string $name): void
    {
        $state = MzState::where('name', $name)->first();
        if ($state === null) {
            return;
        }

        BattleUnitState::where('battle_unit_id', $unit->id)->where('state_id', $state->id)->delete();
    }

    /** @return array{id:int,status:string,winner_side:?string,turn_number:int,units:array,logs:array} */
    public function getState(Battle $battle): array
    {
        $battle->refresh();

        return [
            'id' => $battle->id,
            'status' => $battle->status,
            'winner_side' => $battle->winner_side,
            'turn_number' => $battle->turn_number,
            'units' => $battle->units()->with(['unit', 'mzClass'])->get()->map(fn (BattleUnit $u) => [
                'id' => $u->id,
                'unit_id' => $u->unit_id,
                'name' => $u->unit->name,
                'sprite' => $u->unit->sprite,
                'side' => $u->side,
                'slot' => $u->slot,
                'spd' => $u->spd,
                'atb_gauge' => $u->atb_gauge,
                'attack_motion' => $u->attack_motion,
                'weapon_effect_name' => $u->weapon_effect_name,
                'dragonbones_skeleton' => $u->dragonbones_skeleton,
                'dragonbones_atlas' => $u->dragonbones_atlas,
                'dragonbones_motions' => $u->dragonbones_motions,
                'dragonbones_scale' => $u->dragonbones_scale,
                'max_hp' => $u->max_hp,
                'max_mp' => $u->max_mp,
                'current_hp' => $u->current_hp,
                'current_mp' => $u->current_mp,
                'party_hud_icon' => $u->mzClass?->party_hud_icon,
                'state_motion' => $this->stateMotionFor($u),
            ])->all(),
            'logs' => $battle->logs()->get()->map(fn (BattleLog $l) => [
                'turn_number' => $l->turn_number,
                'actor_battle_unit_id' => $l->actor_battle_unit_id,
                'target_battle_unit_id' => $l->target_battle_unit_id,
                'action_type' => $l->action_type,
                'damage' => $l->damage,
                'is_critical' => $l->is_critical,
                'hit_outcome' => $l->hit_outcome,
                'target_hp_after' => $l->target_hp_after,
                'targets' => $l->targets,
                'skill_motion' => $l->skill_motion,
                'circle_image_name' => $l->circle_image_name,
                'circle_image_scale' => $l->circle_image_scale,
                'item_name' => $l->item_name,
            ])->all(),
        ];
    }
}
