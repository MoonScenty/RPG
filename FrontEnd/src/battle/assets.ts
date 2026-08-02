import { Assets } from 'pixi.js'
import borderNew from '@/assets/battle/border-new.png'
import partyHudBack from '@/assets/battle/party-hud-back.png'
import partyHudHp from '@/assets/battle/party-hud-hp.png'
import partyHudMp from '@/assets/battle/party-hud-mp.png'
import turnOrderBar from '@/assets/battle/turn-order-bar.png'
import turnOrderFrameCurrent from '@/assets/battle/turn-order-frame-current.png'
import turnOrderFrameWait from '@/assets/battle/turn-order-frame-wait.png'
import turnOrderActor from '@/assets/battle/turn-order-actor.png'
import turnOrderEnemy from '@/assets/battle/turn-order-enemy.png'
import turnOrderActor2 from '@/assets/battle/turn-order-actor2.png'
import turnOrderEnemy2 from '@/assets/battle/turn-order-enemy2.png'
import shadow2 from '@/assets/battle/shadow2.png'

// 전투 배경은 트룹별(Troops.json의 battleback1/battleback2, BattleEngine::getState()가
// 그대로 내려줌)이라 빌드 시점에 고정할 수 없다 - img/battlebacks{1,2}/ 전체를
// public/assets/battlebacks{1,2}/로 그대로 복사해두고, 트룹 이름으로 파일명을
// 대입해서 쓴다(값이 없으면 예전 기본값과 동일하게 Grassland). back1을 화면
// 전체에 깔고 back2(위쪽만 불투명, 아래쪽 투명)를 그 위에 덮는다 - BattleScene.ts 참고.
const DEFAULT_BATTLEBACK_NAME = 'Grassland'

export function battleback1Url(name: string | null): string {
  return `/assets/battlebacks1/${name ?? DEFAULT_BATTLEBACK_NAME}.png`
}

export function battleback2Url(name: string | null): string {
  return `/assets/battlebacks2/${name ?? DEFAULT_BATTLEBACK_NAME}.png`
}

export const BORDER_URL = borderNew

// 좌하단 파티 HUD 카드(360x120, ReferenceResource/party_hud 기반 - 2026-08 재디자인) -
// back은 반투명 패널 배경에 HP/MP 라벨과 게이지 트랙이 이미 그려져 있고(SG/%PERCENT/
// STATUS는 장식 텍스트, 기능 없음 - 사용자 확인), hp/mp는 같은 캔버스 크기에 그 트랙
// 자리와 정확히 겹치는 색칠된 게이지만 그려진 오버레이다(그 외 영역은 투명). back
// 위에 hp/mp를 그대로 얹고, 채워진 비율만큼만 보이게 게이지가 그려진 실제 영역(픽셀로
// 실측한 사각형)만 마스킹해서 가로 폭을 줄인다 - PartyHud.ts 참고.
export const PARTY_HUD_BACK_URL = partyHudBack
export const PARTY_HUD_HP_URL = partyHudHp
export const PARTY_HUD_MP_URL = partyHudMp

// 좌상단 턴 순서 UI(옥토패스 스타일) - frame1은 현재 턴 캐릭터(큰 다이아몬드),
// frame2는 대기 턴 캐릭터(작은 다이아몬드), bar는 대기열 뒤 배경 바. TurnOrderStrip.ts 참고.
export const TURN_ORDER_BAR_URL = turnOrderBar
export const TURN_ORDER_FRAME_CURRENT_URL = turnOrderFrameCurrent
export const TURN_ORDER_FRAME_WAIT_URL = turnOrderFrameWait

// frame1(191x191)과 같은 캔버스 크기 - 현재 턴 아이콘에서만 frame1 위에 겹쳐
// 진영 색을 표시하는 삼각형 그라데이션(아군=보라, 적군=빨강).
export const TURN_ORDER_ACTOR_URL = turnOrderActor
export const TURN_ORDER_ENEMY_URL = turnOrderEnemy

// frame2(79x79)와 같은 캔버스 크기 - 대기열 아이콘용 진영 색 삼각형.
export const TURN_ORDER_ACTOR2_URL = turnOrderActor2
export const TURN_ORDER_ENEMY2_URL = turnOrderEnemy2

// mz_project/img/system/Shadow2.png - 아군 배틀러 발밑 그림자(MZ 사이드뷰 배틀에서
// 쓰는 표준 타원 그림자). BattleScene.ts 참고.
export const SHADOW_URL = shadow2

// mz_project 액터 얼굴 시트 - FrontEnd/src/lib/portrait.ts(DOM용)와 동일한 파일을 그대로
// public/에서 서빙 받는다(FrontEnd/public/assets/portraits/*.png, Vite 번들 대상 아님).
export const FACE_SHEET_URLS: Record<string, string> = {
  Actor1: '/assets/portraits/Actor1.png',
  Actor2: '/assets/portraits/Actor2.png',
}

export async function preloadBattleAssets(): Promise<void> {
  await Assets.load([
    BORDER_URL,
    PARTY_HUD_BACK_URL,
    PARTY_HUD_HP_URL,
    PARTY_HUD_MP_URL,
    TURN_ORDER_BAR_URL,
    TURN_ORDER_FRAME_CURRENT_URL,
    TURN_ORDER_FRAME_WAIT_URL,
    TURN_ORDER_ACTOR_URL,
    TURN_ORDER_ENEMY_URL,
    TURN_ORDER_ACTOR2_URL,
    TURN_ORDER_ENEMY2_URL,
    SHADOW_URL,
    ...Object.values(FACE_SHEET_URLS),
  ])
}
