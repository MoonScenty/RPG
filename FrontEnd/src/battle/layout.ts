import type { Side } from '@/lib/battleApi'

export const STAGE_WIDTH = 1280
export const STAGE_HEIGHT = 720

// 세로는 아군 정보 패널이 하단에서 우측으로 옮겨가면서 화면 아래쪽이 비었으므로,
// 맨 윗줄(전열 첫 자리)은 그대로 두고 아래 두 줄만 더 내려서 줄 간격을 벌린다
// (세로는 좌우 대칭과 무관해서 그대로 유지).
const GROUP_OFFSET_Y = 30
const ROW_Y = [300 + GROUP_OFFSET_Y, 470 + GROUP_OFFSET_Y, 640 + GROUP_OFFSET_Y] as const

// 아군(화면 오른쪽)은 왼쪽으로, 적군(화면 왼쪽)은 오른쪽으로(사용자 실측 지시,
// 누적 20+20px) - 서로를 향해 다가가는 방향이라 두 진영 사이 간격이 그만큼 좁혀진다.
const ALLY_SIDE_OFFSET_X = -40
const ENEMY_SIDE_OFFSET_X = 40

// slot 1-3 = 전열(상대 진영과 가까움), slot 4-6 = 후열. 좌우로 화면 전체를 밀던
// GROUP_OFFSET_X(예전엔 -80)를 여기서 뺐다 - 그 값이 있으면 화면 중앙(640) 기준
// 좌우 대칭이 깨져서(사용자 지시로 확인) 아래 LAYOUT_SCALE 적용 후에도 적/아군이
// 중앙에서 같은 거리에 있지 않게 된다. 430/220(적)과 850/1060(아군)은 원래부터
// 640을 기준으로 대칭인 값이라, SIDE_OFFSET(좌우 대칭 이동)만 더하면 대칭이 유지된다.
const ENEMY_COLUMN_X = { front: 430 + ENEMY_SIDE_OFFSET_X, back: 220 + ENEMY_SIDE_OFFSET_X }
const ALLY_COLUMN_X = { front: 850 + ALLY_SIDE_OFFSET_X, back: 1060 + ALLY_SIDE_OFFSET_X }

// 배틀러 스프라이트를 80%로 줄이면서(BattleScene.ts BATTLER_SCALE/
// DRAGONBONES_TARGET_HEIGHT 참고) 배치 간격도 화면 중앙(STAGE_WIDTH/2)을
// 기준으로 같은 비율로 좁힌다(사용자 지시) - 위 컬럼 값들이 이미 640 기준
// 대칭이라 이 스케일을 적용해도 대칭이 그대로 유지된다.
const LAYOUT_SCALE = 0.8

export function slotPosition(side: Side, slot: number): { x: number; y: number } {
  const isFront = slot <= 3
  const row = isFront ? slot - 1 : slot - 4
  const columns = side === 'enemy' ? ENEMY_COLUMN_X : ALLY_COLUMN_X
  const x = isFront ? columns.front : columns.back
  const y = ROW_Y[row] ?? ROW_Y[0]

  return {
    x: STAGE_WIDTH / 2 + (x - STAGE_WIDTH / 2) * LAYOUT_SCALE,
    y: STAGE_HEIGHT / 2 + (y - STAGE_HEIGHT / 2) * LAYOUT_SCALE,
  }
}
