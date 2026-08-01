<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MzArmor;
use App\Models\MzItem;
use App\Models\MzWeapon;
use App\Models\UserArmor;
use App\Models\UserItem;
use App\Models\UserWeapon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * 계정(용병단) 단위 소모 아이템/무기/방어구 인벤토리 - user_items/user_weapons/
 * user_armors, 셋 다 (user_id, mz_*_id, quantity) 구조로 완전히 동일한 패턴이라
 * 컨트롤러 하나로 공유한다. 무기/방어구는 아직 특정 용병에게 "장착"하는 시스템이
 * 없어서(ActorSeeder가 초기 무기를 시딩 시점에 고정 스냅샷) 지금은 소유/재고
 * 추적까지만 - 소모 아이템만 gambit "아이템을 사용한다"에서 실제로 재고를
 * 소모한다(BattleEngine::consumeItemStock() 참고).
 *
 * 구매는 MercenaryController와 동일하게 각 mz_* 행의 price를 그대로 쓰고
 * (mz_items는 지금 전부 0원, mz_weapons/mz_armors는 mz_project 원본 가격) 골드가
 * 부족하면 거부한다 - 살 때마다 quantity가 쌓이는 스택형이라(용병처럼 "이미
 * 보유"로 막지 않음) 여러 개 살 수 있다.
 */
class InventoryController extends Controller
{
    public function items(Request $request)
    {
        return $this->catalog($request, MzItem::class, UserItem::class, 'mz_item_id');
    }

    public function purchaseItem(Request $request, int $id)
    {
        return $this->purchase($request, MzItem::class, UserItem::class, 'mz_item_id', $id);
    }

    public function weapons(Request $request)
    {
        return $this->catalog($request, MzWeapon::class, UserWeapon::class, 'mz_weapon_id');
    }

    public function purchaseWeapon(Request $request, int $id)
    {
        return $this->purchase($request, MzWeapon::class, UserWeapon::class, 'mz_weapon_id', $id);
    }

    public function armors(Request $request)
    {
        return $this->catalog($request, MzArmor::class, UserArmor::class, 'mz_armor_id');
    }

    public function purchaseArmor(Request $request, int $id)
    {
        return $this->purchase($request, MzArmor::class, UserArmor::class, 'mz_armor_id', $id);
    }

    /**
     * @param class-string<Model> $catalogModel mz_items/mz_weapons/mz_armors
     * @param class-string<Model> $ownedModel user_items/user_weapons/user_armors
     */
    private function catalog(Request $request, string $catalogModel, string $ownedModel, string $foreignKey)
    {
        $userId = $request->user()->id;
        $owned = $ownedModel::where('user_id', $userId)->get()->keyBy($foreignKey);

        $rows = $catalogModel::orderBy('id')->get()->map(function (Model $row) use ($owned) {
            return array_merge($row->toArray(), [
                'quantity' => $owned->get($row->id)?->quantity ?? 0,
            ]);
        });

        return response()->json($rows);
    }

    /**
     * @param class-string<Model> $catalogModel
     * @param class-string<Model> $ownedModel
     */
    private function purchase(Request $request, string $catalogModel, string $ownedModel, string $foreignKey, int $id)
    {
        $data = $request->validate(['quantity' => ['sometimes', 'integer', 'min:1', 'max:99']]);
        $buyCount = $data['quantity'] ?? 1;

        $row = $catalogModel::find($id);
        if ($row === null) {
            return response()->json(['message' => '존재하지 않는 항목입니다.'], 404);
        }

        $user = $request->user();
        $totalPrice = $row->price * $buyCount;
        if ($user->gold < $totalPrice) {
            return response()->json(['message' => '골드가 부족합니다.'], 400);
        }

        $user->gold -= $totalPrice;
        $user->save();

        $owned = $ownedModel::firstOrNew(['user_id' => $user->id, $foreignKey => $id]);
        $owned->quantity = ($owned->quantity ?? 0) + $buyCount;
        $owned->save();

        return response()->json(['gold' => $user->gold, 'quantity' => $owned->quantity], 201);
    }
}
