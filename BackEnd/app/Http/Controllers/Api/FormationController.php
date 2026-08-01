<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormationSlot;
use App\Models\Unit;
use App\Models\UserMercenary;
use Illuminate\Http\Request;

class FormationController extends Controller
{
    private const VALID_SLOTS = [1, 2, 3, 4, 5, 6];
    private const PRESET_NUMBERS = [1, 2, 3, 4, 5];
    private const MAX_PLACED = 4;

    /** All 5 presets (each a 6-slot layout + owned-but-unplaced-in-that-preset mercenaries), plus which preset is active. */
    public function show(Request $request)
    {
        $userId = $request->user()->id;

        $ownedUnitIds = UserMercenary::where('user_id', $userId)->pluck('unit_id')->all();
        $unitsById = Unit::whereIn('id', $ownedUnitIds)->get()->keyBy('id');

        $rowsByPreset = array_fill_keys(self::PRESET_NUMBERS, []);
        foreach (FormationSlot::where('user_id', $userId)->orderBy('slot')->get() as $row) {
            $rowsByPreset[(int) $row->preset_number][] = $row;
        }

        $presets = [];
        foreach (self::PRESET_NUMBERS as $presetNumber) {
            $slots = array_fill_keys(self::VALID_SLOTS, null);
            $placedIds = [];
            foreach ($rowsByPreset[$presetNumber] as $row) {
                $unitId = (int) $row->unit_id;
                if ($unitsById->has($unitId)) {
                    $slots[(int) $row->slot] = $unitsById[$unitId];
                    $placedIds[] = $unitId;
                }
            }

            $unplaced = $unitsById->reject(fn (Unit $unit) => in_array($unit->id, $placedIds, true))->values();

            $presets[$presetNumber] = ['slots' => $slots, 'unplaced' => $unplaced];
        }

        return response()->json([
            'active_preset' => (int) $request->user()->active_formation_preset,
            'presets' => $presets,
        ]);
    }

    /** Wholesale-replaces one preset's layout. Body: { preset: 1-5, slots: { "1": unitId|null, ..., "6": ... } }. */
    public function update(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->validate([
            'preset' => ['required', 'integer'],
            'slots' => ['required', 'array'],
        ]);

        $presetNumber = (int) $data['preset'];
        if (! in_array($presetNumber, self::PRESET_NUMBERS, true)) {
            return response()->json(['message' => '올바르지 않은 프리셋 번호입니다.'], 400);
        }

        $ownedUnitIds = UserMercenary::where('user_id', $userId)->pluck('unit_id')->all();

        $seenUnitIds = [];
        $rows = [];
        foreach ($data['slots'] as $slotKey => $unitId) {
            if ($unitId === null) {
                continue;
            }

            $slotNumber = (int) $slotKey;
            if (! in_array($slotNumber, self::VALID_SLOTS, true)) {
                return response()->json(['message' => "잘못된 슬롯 번호입니다: {$slotKey}"], 400);
            }

            $unitId = (int) $unitId;
            if (! in_array($unitId, $ownedUnitIds, true)) {
                return response()->json(['message' => '보유하지 않은 용병은 배치할 수 없습니다.'], 400);
            }
            if (in_array($unitId, $seenUnitIds, true)) {
                return response()->json(['message' => '같은 용병을 두 슬롯에 배치할 수 없습니다.'], 400);
            }

            $seenUnitIds[] = $unitId;
            $rows[] = ['user_id' => $userId, 'preset_number' => $presetNumber, 'slot' => $slotNumber, 'unit_id' => $unitId];
        }

        if (count($rows) > self::MAX_PLACED) {
            return response()->json(['message' => '진형에는 최대 ' . self::MAX_PLACED . '명까지만 배치할 수 있습니다.'], 400);
        }

        FormationSlot::where('user_id', $userId)->where('preset_number', $presetNumber)->delete();
        if ($rows !== []) {
            FormationSlot::insert($rows);
        }

        return response()->noContent();
    }

    /** Swaps which formation preset is used when spawning a battle. Body: { preset: 1-5 }. */
    public function setActive(Request $request)
    {
        $data = $request->validate(['preset' => ['required', 'integer']]);
        $presetNumber = (int) $data['preset'];

        if (! in_array($presetNumber, self::PRESET_NUMBERS, true)) {
            return response()->json(['message' => '올바르지 않은 프리셋 번호입니다.'], 400);
        }

        $user = $request->user();
        $user->active_formation_preset = $presetNumber;
        $user->save();

        return response()->noContent();
    }
}
