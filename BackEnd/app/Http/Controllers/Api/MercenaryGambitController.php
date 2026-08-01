<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MercenaryGambit;
use App\Models\Unit;
use App\Models\UserMercenary;
use App\Support\GambitCatalog;
use Illuminate\Http\Request;

class MercenaryGambitController extends Controller
{
    private const PRESET_NUMBERS = [1, 2, 3, 4, 5];

    /** Not a gameplay limit — just a sanity cap so a malformed/abusive request can't write an unbounded number of rows. */
    private const MAX_RULES_PER_PRESET = 30;

    /**
     * 조건/행동 드롭다운을 채우는 카탈로그 - 스킬은 이 용병의 직업(+공용)에 맞게
     * 필터링되지만, 소모 아이템은 직업 구분 없이 전원 같은 목록을 그대로 받는다.
     */
    public function catalog(Request $request, int $unitId)
    {
        $userMercenary = $this->findOwned($request, $unitId);
        if ($userMercenary === null) {
            return response()->json(['message' => '보유하지 않은 용병입니다.'], 404);
        }

        return response()->json([
            'skills' => GambitCatalog::skillsForUnit($userMercenary->unit),
            'items' => GambitCatalog::allItems(),
            'debuffs' => GambitCatalog::allDebuffs(),
        ]);
    }

    private function findOwned(Request $request, int $unitId): ?UserMercenary
    {
        return UserMercenary::where('user_id', $request->user()->id)->where('unit_id', $unitId)->first();
    }

    /** All 5 presets' rule lists for one of the account's owned mercenaries, plus which preset is currently active. */
    public function show(Request $request, int $unitId)
    {
        $userMercenary = $this->findOwned($request, $unitId);
        if ($userMercenary === null) {
            return response()->json(['message' => '보유하지 않은 용병입니다.'], 404);
        }

        $presets = array_fill_keys(self::PRESET_NUMBERS, []);
        foreach (
            MercenaryGambit::where('user_mercenary_id', $userMercenary->id)->orderBy('priority')->get() as $rule
        ) {
            $presets[(int) $rule->preset_number][] = $rule;
        }

        return response()->json([
            'active_preset' => (int) $userMercenary->active_gambit_preset,
            'presets' => $presets,
        ]);
    }

    /** Wholesale-replaces one preset's rule list. Body: { preset: 1-5, rules: [rule, ...] } (any length). */
    public function update(Request $request, int $unitId)
    {
        $userMercenary = $this->findOwned($request, $unitId);
        if ($userMercenary === null) {
            return response()->json(['message' => '보유하지 않은 용병입니다.'], 404);
        }

        $data = $request->validate([
            'preset' => ['required', 'integer'],
            'rules' => ['required', 'array'],
        ]);

        $presetNumber = (int) $data['preset'];
        if (! in_array($presetNumber, self::PRESET_NUMBERS, true)) {
            return response()->json(['message' => '올바르지 않은 프리셋 번호입니다.'], 400);
        }

        $rulesInput = array_values($data['rules']);
        if (count($rulesInput) > self::MAX_RULES_PER_PRESET) {
            return response()->json(['message' => '규칙은 프리셋당 최대 ' . self::MAX_RULES_PER_PRESET . '개까지 만들 수 있습니다.'], 400);
        }

        $rows = [];
        foreach ($rulesInput as $index => $rule) {
            if (! is_array($rule)) {
                return response()->json(['message' => '규칙 ' . ($index + 1) . '의 형식이 올바르지 않습니다.'], 400);
            }

            $error = $this->validateRule($rule, $userMercenary->unit);
            if ($error !== null) {
                return response()->json(['message' => '규칙 ' . ($index + 1) . ': ' . $error], 400);
            }

            $rows[] = $this->normalizeRule($userMercenary->id, $presetNumber, $index + 1, $rule);
        }

        MercenaryGambit::where('user_mercenary_id', $userMercenary->id)->where('preset_number', $presetNumber)->delete();
        if ($rows !== []) {
            MercenaryGambit::insert($rows);
        }

        return response()->noContent();
    }

    /** Swaps which preset is in effect for this mercenary. Body: { preset: 1-5 }. */
    public function setActive(Request $request, int $unitId)
    {
        $userMercenary = $this->findOwned($request, $unitId);
        if ($userMercenary === null) {
            return response()->json(['message' => '보유하지 않은 용병입니다.'], 404);
        }

        $data = $request->validate(['preset' => ['required', 'integer']]);
        $presetNumber = (int) $data['preset'];
        if (! in_array($presetNumber, self::PRESET_NUMBERS, true)) {
            return response()->json(['message' => '올바르지 않은 프리셋 번호입니다.'], 400);
        }

        $userMercenary->active_gambit_preset = $presetNumber;
        $userMercenary->save();

        return response()->noContent();
    }

