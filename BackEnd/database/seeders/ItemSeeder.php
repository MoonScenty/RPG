<?php

namespace Database\Seeders;

use App\Support\MzNoteTagParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * mz_project/data/Items.json -> mz_items. id는 원본과 1:1 고정 유지. 이름이 빈
 * 슬롯이거나 "-----"로 시작하는 에디터 구분선 더미는 스킵(Skills.json 임포트와
 * 동일 컨벤션). mz_skills와 마찬가지로 effects[](네이티브 code 11=HP회복/12=MP회복/
 * 21=상태 부여/22=상태 해제)와 note 커스텀 태그(ApplySelfState/ExcludeSelf,
 * MzNoteTagParser::parseItemTags())를 tags 컬럼으로 미리 해석해둔다 -
 * BattleEngine::resolveItemUse()가 이걸 그대로 읽어서 적용한다. States.json을
 * 직접 읽어 상태 id -> 이름 맵을 자체적으로 만들어 쓰므로(MzImportSeeder의
 * mz_states DB 의존 없음) DatabaseSeeder 안에서 다른 시더보다 먼저 돌아도 안전하다.
 * 재실행해도 안전하게 매번 전체를 지우고 다시 채운다.
 */
class ItemSeeder extends Seeder
{
    private const MZ_DATA_PATH = __DIR__ . '/../../../mz_project/data';

    public function run(): void
    {
        $items = $this->readJson('Items.json');
        $stateNames = $this->stateNameMap($this->readJson('States.json'));

        DB::table('mz_items')->delete();

        $rows = [];
        foreach ($items as $item) {
            if ($item === null || $item['name'] === '' || str_starts_with($item['name'], '-----')) {
                continue;
            }
            $rows[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'item_type_id' => $item['itypeId'],
                'consumable' => $item['consumable'],
                'price' => $item['price'],
                'icon_index' => $item['iconIndex'],
                'animation_id' => $item['animationId'],
                'scope' => $item['scope'],
                'occasion' => $item['occasion'],
                'hit_type' => $item['hitType'],
                'speed' => $item['speed'],
                'success_rate' => $item['successRate'],
                'repeats' => $item['repeats'],
                'tp_gain' => $item['tpGain'],
                'damage_type' => $item['damage']['type'],
                'damage_formula' => $item['damage']['formula'],
                'variance' => $item['damage']['variance'],
                'element_id' => $item['damage']['elementId'],
                'description' => $item['description'] ?: null,
                'note' => $item['note'] ?: null,
                'effects' => json_encode($item['effects'] ?? []),
                'tags' => json_encode($this->resolveTags($item, $stateNames)),
            ];
        }
        DB::table('mz_items')->insert($rows);

        $this->command?->info('mz_items 임포트 완료 (' . count($rows) . '개).');
    }

    /** @return array<int, string> state id -> name */
    private function stateNameMap(array $states): array
    {
        $names = [];
        foreach ($states as $state) {
            if ($state === null || $state['id'] < 1 || $state['id'] > 200) {
                continue;
            }
            $names[$state['id']] = $state['name'];
        }

        return $names;
    }

    /** @param array<int, string> $stateNames */
    private function resolveTags(array $item, array $stateNames): array
    {
        $tags = MzNoteTagParser::parseItemTags($item['note'] ?? '');

        $recoverHp = ['rate' => 0.0, 'flat' => 0];
        $recoverMp = ['rate' => 0.0, 'flat' => 0];
        $addStates = [];
        $removeStates = [];

        foreach ($item['effects'] ?? [] as $effect) {
            $code = $effect['code'] ?? null;
            if ($code === 11) {
                $recoverHp = ['rate' => (float) $effect['value1'], 'flat' => (int) $effect['value2']];
            } elseif ($code === 12) {
                $recoverMp = ['rate' => (float) $effect['value1'], 'flat' => (int) $effect['value2']];
            } elseif ($code === 21 && isset($stateNames[$effect['dataId']])) {
                $addStates[] = ['state' => $stateNames[$effect['dataId']], 'chance' => $effect['value1']];
            } elseif ($code === 22 && isset($stateNames[$effect['dataId']])) {
                $removeStates[] = ['state' => $stateNames[$effect['dataId']], 'chance' => $effect['value1']];
            }
        }

        $tags['recover_hp'] = $recoverHp;
        $tags['recover_mp'] = $recoverMp;
        $tags['add_states'] = $addStates;
        $tags['remove_states'] = $removeStates;
        $tags['crafting'] = MzNoteTagParser::parseCraftingTag($item['note'] ?? '');

        return $tags;
    }

    private function readJson(string $file): array
    {
        $path = self::MZ_DATA_PATH . '/' . $file;
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("mz_project 데이터를 읽지 못했습니다: {$path}");
        }

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
