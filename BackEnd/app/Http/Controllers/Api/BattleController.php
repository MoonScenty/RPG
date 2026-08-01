<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Battle;
use App\Models\MzAnimation;
use App\Models\MzTroop;
use App\Support\BattleEngine;
use App\Support\GambitCatalog;
use Illuminate\Http\Request;

class BattleController extends Controller
{
    public function __construct(private readonly BattleEngine $engine)
    {
    }

    /**
     * 전투 배경음(battleBgm/victoryMe/defeatMe) 트랙명 + 캐스팅(시전 대기) 턴 로그
     * 문구(GambitCatalog::castingMessage(), "캐스팅" 더미 스킬의 message1 필드로
     * 지정) - 전투 시작 시 한 번만 받아두는 정적 값들이라 같은 엔드포인트에 묶었다.
     *
     * mz_project System.json 시절엔 이 값들이 프로젝트 전역(mz_system_audio) 기본값
     * 하나였는데, RPGProject/data는 대응 파일이 없는 대신 Troops.json에 트룹별로
     * battleBgm/victoryMe/defeatMe를 갖고 있다(TypesAndSystemAudioSeeder 주석 참고) -
     * mz_system_audio는 늘 null로 시딩돼서 예전 코드 그대로면 배경음이 항상 안 남.
     * 시험전투 트룹이 현재 하나뿐이라(BattleEngine::spawnEnemyTroop()과 동일하게
     * MzTroop::first()) 그 트룹의 값을 그대로 쓴다.
     */
    public function audio()
    {
        $troop = MzTroop::first();

        return response()->json([
            'battle_bgm' => $troop?->battle_bgm['name'] ?? null,
            'victory_me' => $troop?->victory_me['name'] ?? null,
            'defeat_me' => $troop?->defeat_me['name'] ?? null,
            'casting_message' => GambitCatalog::castingMessage(),
        ]);
    }

    /**
     * mz_animations의 effect_name별 재생 메타데이터(scale/sound_timings) - units.
     * weapon_effect_name은 파일명만 가리키므로, 실제 재생 크기·효과음 타이밍은
     * 여기서 이름으로 조회한다. 유닛마다 달라지는 값이 아니라 이펙트 종류에
     * 고정된 값이라 별도 카탈로그 엔드포인트로 뺐다(battle-audio와 동일 패턴).
     */
    public function effects()
    {
        $animations = MzAnimation::whereNotNull('effect_name')->get(['effect_name', 'scale', 'sound_timings']);

        return response()->json(
            $animations->map(fn (MzAnimation $a) => [
                'name' => $a->effect_name,
                'scale' => $a->scale,
                'sound_timings' => $a->sound_timings,
            ])->values(),
        );
    }

    /**
     * 진행 중인 전투가 있는지만 확인(생성/재개 없음) - 메인 화면이 "이어서 전투"
     * 버튼을 보여줄지 판단하는 용도. 새로고침 시 /battle이 배틀 세션을 자동으로
     * 이어버리지 않고 메인으로 보내기 위해, 실제 진입(createOrResume)은 이 버튼을
     * 눌렀을 때만 일어나게 분리했다(BGM 자동재생 정책도 이걸로 해결됨 - 진짜
     * 클릭 직후에만 재생 시도).
     */
    public function active(Request $request)
    {
        $battle = Battle::where('user_id', $request->user()->id)->where('status', 'in_progress')->first();

        return response()->json(['id' => $battle?->id]);
    }

    /** 진행 중인 시험전투가 있으면 이어서 반환, 없으면 현재 진형+고정 적으로 새로 시작. */
    public function store(Request $request)
    {
        $battle = $this->engine->createOrResume($request->user());

        return response()->json($this->engine->getState($battle));
    }

    public function show(Request $request, int $id)
    {
        $battle = Battle::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json($this->engine->getState($battle));
    }

    /** 한 턴(행동자 1명)을 진행시키고 갱신된 상태를 반환. */
    public function turn(Request $request, int $id)
    {
        $battle = Battle::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json($this->engine->advanceTurn($battle));
    }
}