    private function validateRule(array $rule, Unit $unit): ?string
    {
        $conditionKind = $rule['condition_kind'] ?? null;
        if (! in_array($conditionKind, ['always', 'first_action', 'stat', 'debuff', 'position', 'dead_ally'], true)) {
            return '올바르지 않은 조건입니다.';
        }

        if ($conditionKind === 'stat') {
            if (! in_array($rule['condition_scope'] ?? null, ['self', 'ally'], true)) {
                return '올바르지 않은 대상입니다.';
            }
            if (! in_array($rule['condition_stat'] ?? null, ['hp', 'mp'], true)) {
                return '올바르지 않은 스탯입니다.';
            }
            if (! in_array($rule['condition_value_type'] ?? null, ['percent', 'flat'], true)) {
                return '올바르지 않은 값 종류입니다.';
            }
            if (! in_array($rule['condition_operator'] ?? null, ['gte', 'lte'], true)) {
                return '올바르지 않은 연산자입니다.';
            }
            if (! is_numeric($rule['condition_value'] ?? null)) {
                return '수치를 입력해주세요.';
            }
            $value = (int) $rule['condition_value'];
            $max = $rule['condition_value_type'] === 'percent' ? 100 : 9999;
            if ($value < 1 || $value > $max) {
                return "수치는 1~{$max} 사이여야 합니다.";
            }
        } elseif ($conditionKind === 'debuff') {
            if (! in_array($rule['condition_scope'] ?? null, ['self', 'ally', 'enemy'], true)) {
                return '올바르지 않은 대상입니다.';
            }
            if (! GambitCatalog::debuffExists((string) ($rule['condition_debuff_key'] ?? ''))) {
                return '올바르지 않은 디버프입니다.';
            }
        } elseif ($conditionKind === 'position') {
            if (! in_array($rule['condition_position'] ?? null, ['front', 'back'], true)) {
                return '올바르지 않은 위치입니다.';
            }
        }

        $actionType = $rule['action_type'] ?? null;
        if (! in_array($actionType, ['attack', 'skill', 'item', 'cancel'], true)) {
            return '올바르지 않은 행동입니다.';
        }
        if ($actionType === 'skill') {
            $skillKey = (string) ($rule['action_skill_key'] ?? '');
            $skill = GambitCatalog::skill($skillKey);
            if ($skill === null || ($skill->class_id !== null && $skill->class_id !== $unit->class_id)) {
                return '이 용병이 배울 수 없는 스킬입니다.';
            }
        } elseif ($actionType === 'item') {
            $itemKey = (string) ($rule['action_skill_key'] ?? '');
            if (! GambitCatalog::itemExists($itemKey)) {
                return '존재하지 않는 아이템입니다.';
            }
        }

        return null;
    }

    /** Drops any leftover fields from a previous condition/action kind so half-filled rows never persist. */
    private function normalizeRule(int $userMercenaryId, int $presetNumber, int $priority, array $rule): array
    {
        $conditionKind = $rule['condition_kind'];

        $row = [
            'user_mercenary_id' => $userMercenaryId,
            'preset_number' => $presetNumber,
            'priority' => $priority,
            'condition_kind' => $conditionKind,
            'condition_scope' => null,
            'condition_stat' => null,
            'condition_value_type' => null,
            'condition_operator' => null,
            'condition_value' => null,
            'condition_debuff_key' => null,
            'condition_position' => null,
            'action_type' => $rule['action_type'],
            'action_skill_key' => null,
        ];

        if ($conditionKind === 'stat') {
            $row['condition_scope'] = $rule['condition_scope'];
            $row['condition_stat'] = $rule['condition_stat'];
            $row['condition_value_type'] = $rule['condition_value_type'];
            $row['condition_operator'] = $rule['condition_operator'];
            $row['condition_value'] = (int) $rule['condition_value'];
        } elseif ($conditionKind === 'debuff') {
            $row['condition_scope'] = $rule['condition_scope'];
            $row['condition_debuff_key'] = $rule['condition_debuff_key'];
        } elseif ($conditionKind === 'position') {
            $row['condition_position'] = $rule['condition_position'];
        }

        if ($row['action_type'] === 'skill' || $row['action_type'] === 'item') {
            $row['action_skill_key'] = $rule['action_skill_key'];
        }

        return $row;
    }
}
