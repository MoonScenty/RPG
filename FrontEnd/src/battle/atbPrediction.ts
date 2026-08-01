import type { BattleUnit } from '@/lib/battleApi'

// 백엔드 BattleEngine::pickReadyActor()와 동일한 ATB 시뮬레이션 - 게이지가 spd만큼
// (최소 1) 매 틱 증가하다 100 이상에 먼저 도달하는 유닛이 그 턴의 주인공. 동시
// 도달이면 게이지가 높은 쪽, 그다음 spd가 높은 쪽을 우선한다. ATB_MAX_TICKS는
// spd 데이터가 비정상적으로 낮아도(0 등) 예측 루프가 무한히 돌지 않게 막는 안전장치.
// TurnOrderStrip(좌상단 턴 순서 UI)과 PartyHud(우측 파티 카드의 "자기 턴" 강조) 둘 다
// "지금 누구 차례인지"를 같은 로직으로 예측해야 해서 공용 함수로 뺐다.
const ATB_READY_THRESHOLD = 100
const ATB_MAX_TICKS = 1000

/** 살아있는 유닛 목록의 현재 atb_gauge를 시작점으로, 앞으로 행동할 순서를 count개 예측한다. */
export function predictNextActors(living: BattleUnit[], count: number): BattleUnit[] {
  const gauges = new Map(living.map((u) => [u.id, u.atb_gauge]))
  const result: BattleUnit[] = []

  for (let step = 0; step < count; step++) {
    let ready: BattleUnit | undefined
    for (let tick = 0; tick < ATB_MAX_TICKS && !ready; tick++) {
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
    if (!ready) break

    gauges.set(ready.id, (gauges.get(ready.id) ?? 0) - ATB_READY_THRESHOLD)
    result.push(ready)
  }

  return result
}

/** 지금 살아있는 유닛들 중 바로 다음 차례로 예측되는 유닛의 id(없으면 null). */
export function predictCurrentActorId(living: BattleUnit[]): number | null {
  return predictNextActors(living, 1)[0]?.id ?? null
}
