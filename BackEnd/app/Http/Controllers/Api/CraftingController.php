<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CraftingJob;
use App\Models\MzArmor;
use App\Models\MzItem;
use App\Models\MzWeapon;
use App\Models\UserArmor;
use App\Models\UserItem;
use App\Models\UserWeapon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * 연구소(포션류)/대장간(무기·방어구) 제작. 완성품(mz_items/mz_weapons/mz_armors)의
 * CraftingEditor(시간/골드/재료 구조화 필드)를 각 Seeder가 시딩 시점에
 * tags['crafting']으로 미리 만들어둔 걸 레시피로 쓴다. recipe_type이 'item'이면 무조건 lab, 'weapon'/
 * 'armor'면 무조건 smithy - 클라이언트가 workshop을 잘못 보내 슬롯 캡을 우회하지
 * 못하도록 서버에서 강제한다. 재료는 종류(아이템/무기/방어구)마다 다른 테이블
 * 소속일 수 있어 각 재료의 type으로 어느 카탈로그/인벤토리 테이블을 볼지 정하고,
 * 그 안에서 이름으로 찾는다([[MZ 데이터는 이름으로 참조]] 컨벤션).
 */
class CraftingController extends Controller
{
    private const SLOT_CAP = 5;
    private const WORKSHOPS = ['lab', 'smithy'];
    private const RECIPE_TYPES = ['item', 'weapon', 'armor'];

    public function index(Request $request)
    {
        $jobs = CraftingJob::where('user_id', $request->user()->id)->orderBy('finishes_at')->get();

        return response()->json($jobs->map(fn (CraftingJob $job) => $this->describeJob($job))->values());
    }

    public function recipes(Request $request, string $workshop)
    {
        if (! in_array($workshop, self::WORKSHOPS, true)) {
            return response()->json(['message' => '알 수 없는 작업장입니다.'], 404);
        }

        $user = $request->user();
        $rows = [];

        if ($workshop === 'lab') {
            foreach (MzItem::orderBy('id')->get() as $item) {
                $crafting = $item->tags['crafting'] ?? null;
                if ($crafting !== null) {
                    $rows[] = $this->describeRecipe('item', $item, $crafting, $user);
                }
            }
        } else {
            foreach (MzWeapon::orderBy('id')->get() as $weapon) {
                $crafting = $weapon->tags['crafting'] ?? null;
                if ($crafting !== null) {
                    $rows[] = $this->describeRecipe('weapon', $weapon, $crafting, $user);
                }
            }
            foreach (MzArmor::orderBy('id')->get() as $armor) {
                $crafting = $armor->tags['crafting'] ?? null;
                if ($crafting !== null) {
                    $rows[] = $this->describeRecipe('armor', $armor, $crafting, $user);
                }
            }
        }

        return response()->json($rows);
    }

    public function start(Request $request, string $workshop)
    {
        if (! in_array($workshop, self::WORKSHOPS, true)) {
            return response()->json(['message' => '알 수 없는 작업장입니다.'], 404);
        }

        $data = $request->validate([
            'recipe_type' => ['required', Rule::in(self::RECIPE_TYPES)],
            'recipe_id' => ['required', 'integer'],
        ]);

        $expectedWorkshop = $data['recipe_type'] === 'item' ? 'lab' : 'smithy';
        if ($expectedWorkshop !== $workshop) {
            return response()->json(['message' => '이 작업장에서 만들 수 없는 종류입니다.'], 422);
        }

        $recipe = $this->modelFor($data['recipe_type'])::find($data['recipe_id']);
        if ($recipe === null) {
            return response()->json(['message' => '존재하지 않는 항목입니다.'], 404);
        }

        $crafting = $recipe->tags['crafting'] ?? null;
        if ($crafting === null) {
            return response()->json(['message' => '조합식이 없는 항목입니다.'], 422);
        }

        $user = $request->user();

        $activeCount = CraftingJob::where('user_id', $user->id)->where('workshop', $workshop)->count();
        if ($activeCount >= self::SLOT_CAP) {
            return response()->json(['message' => '제작 슬롯이 가득 찼습니다.'], 400);
        }

        if ($user->gold < $crafting['gold_cost']) {
            return response()->json(['message' => '골드가 부족합니다.'], 400);
        }

        $materialRefs = [];
        foreach ($crafting['materials'] as $material) {
            [, $ownedModel, $foreignKey] = $this->modelsForType($material['type']);
            $catalogRow = $this->modelFor($material['type'])::where('name', $material['name'])->first();
            if ($catalogRow === null) {
                throw new \RuntimeException("조합식이 존재하지 않는 재료를 참조합니다: {$material['name']}");
            }
            $owned = $ownedModel::where('user_id', $user->id)->where($foreignKey, $catalogRow->id)->value('quantity') ?? 0;
            if ($owned < $material['count']) {
                return response()->json(['message' => "재료가 부족합니다: {$material['name']}"], 400);
            }
            $materialRefs[] = [$ownedModel, $foreignKey, $catalogRow->id, $material['count']];
        }

        $job = DB::transaction(function () use ($user, $workshop, $data, $crafting, $materialRefs) {
            $user->gold -= $crafting['gold_cost'];
            $user->save();

            foreach ($materialRefs as [$ownedModel, $foreignKey, $catalogId, $need]) {
                $owned = $ownedModel::where('user_id', $user->id)->where($foreignKey, $catalogId)->first();
                $owned->quantity -= $need;
                $owned->save();
            }

            return CraftingJob::create([
                'user_id' => $user->id,
                'workshop' => $workshop,
                'recipe_type' => $data['recipe_type'],
                'recipe_id' => $data['recipe_id'],
                'finishes_at' => now()->addSeconds($crafting['seconds']),
            ]);
        });

        return response()->json($this->describeJob($job) + ['gold' => $user->gold], 201);
    }

