<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MzArmor;
use App\Models\MzWeapon;
use App\Models\UserArmor;
use App\Models\UserMercenary;
use App\Models\UserWeapon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * 용병 개인별 장비 슬롯(무기/방패/몸/장신구, user_mercenaries의 weapon_id 등 4컬럼)
 * 조회/장착/해제. user_weapons/user_armors(quantity 스택형 보유 수량)를 "풀"로
 * 보고, 장착은 그 풀에서 1개를 예약(quantity -1)해 슬롯에 고정하는 것으로,
 * 해제/교체는 이전 장비를 풀로 반환(quantity +1)하는 것으로 구현한다 - 그래서
 * 두 용병이 같은 카탈로그 아이템 1개를 동시에 낀 채로 있는 일이 생기지 않는다.
 *
 * 무기는 용병 직업에 맞는 무기 계열(wtype)만 장착 가능하도록 제한한다 - 이
 * 매핑은 mz_project 데이터(Classes.json의 code41 트레잇)에 실제로 들어있지
 * 않고(전부 비활성 placeholder) 무기 생성 당시 "직업별 20종" 작업에서 쓴
 * 계열 배정을 그대로 코드에 옮긴 것([[MZ 데이터는 이름으로 참조]] 컨벤션과
 * 별개로, 이건 이름이 아니라 구조적 매핑이라 상수로 둔다). 방어구(방패/몸/
 * 장신구)는 직업 제한 없이 etype만 맞으면 누구나 장착 가능 - 방어구 컨셉
 * 자체가 직업 전용이 아니라 범용으로 설계됐다.
 */
class EquipmentController extends Controller
{
    private const SLOTS = ['weapon', 'shield', 'body_armor', 'accessory'];

    /** slot => [DB 컬럼, 카탈로그 모델, 보유 모델, 보유 테이블 외래키, 필요 etypeId(무기는 null)]. */
    private const SLOT_CONFIG = [
        'weapon' => ['weapon_id', MzWeapon::class, UserWeapon::class, 'mz_weapon_id', null],
        'shield' => ['shield_id', MzArmor::class, UserArmor::class, 'mz_armor_id', 2],
        'body_armor' => ['body_armor_id', MzArmor::class, UserArmor::class, 'mz_armor_id', 4],
        'accessory' => ['accessory_id', MzArmor::class, UserArmor::class, 'mz_armor_id', 5],
    ];

    /** classId => 장착 가능한 wtypeId. 무기 생성 시 정한 직업-무기 계열 배정을 그대로 따른다. */
    private const CLASS_WEAPON_TYPE = [
        1 => 1, // 검사 -> 검
        2 => 2, // 궁술사 -> 활
        3 => 3, // 흑마술사 -> 지팡이
        4 => 4, // 백마도사 -> 홀장
        5 => 1, // 기사 -> 검
        6 => 5, // 창술사 -> 창
        7 => 6, // 비술사 -> 책
        8 => 7, // 악마술사 -> 오브
    ];

    public function show(Request $request, int $unitId)
    {
        $mercenary = $this->findOwnedMercenary($request, $unitId);
        if ($mercenary === null) {
            return response()->json(['message' => '보유하지 않은 용병입니다.'], 404);
        }

        return response()->json($this->describe($mercenary));
    }

    public function update(Request $request, int $unitId)
    {
        $mercenary = $this->findOwnedMercenary($request, $unitId);
        if ($mercenary === null) {
            return response()->json(['message' => '보유하지 않은 용병입니다.'], 404);
        }

        $data = $request->validate([
            'slot' => ['required', Rule::in(self::SLOTS)],
            'item_id' => ['nullable', 'integer'],
        ]);

        [$column, $catalogModel, $ownedModel, $foreignKey, $requiredEtypeId] = self::SLOT_CONFIG[$data['slot']];
        $itemId = $data['item_id'] ?? null;
        $currentId = $mercenary->{$column};

        if ($itemId === $currentId) {
            return response()->json($this->describe($mercenary));
        }

        if ($itemId !== null) {
            $item = $catalogModel::find($itemId);
            if ($item === null) {
                return response()->json(['message' => '존재하지 않는 장비입니다.'], 404);
            }
            if ($data['slot'] === 'weapon') {
                $allowedWtype = self::CLASS_WEAPON_TYPE[$mercenary->unit->class_id] ?? null;
                if ($allowedWtype === null || $item->wtype_id !== $allowedWtype) {
                    return response()->json(['message' => '이 용병의 직업으로는 장착할 수 없는 무기입니다.'], 422);
                }
            } elseif ($item->etype_id !== $requiredEtypeId) {
                return response()->json(['message' => '이 슬롯에 장착할 수 없는 방어구입니다.'], 422);
            }

            $owned = $ownedModel::where('user_id', $request->user()->id)->where($foreignKey, $itemId)->value('quantity') ?? 0;
            if ($owned < 1) {
                return response()->json(['message' => '보유하지 않은 장비입니다.'], 400);
            }
        }

        DB::transaction(function () use ($request, $mercenary, $column, $ownedModel, $foreignKey, $currentId, $itemId) {
            $userId = $request->user()->id;

            if ($currentId !== null) {
                $returned = $ownedModel::firstOrNew(['user_id' => $userId, $foreignKey => $currentId]);
                $returned->quantity = ($returned->quantity ?? 0) + 1;
                $returned->save();
            }

            if ($itemId !== null) {
                $reserved = $ownedModel::where('user_id', $userId)->where($foreignKey, $itemId)->first();
                $reserved->quantity -= 1;
                $reserved->save();
            }

            $mercenary->{$column} = $itemId;
            $mercenary->save();
        });

        return response()->json($this->describe($mercenary->fresh()));
    }

    private function findOwnedMercenary(Request $request, int $unitId): ?UserMercenary
    {
        return UserMercenary::where('user_id', $request->user()->id)->where('unit_id', $unitId)->with('unit')->first();
    }

    private function describe(UserMercenary $mercenary): array
    {
        $userId = $mercenary->user_id;
        $equipped = [];
        $options = [];

        foreach (self::SLOT_CONFIG as $slot => [$column, $catalogModel, $ownedModel, $foreignKey, $requiredEtypeId]) {
            $currentId = $mercenary->{$column};
            $equipped[$slot] = $currentId !== null ? $this->describeItem($catalogModel::find($currentId)) : null;

            $query = $slot === 'weapon'
                ? $catalogModel::where('wtype_id', self::CLASS_WEAPON_TYPE[$mercenary->unit->class_id] ?? 0)
                : $catalogModel::where('etype_id', $requiredEtypeId);

            $owned = $ownedModel::where('user_id', $userId)->where('quantity', '>', 0)->pluck('quantity', $foreignKey);

            $options[$slot] = $query->get()
                ->filter(fn ($item) => $owned->has($item->id))
                ->map(fn ($item) => $this->describeItem($item) + ['quantity' => $owned[$item->id]])
                ->values();
        }

        return ['equipped' => $equipped, 'options' => $options];
    }

    private function describeItem($item): ?array
    {
        if ($item === null) {
            return null;
        }

        return ['id' => $item->id, 'name' => $item->name, 'icon_index' => $item->icon_index];
    }
}
