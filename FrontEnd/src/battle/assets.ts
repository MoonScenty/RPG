import { Assets } from 'pixi.js'
import borderNew from '@/assets/battle/border-new.png'
import partyHudBack from '@/assets/battle/party-hud-back.png'
import partyHudHp from '@/assets/battle/party-hud-hp.png'
import partyHudMp from '@/assets/battle/party-hud-mp.png'
import turnHexActor from '@/assets/battle/turn-hex-actor.png'
import turnHexEnemy from '@/assets/battle/turn-hex-enemy.png'
import turnHexCurrentGlow from '@/assets/battle/turn-hex-current-glow.png'
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

// 우하단 턴 순서 큐(ReferenceResource/turn_hud 기반 - 2026-08 재디자인, 캐릭터
// 초상화 없이 육각형 7개로만 단순 표시) - 칸마다 그 차례가 아군/적 중 누구
// 턴인지에 따라 이 두 육각형(32x25, 배경 없이 완성된 그림) 중 하나를 그대로
// 쓴다. TurnOrderStrip.ts 참고.
export const TURN_HEX_ACTOR_URL = turnHexActor
export const TURN_HEX_ENEMY_URL = turnHexEnemy

// 현재 턴 칸(맨 앞) 강조 장식(116x31) - 가운데는 완전히 비어있고 양쪽 끝에만
// 흐려지는 셰브런 무늬가 있는 모양이라 줄 전체 바닥으로는 안 맞고, 현재 턴
// 육각형 하나 위에 원본 비율 그대로 겹쳐서 은은한 강조 효과만 낸다(사용자 지시).
export const TURN_HEX_CURRENT_GLOW_URL = turnHexCurrentGlow

// mz_project/img/system/Shadow2.png - 아군 배틀러 발밑 그림자(MZ 사이드뷰 배틀에서
// 쓰는 표준 타원 그림자). BattleScene.ts 참고.
export const SHADOW_URL = shadow2

// mz_project 액터 얼굴 시트 - FrontEnd/src/lib/portrait.ts(DOM용)와 동일한 파일을 그대로
// public/에서 서빙 받는다(FrontEnd/public/assets/portraits/*.png, Vite 번들 대상 아님).
export const FACE_SHEET_URLS: Record<string, string> = {
  Actor1: '/assets/portraits/Actor1.png',
  Actor2: '/assets/portraits/Actor2.png',
}

// RPGProject/img/hud_faces 기반 - 파티 HUD 좌측 얼굴 전용 시트(FACE_SHEET_URLS와
// 별개 에셋, 지금은 img/faces와 같은 그림이지만 나중에 다른 그림으로 바뀔 수 있음).
// FrontEnd/public/assets/portraits와 동일한 방식(4x2, 144x144 셀)으로
// FrontEnd/public/assets/hud_faces/{Actor1,Actor2}.png를 미리 합쳐뒀다.
export const HUD_FACE_SHEET_URLS: Record<string, string> = {
  Actor1: '/assets/hud_faces/Actor1.png',
  Actor2: '/assets/hud_faces/Actor2.png',
}

// 적 얼굴(units.enemy_face) - 액터 얼굴시트와 달리 적은 한 마리당 독립된 이미지
// 파일 한 장이라(8칸 시트 아님) 미리 합칠 필요 없이 RPGProject/img/faces의
// 파일을 FrontEnd/public/assets/enemy_faces/에 그대로 복사해뒀다. 어떤 적이
// 새로 추가될지 미리 알 수 없어 battleback1Url/battleback2Url처럼 이름으로
// 즉석에서 URL만 만들고(preloadBattleAssets에 등록 안 함) 필요할 때
// Texture.from()으로 그때그때 로드한다 - TurnOrderStrip.ts 참고.
export function enemyFaceUrl(name: string): string {
  // "Forest Goblin Axe.png"처럼 파일명에 공백이 들어있는 경우가 많아 인코딩 없이
  // 그대로 URL에 넣으면 요청이 깨질 수 있다.
  return `/assets/enemy_faces/${encodeURIComponent(name)}.png`
}

export async function preloadBattleAssets(): Promise<void> {
  await Assets.load([
    BORDER_URL,
    PARTY_HUD_BACK_URL,
    PARTY_HUD_HP_URL,
    PARTY_HUD_MP_URL,
    TURN_HEX_ACTOR_URL,
    TURN_HEX_ENEMY_URL,
    TURN_HEX_CURRENT_GLOW_URL,
    SHADOW_URL,
    ...Object.values(FACE_SHEET_URLS),
    ...Object.values(HUD_FACE_SHEET_URLS),
  ])
}