    public function collect(Request $request, int $id)
    {
        $job = CraftingJob::where('id', $id)->where('user_id', $request->user()->id)->first();
        if ($job === null) {
            return response()->json(['message' => '존재하지 않는 작업입니다.'], 404);
        }
        if (now()->lt($job->finishes_at)) {
            return response()->json(['message' => '아직 제작이 끝나지 않았습니다.'], 400);
        }

        [$ownedModel, $foreignKey] = match ($job->recipe_type) {
            'item' => [UserItem::class, 'mz_item_id'],
            'weapon' => [UserWeapon::class, 'mz_weapon_id'],
            'armor' => [UserArmor::class, 'mz_armor_id'],
        };

        $owned = $ownedModel::firstOrNew(['user_id' => $job->user_id, $foreignKey => $job->recipe_id]);
        $owned->quantity = ($owned->quantity ?? 0) + 1;
        $owned->save();

        $job->delete();

        return response()->json(['quantity' => $owned->quantity]);
    }

    /** @return class-string<Model> */
    private function modelFor(string $recipeType): string
    {
        return match ($recipeType) {
            'item' => MzItem::class,
            'weapon' => MzWeapon::class,
            'armor' => MzArmor::class,
        };
    }

    /**
     * 재료 종류(item/weapon/armor)별로 어느 카탈로그/보유 테이블·외래키를 볼지.
     *
     * @return array{0: class-string<Model>, 1: class-string<Model>, 2: string}
     */
    private function modelsForType(string $type): array
    {
        return match ($type) {
            'item' => [MzItem::class, UserItem::class, 'mz_item_id'],
            'weapon' => [MzWeapon::class, UserWeapon::class, 'mz_weapon_id'],
            'armor' => [MzArmor::class, UserArmor::class, 'mz_armor_id'],
        };
    }

    private function describeRecipe(string $type, Model $recipe, array $crafting, $user): array
    {
        $materials = [];
        foreach ($crafting['materials'] as $material) {
            [$catalogModel, $ownedModel, $foreignKey] = $this->modelsForType($material['type']);
            $catalogRow = $catalogModel::where('name', $material['name'])->first();
            $have = $catalogRow !== null
                ? ($ownedModel::where('user_id', $user->id)->where($foreignKey, $catalogRow->id)->value('quantity') ?? 0)
                : 0;
            $materials[] = ['type' => $material['type'], 'name' => $material['name'], 'need' => $material['count'], 'have' => $have];
        }

        return [
            'recipe_type' => $type,
            'recipe_id' => $recipe->id,
            'name' => $recipe->name,
            'seconds' => $crafting['seconds'],
            'gold_cost' => $crafting['gold_cost'],
            'materials' => $materials,
            'craftable' => $user->gold >= $crafting['gold_cost']
                && collect($materials)->every(fn (array $m) => $m['have'] >= $m['need']),
        ];
    }

    private function describeJob(CraftingJob $job): array
    {
        $recipe = $this->modelFor($job->recipe_type)::find($job->recipe_id);

        return [
            'id' => $job->id,
            'workshop' => $job->workshop,
            'recipe_type' => $job->recipe_type,
            'recipe_id' => $job->recipe_id,
            'recipe_name' => $recipe?->name,
            'finishes_at' => $job->finishes_at->toIso8601String(),
            'remaining_seconds' => max(0, $job->finishes_at->getTimestamp() - now()->getTimestamp()),
            'ready' => now()->gte($job->finishes_at),
        ];
    }
}
