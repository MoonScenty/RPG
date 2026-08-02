import type { BattleUnit } from '@/lib/battleApi'

// 백엔드 BattleEngine::pickReadyActor()와 동일한 ATB 시뮬레이션 - 게이지가 spd만큼
// (최소 1) 매 틱 증가하다 100 이상에 먼저 도달하는 유닛이 그 턴의 주인공. 동시
// 도달이면 게이지가 높은 쪽, 그다음 spd가 높은 쪽을 우선한다. ATB_MAX_TICKS는
// spd 데이터가 비정상적으로 낮아도(0 등) 예측 루프가 무한히 돌지 않게 막는 안전장치.
// TurnOrderStrip(우하단 턴 순서 큐)이 "앞으로 몇 턴이 누구 차례인지"를 예측하는 데 쓴다.
const ATB_READY_THRESHOLD = 100
const ATB_MAX_TICKS = 1000

/**
 * 게이지 1틱 당 실제 경과 시간(ms) - 백엔드는 순간적으로 계산하지만, 프론트는
 * 이 값으로 "체감 시간"을 만들어서 게이지가 실제로 차오르는 것처럼 보여준다
 * (사용자 요청: 진짜 ATB처럼 시간이 흘러가는 게 보여야 함). 폴링 주기를 서버가
 * 아니라 이 상수 하나로 조절하므로, 템포를 바꾸고 싶으면 여기만 고치면 된다.
 */
export const MS_PER_GAUGE_TICK = 500

/** 대기 연출이 너무 짧아 뚝뚝 끊기거나(스피드 매우 높음), 너무 길어 멈춘 것처럼 보이지 않게(스피드 매우 낮음) 하는 클램프. */
export const MIN_TURN_DELAY_MS = 350
export const MAX_TURN_DELAY_MS = 3000

interface TickResult {
  ready: BattleUnit
  gauges: Map<number, number>
  ticks: number
}

/** 한 명이 준비될 때까지(gauge>=100) 시뮬레이션 - predictNextActors와 예측 delay 계산이 공유하는 핵심 로직. */
function simulateUntilReady(living: BattleUnit[], startGauges: Map<number, number>): TickResult | null {
  const gauges = new Map(startGauges)
  let ready: BattleUnit | undefined
  let ticks = 0

  for (; ticks < ATB_MAX_TICKS && !ready; ticks++) {
    for (const u of living) {
      gauges.set(u.id, (gauges.get(u.id) ?? 0) + Math.max(1, u.spd))
    }
    ready = living
      .filter((u) => (gauges.get(u.id) ?? 0) >= ATB_READY_THRESHOLD)
      .sort((a, b) => {
        const diff = (gauges.get(b.id) ?? 0) - (gauges.get(a.id) ?? 0)
        return diff !== 0 ? diff : b.spd - a.spd
      })[0]
  }

  return ready ? { ready, gauges, ticks } : null
}

/** 살아있는 유닛 목록의 현재 atb_gauge를 시작점으로, 앞으로 행동할 순서를 count개 예측한다. */
export function predictNextActors(living: BattleUnit[], count: number): BattleUnit[] {
  let gauges = new Map(living.map((u) => [u.id, u.atb_gauge]))
  const result: BattleUnit[] = []

  for (let step = 0; step < count; step++) {
    const outcome = simulateUntilReady(living, gauges)
    if (!outcome) break

    gauges = outcome.gauges
    gauges.set(outcome.ready.id, (gauges.get(outcome.ready.id) ?? 0) - ATB_READY_THRESHOLD)
    result.push(outcome.ready)
  }

  return result
}

/**
 * 다음 행동까지 실제로 몇 ms를 기다려야 자연스러운지 예측한다(MIN/MAX_TURN_DELAY_MS로
 * 클램프). BattleScene.loop()가 advanceTurn() 호출 전에 이 시간만큼 게이지 연출을
 * 재생한 뒤 다음 턴을 요청한다.
 */
export function estimateNextTurnDelayMs(living: BattleUnit[]): number {
  const outcome = simulateUntilReady(living, new Map(living.map((u) => [u.id, u.atb_gauge])))
  if (!outcome) return MIN_TURN_DELAY_MS

  const raw = outcome.ticks * MS_PER_GAUGE_TICK
  return Math.min(MAX_TURN_DELAY_MS, Math.max(MIN_TURN_DELAY_MS, raw))
}

/**
 * progress(0~1) 시점의 각 유닛 게이지를 선형 보간한다 - BattleScene.loop()가 대기
 * 연출 중 매 프레임 이걸로 PartyHud/TurnOrderStrip을 다시 그려서 게이지가 실제로
 * 차오르는 것처럼 보이게 한다. 다음 행동자의 게이지는 정확히 progress=1에서 100에
 * 도달하도록(estimateNextTurnDelayMs와 같은 시뮬레이션 결과 기준) 스케일한다.
 */
export function interpolateGauges(living: BattleUnit[], progress: number): Map<number, number> {
  const start = new Map(living.map((u) => [u.id, u.atb_gauge]))
  const outcome = simulateUntilReady(living, start)
  if (!outcome) return start

  const clamped = Math.min(1, Math.max(0, progress))
  const result = new Map<number, number>()
  for (const u of living) {
    const target = outcome.gauges.get(u.id) ?? u.atb_gauge
    result.set(u.id, u.atb_gauge + (target - u.atb_gauge) * clamped)
  }

  return result
}
