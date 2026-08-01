<?php

namespace Database\Seeders;

use App\Models\MzItem;
use App\Models\MzWeapon;
use App\Models\User;
use App\Models\UserItem;
use App\Models\UserWeapon;
use Illuminate\Database\Seeder;

/**
 * 조합 시스템 수동 테스트용 - 기본 시더 체인(DatabaseSeeder)에는 안 넣었다.
 * `php artisan db:seed --class=CraftingTestSeeder`로 직접 돌려야 한다.
 * 모든 계정에게 조합 재료(occasion=3, [[조합 재료]] 89종) 재고를 넉넉히 채우고
 * 골드도 보충해서, 프론트 없이 /api/crafting/{workshop}/start + /collect를 바로
 * 찔러볼 수 있게 한다. 재료가 아이템뿐 아니라 무기/방어구 종류일 수도 있는 새
 * <Crafting> 문법(테스트 샘플 "강화 검"이 재료로 "낡은 검"(무기)을 요구함)까지
 * 확인할 수 있게 낡은 검도 같이 지급한다. 재실행해도 안전(재고를 절대치로 맞추고,
 * 골드는 최소치 보장만 함).
 */
class CraftingTestSeeder extends Seeder
{
    private const MATERIAL_QUANTITY = 99;
    private const WEAPON_QUANTITY = 5;
    private const MIN_GOLD = 10000;

    public function run(): void
    {
        $materialIds = MzItem::where('occasion', 3)->pluck('id');
        $starterWeaponId = MzWeapon::where('name', '낡은 검')->value('id');

        $userCount = 0;
        User::each(function (User $user) use ($materialIds, $starterWeaponId, &$userCount) {
            foreach ($materialIds as $itemId) {
                $owned = UserItem::firstOrNew(['user_id' => $user->id, 'mz_item_id' => $itemId]);
                $owned->quantity = self::MATERIAL_QUANTITY;
                $owned->save();
            }

            if ($starterWeaponId !== null) {
                $owned = UserWeapon::firstOrNew(['user_id' => $user->id, 'mz_weapon_id' => $starterWeaponId]);
                $owned->quantity = self::WEAPON_QUANTITY;
                $owned->save();
            }

            if ($user->gold < self::MIN_GOLD) {
                $user->gold = self::MIN_GOLD;
                $user->save();
            }

            $userCount++;
        });

        $this->command?->info("조합 재료 테스트 지급 완료 (계정 {$userCount}개, 재료 {$materialIds->count()}종 각 " . self::MATERIAL_QUANTITY . '개 + 낡은 검 ' . self::WEAPON_QUANTITY . '개).');
    }
}
