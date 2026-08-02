import { Container, Graphics, Sprite, Texture } from 'pixi.js'
import { isUnitAlive, type BattleState } from '@/lib/battleApi'
import { TURN_HEX_ACTOR_URL, TURN_HEX_ENEMY_URL } from './assets'
import { predictNextActors } from './atbPrediction'
import { STAGE_HEIGHT, STAGE_WIDTH } from './layout'

// ReferenceResource/turn_hud(2026-08 재디자인) 기반 - 캐릭터 초상화가 있던 옥토패스
// 스타일 다이아몬드 스트립을 걷어내고, 우하단에 육각형 7개(현재 턴 포함, 백엔드
// BattleEngine::pickReadyActor()와 동일한 ATB 예측으로 다음 몇 턴이 아군/적 중
// 누구 차례인지만 단순 표시)로 교체했다(사용자 지시).
const HEX_WIDTH = 32
const HEX_HEIGHT = 25
const HEX_PITCH = 34
const HEX_COUNT = 7

// 육각형 줄 오른쪽 끝(셰브런 포함) ~ 화면 우측 끝 여백, 줄 아래쪽 ~ 화면 하단 여백 -
// ReferenceResource/turn_hud/ref.png 배치를 픽셀로 실측해서 맞춘 값.
const RIGHT_MARGIN = 30
const BOTTOM_MARGIN = 65
const CHEVRON_GAP = 12
const CHEVRON_WIDTH = 26

const ROW_TOP = STAGE_HEIGHT - BOTTOM_MARGIN - HEX_HEIGHT
const ROW_RIGHT = STAGE_WIDTH - RIGHT_MARGIN - CHEVRON_WIDTH - CHEVRON_GAP
const ROW_LEFT = ROW_RIGHT - HEX_WIDTH - (HEX_COUNT - 1) * HEX_PITCH

/** 우하단 턴 순서 큐: 육각형 7개(현재 턴 + 다음 6턴), 캐릭터 표시 없이 진영색만. */
export class TurnOrderStrip {
  readonly container = new Container()

  private readonly slots: Sprite[]

  constructor() {
    this.slots = Array.from({ length: HEX_COUNT }, (_, i) => {
      const sprite = Sprite.from(TURN_HEX_ACTOR_URL)
      sprite.position.set(ROW_LEFT + i * HEX_PITCH, ROW_TOP)
      sprite.visible = false
      this.container.addChild(sprite)
      return sprite
    })

    this.container.addChild(this.buildChevron())
  }

  update(state: BattleState): void {
    const living = state.units.filter(isUnitAlive)
    if (living.length === 0) {
      this.slots.forEach((s) => (s.visible = false))
      return
    }

    const totalCount = Math.min(HEX_COUNT, living.length)
    const queue = predictNextActors(living, totalCount)

    this.slots.forEach((sprite, i) => {
      const unit = queue[i]
      if (!unit) {
        sprite.visible = false
        return
      }
      sprite.visible = true
      sprite.texture = Texture.from(unit.side === 'ally' ? TURN_HEX_ACTOR_URL : TURN_HEX_ENEMY_URL)
    })
  }

  /**
   * 육각형 줄 끝의 ">>" 표시(대기열이 더 있다는 장식) - 전용 이미지 에셋 없이
   * 화살촉 두 개를 겹쳐서 직접 그린다. 뒤쪽 화살은 더 흐리게 해서
   * ReferenceResource/turn_hud/ref.png의 흐려지는 느낌을 흉내낸다.
   */
  private buildChevron(): Container {
    const group = new Container()
    const baseX = ROW_RIGHT + CHEVRON_GAP

    ;[0, 10].forEach((offsetX, i) => {
      const arrow = new Graphics()
      arrow
        .moveTo(0, 0)
        .lineTo(CHEVRON_WIDTH / 2, HEX_HEIGHT / 2)
        .lineTo(0, HEX_HEIGHT)
        .stroke({ width: 4, color: 0xffffff, alpha: i === 0 ? 0.9 : 0.4, cap: 'round', join: 'round' })
      arrow.position.set(baseX + offsetX, ROW_TOP)
      group.addChild(arrow)
    })

    return group
  }
}
