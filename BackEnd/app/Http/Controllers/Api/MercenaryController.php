<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MercenaryGambit;
use App\Models\Unit;
use App\Models\UserMercenary;
use Illuminate\Http\Request;

class MercenaryController extends Controller
{
    /** 당분간 전원 무료 영입 - 가격 밸런스는 나중에 다시 설계. */
    private function nextPrice(int $ownedCount): int
    {
        return 0;
    }

    /** Every purchasable mercenary, flagged with whether the current account already owns it. */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $mercenaries = Unit::where('type', 'ally')->orderBy('id')->get();
        $ownedIds = UserMercenary::where('user_id', $userId)->pluck('unit_id')->all();
        $price = $this->nextPrice(count($ownedIds));

        $result = $mercenaries->map(function (Unit $unit) use ($ownedIds, $price) {
            $owned = in_array($unit->id, $ownedIds, true);

            return array_merge($unit->toArray(), [
                'owned' => $owned,
                'price' => $owned ? 0 : $price,
            ]);
        });

        return response()->json($result);
    }

    public function purchase(Request $request, int $id)
    {
        $userId = $request->user()->id;
        $unit = Unit::find($id);

        if ($unit === null || $unit->type !== 'ally') {
            return response()->json(['message' => '용병을 찾을 수 없습니다.'], 404);
        }

        if (UserMercenary::where('user_id', $userId)->where('unit_id', $id)->exists()) {
            return response()->json(['message' => '이미 보유한 용병입니다.'], 409);
        }

        $ownedCount = UserMercenary::where('user_id', $userId)->count();
        $price = $this->nextPrice($ownedCount);

        $user = $request->user();
        if ($user->gold < $price) {
            return response()->json(['message' => '골드가 부족합니다.'], 400);
        }

        $user->gold -= $price;
        $user->save();

        $userMercenary = UserMercenary::create([
            'user_id' => $userId,
            'unit_id' => $id,
            'weapon_id' => $unit->defaultWeaponId(),
        ]);

        // 갓 산 용병도 바로 전투에서 움직이도록 프리셋 1에 기본 규칙(항상->공격)을 심어둔다.
        MercenaryGambit::create([
            'user_mercenary_id' => $userMercenary->id,
            'preset_number' => 1,
            'priority' => 1,
            'condition_kind' => 'always',
            'action_type' => 'attack',
        ]);

        return response()->json(['gold' => $user->gold], 201);
    }
}
