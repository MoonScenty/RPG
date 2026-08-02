import type { Side } from '@/lib/battleApi'

export const STAGE_WIDTH = 1280
export const STAGE_HEIGHT = 720

// 배틀러 전체(적/아군 모두)를 화면 왼쪽 하단쪽으로 이동(사용자 요청, X 누적
// -60-20px) - 아래 두 값만 조절하면 다시 옮길 수 있다.
const GROUP_OFFSET_X = -80
const GROUP_OFFSET_Y = 30

// 아군 정보 패널이 하단에서 우측으로 옮겨가면서 화면 아래쪽이 비었으므로,
// 맨 윗줄(전열 첫 자리)은 그대로 두고 아래 두 줄만 더 내려서 줄 간격을 벌린다.
const ROW_Y = [300 + GROUP_OFFSET_Y, 470 + GROUP_OFFSET_Y, 640 + GROUP_OFFSET_Y] as const

// 아군(화면 오른쪽)은 왼쪽으로, 적군(화면 왼쪽)은 오른쪽으로(사용자 실측 지시,
// 누적 20+20px) - 서로를 향해 다가가는 방향이라 두 진영 사이 간격이 그만큼 좁혀진다.
const ALLY_SIDE_OFFSET_X = -40
const ENEMY_SIDE_OFFSET_X = 40

// slot 1-3 = 전열(상대 진영과 가까움), slot 4-6 = 후열.
const ENEMY_COLUMN_X = { front: 430 + GROUP_OFFSET_X + ENEMY_SIDE_OFFSET_X, back: 220 + GROUP_OFFSET_X + ENEMY_SIDE_OFFSET_X }
const ALLY_COLUMN_X = { front: 850 + GROUP_OFFSET_X + ALLY_SIDE_OFFSET_X, back: 1060 + GROUP_OFFSET_X + ALLY_SIDE_OFFSET_X }

// 배틀러 스프라이트를 80%로 줄이면서(BattleScene.ts BATTLER_SCALE/
// DRAGONBONES_TARGET_HEIGHT 참고) 배치 간격도 화면 중앙을 기준으로 같은 비율로
// 좁혀달라는 지시(사용자) - 위 상수들은 그대로 두고 최종 좌표에만 화면 중앙
// 기준 스케일을 적용해서, 그동안 누적된 개별 보정값(GROUP_OFFSET 등)의 의미를
// 안 흐트러뜨린다.
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
