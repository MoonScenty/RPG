import { Assets } from 'pixi.js'
import battleback1 from '@/assets/battle/battleback1-grassland.png'
import battleback2 from '@/assets/battle/battleback2-grassland.png'
import borderNew from '@/assets/battle/border-new.png'
import partyHudBack from '@/assets/battle/party-hud-back.png'
import partyHudHp from '@/assets/battle/party-hud-hp.png'
import partyHudMp from '@/assets/battle/party-hud-mp.png'
import partyHudDot from '@/assets/battle/party-hud-dot.png'
import turnOrderBar from '@/assets/battle/turn-order-bar.png'
import turnOrderFrameCurrent from '@/assets/battle/turn-order-frame-current.png'
import turnOrderFrameWait from '@/assets/battle/turn-order-frame-wait.png'
import turnOrderActor from '@/assets/battle/turn-order-actor.png'
import turnOrderEnemy from '@/assets/battle/turn-order-enemy.png'
import turnOrderActor2 from '@/assets/battle/turn-order-actor2.png'
import turnOrderEnemy2 from '@/assets/battle/turn-order-enemy2.png'
import shadow2 from '@/assets/battle/shadow2.png'

// mz_project System.json의 기본 전투 배경(battleback1Name/battleback2Name, 에디터
// "적 군단" 탭 일반 설정의 "전투 배경 변경"이 실제로 저장되는 곳 - 트룹별이 아니라
// 프로젝트 전역 기본값 1개) - img/battlebacks{1,2}/에서 그대로 가져옴(현재 값 둘 다
// "Grassland", mz_project에서 바뀌면 이 파일들도 수동으로 맞춰줘야 함). back1을
// 화면 전체에 깔고 back2(위쪽만 불투명, 아래쪽 투명)를 그 위에 덮는다 - BattleScene.ts 참고.
export const BACKGROUND1_URL = battleback1
export const BACKGROUND2_URL = battleback2
export const BORDER_URL = borderNew

// 우측 파티 HUD 카드(220x120) - back은 반투명 패널 배경에 HP/MP 게이지 트랙(짙은
// 회색 선)+TP 점 5개(빈 상태, 흰 점)까지 미리 그려져 있고, hp/mp는 같은 캔버스
// 크기에 그 트랙 자리와 정확히 겹치는 색칠된 게이지만 그려진 오버레이다(그 외
// 영역은 투명). 그래서 back 위에 hp/mp를 그대로 얹기만 하면 되고, 채워진 비율만큼만
// 보이게 하려면 게이지가 그려진 실제 영역(픽셀로 실측한 사각형)만 마스킹해서 가로
// 폭을 줄인다 - PartyHud.ts 참고. dot은 TP 20당 1칸씩 back의 빈 점 자리 위에
// 덧그리는 "채워진" 점 아이콘(20x20, 단일 색상).
export const PARTY_HUD_BACK_URL = partyHudBack
export const PARTY_HUD_HP_URL = partyHudHp
export const PARTY_HUD_MP_URL = partyHudMp
export const PARTY_HUD_DOT_URL = partyHudDot

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

// mz_project 액터 얼굴 시트 - frontend/src/lib/portrait.ts(DOM용)와 동일한 파일을 그대로
// public/에서 서빙 받는다(frontend/public/assets/portraits/*.png, Vite 번들 대상 아님).
export const FACE_SHEET_URLS: Record<string, string> = {
  Actor1: '/assets/portraits/Actor1.png',
  Actor2: '/assets/portraits/Actor2.png',
}

// mz_project/img/system/IconSet.png(32px 그리드, 16열) - frontend/src/lib/mzIcon.ts(DOM용
// CSS 배경 크롭)와 동일한 파일을 public/에서 그대로 서빙 받는다. 파티 HUD 카드의 직업
// 아이콘(<PartyHudIcon> 노트태그)을 PixiJS 텍스처 프레임으로 크롭할 때 쓴다 - PartyHud.ts 참고.
export const ICON_SET_URL = '/assets/icons/IconSet.png'

export async function preloadBattleAssets(): Promise<void> {
  await Assets.load([
    BACKGROUND1_URL,
    BACKGROUND2_URL,
    BORDER_URL,
    PARTY_HUD_BACK_URL,
    PARTY_HUD_HP_URL,
    PARTY_HUD_MP_URL,
    PARTY_HUD_DOT_URL,
    TURN_ORDER_BAR_URL,
    TURN_ORDER_FRAME_CURRENT_URL,
    TURN_ORDER_FRAME_WAIT_URL,
    TURN_ORDER_ACTOR_URL,
    TURN_ORDER_ENEMY_URL,
    TURN_ORDER_ACTOR2_URL,
    TURN_ORDER_ENEMY2_URL,
    SHADOW_URL,
    ICON_SET_URL,
    ...Object.values(FACE_SHEET_URLS),
  ])
}
