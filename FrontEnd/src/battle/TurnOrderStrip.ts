import { Container, Sprite, Text, Texture } from 'pixi.js'
import { isUnitAlive, type BattleState } from '@/lib/battleApi'
import { TURN_HEX_ACTOR_URL, TURN_HEX_CURRENT_GLOW_URL, TURN_HEX_ENEMY_URL } from './assets'
import { predictNextActors } from './atbPrediction'
import { STAGE_HEIGHT, STAGE_WIDTH } from './layout'
import { FONT_FAMILY } from './theme'

// ReferenceResource/turn_hud(2026-08 재디자인) 기반 - 캐릭터 초상화가 있던 옥토패스
// 스타일 다이아몬드 스트립을 걷어내고, 우하단에 육각형 7개(현재 턴 포함, 백엔드
// BattleEngine::pickReadyActor()와 동일한 ATB 예측으로 다음 몇 턴이 아군/적 중
// 누구 차례인지만 단순 표시)로 교체했다(사용자 지시). 공유 바닥 이미지(base.png)는
// 빼고 - 각 칸은 그 자체로 완성된 육각형 그림(actor/enemy)이라 데이터 없는 칸은
// 그냥 숨긴다. 맨 오른쪽(현재 턴) 칸에만 은은한 강조 장식(turn.png)을 육각형보다
// 뒤에 원본 비율 그대로 겹쳐서 다른 칸과 구분되게 한다(사용자 지시).
const HEX_WIDTH = 32
const HEX_HEIGHT = 25
const HEX_PITCH = 34
const HEX_COUNT = 7

// 육각형 줄 오른쪽 끝 ~ 화면 우측 끝 여백, 줄 아래쪽 ~ 화면 하단 여백 -
// ReferenceResource/turn_hud/ref.png 배치를 픽셀로 실측해서 맞춘 값.
const RIGHT_MARGIN = 50
const BOTTOM_MARGIN = 50

const ROW_TOP = STAGE_HEIGHT - BOTTOM_MARGIN - HEX_HEIGHT
const ROW_RIGHT = STAGE_WIDTH - RIGHT_MARGIN
const ROW_LEFT = ROW_RIGHT - HEX_WIDTH - (HEX_COUNT - 1) * HEX_PITCH

/** queue[0](현재 턴)이 맨 오른쪽에 오도록 순서를 뒤집는다(사용자 지시). */
function slotX(queueIndex: number): number {
  return ROW_LEFT + (HEX_COUNT - 1 - queueIndex) * HEX_PITCH
}

// 현재 턴 칸 밑에 붙는 라벨(사용자 지시).
const CURRENT_LABEL_FONT_SIZE = 10
const CURRENT_LABEL_GAP = 4 // 육각형 바닥 ~ 라벨 상단 간격

/** 우하단 턴 순서 큐: 육각형 7개(현재 턴 + 다음 6턴), 캐릭터 표시 없이 진영색만. */
export class TurnOrderStrip {
  readonly container = new Container()

  private readonly slots: Sprite[]
  private readonly currentGlow: Sprite
  private readonly currentLabel: Text

  constructor() {
    // 현재 턴 칸(맨 오른쪽, slotX(0)) 중심에 원본 비율(116x31) 그대로 겹친다 -
    // 가운데가 비어있고 양쪽 끝에만 흐려지는 셰브런 무늬가 있는 모양이라 육각형
    // 자체를 안 가린다. 육각형보다 먼저 addChild해서 셰브런이 뒤로 가게 한다(사용자 지시).
    this.currentGlow = Sprite.from(TURN_HEX_CURRENT_GLOW_URL)
    this.currentGlow.anchor.set(0.5)
    this.currentGlow.position.set(slotX(0) + HEX_WIDTH / 2, ROW_TOP + HEX_HEIGHT / 2)
    this.currentGlow.visible = false
    this.container.addChild(this.currentGlow)

    this.slots = Array.from({ length: HEX_COUNT }, (_, i) => {
      const sprite = Sprite.from(TURN_HEX_ACTOR_URL)
      sprite.position.set(slotX(i), ROW_TOP)
      sprite.visible = false
      this.container.addChild(sprite)
      return sprite
    })

    // 현재 턴 칸 바로 밑에 뜨는 라벨(사용자 지시).
    this.currentLabel = new Text({
      text: 'CURRENT TURN',
      style: {
        fill: 0xffffff,
        fontSize: CURRENT_LABEL_FONT_SIZE,
        fontFamily: FONT_FAMILY,
        stroke: { color: 0x000000, width: 2, alpha: 0.6 },
      },
    })
    this.currentLabel.anchor.set(0.5, 0)
    this.currentLabel.position.set(slotX(0) + HEX_WIDTH / 2, ROW_TOP + HEX_HEIGHT + CURRENT_LABEL_GAP)
    this.currentLabel.visible = false
    this.container.addChild(this.currentLabel)
  }

  update(state: BattleState): void {
    const living = state.units.filter(isUnitAlive)
    if (living.length === 0) {
      this.slots.forEach((s) => (s.visible = false))
      this.currentGlow.visible = false
      this.currentLabel.visible = false
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

    this.currentGlow.visible = queue.length > 0
    this.currentLabel.visible = queue.length > 0
  }
}
