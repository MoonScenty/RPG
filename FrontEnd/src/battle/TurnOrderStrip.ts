import { Container, Sprite, Text, Texture } from 'pixi.js'
import { isUnitAlive, type BattleState, type BattleUnit } from '@/lib/battleApi'
import { enemyFaceUrl, TURN_HEX_ACTOR_URL, TURN_HEX_CURRENT_GLOW_URL, TURN_HEX_ENEMY_URL } from './assets'
import { predictNextActors } from './atbPrediction'
import { faceTexture } from './faces'
import { STAGE_HEIGHT, STAGE_WIDTH } from './layout'
import { FONT_FAMILY } from './theme'

// ReferenceResource/turn_hud(2026-08 재디자인) 기반 - 캐릭터 초상화가 있던 옥토패스
// 스타일 다이아몬드 스트립을 걷어내고, 우하단에 육각형 7개(현재 턴 포함, 백엔드
// BattleEngine::pickReadyActor()와 동일한 ATB 예측으로 다음 몇 턴이 아군/적 중
// 누구 차례인지만 단순 표시)로 교체했다(사용자 지시). 공유 바닥 이미지(base.png)는
// 빼고 - 각 칸은 그 자체로 완성된 육각형 그림(actor/enemy)이라 데이터 없는 칸은
// 그냥 숨긴다. 맨 오른쪽(현재 턴) 칸에만 은은한 강조 장식(turn.png)을 육각형보다
// 뒤에 원본 비율 그대로 겹쳐서 다른 칸과 구분되게 한다(사용자 지시).
//
// 이후 얼굴 그래픽 파이프라인(party hud/hud_faces, 적 얼굴 img/faces)이 갖춰져서
// 육각형 안에도 얼굴을 보여주도록 재추가했다(사용자 지시) - 육각형 자체는 그대로
// 두고, 살짝 안쪽으로 들여보낸(FACE_INSET) 자리에 얼굴을 육각형 모양 그대로
// 마스킹해서 얹는다. 그러면 원래 육각형의 테두리 부분이 자연스럽게 진영색 테두리로
// 남는다 - 별도 링 에셋을 새로 안 만들어도 됨. 얼굴이 없는 유닛(아직 얼굴을
// 안 채운 적 등)은 마스킹 없이 그냥 진영색 육각형만 보인다.
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

// 얼굴을 육각형보다 이만큼씩 안쪽으로 들여서, 육각형 원본 테두리가 진영색 테두리로
// 남게 한다(사용자 지시 - 별도 링 에셋 없이 기존 육각형 그림만으로 프레임 효과).
const FACE_INSET = 3
const FACE_WIDTH = HEX_WIDTH - FACE_INSET * 2
const FACE_HEIGHT = HEX_HEIGHT - FACE_INSET * 2

// 현재 턴 칸 밑에 붙는 라벨(사용자 지시).
const CURRENT_LABEL_FONT_SIZE = 10
const CURRENT_LABEL_GAP = 4 // 육각형 바닥 ~ 라벨 상단 간격

interface HexSlot {
  hex: Sprite
  face: Sprite
  faceMask: Sprite
}

/** 우하단 턴 순서 큐: 육각형 7개(현재 턴 + 다음 6턴), 진영색 육각형 안에 얼굴을 마스킹해서 얹는다. */
export class TurnOrderStrip {
  readonly container = new Container()

  private readonly slots: HexSlot[]
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
      const x = slotX(i)

      const hex = Sprite.from(TURN_HEX_ACTOR_URL)
      hex.position.set(x, ROW_TOP)
      hex.visible = false
      this.container.addChild(hex)

      // 육각형 원본 실루엣을 그대로 얼굴 마스크로 재사용 - 안쪽으로 들인 자리에
      // 축소해서 겹치면 바깥 테두리만 진영색 육각형이 남는다.
      const faceMask = Sprite.from(TURN_HEX_ACTOR_URL)
      faceMask.position.set(x + FACE_INSET, ROW_TOP + FACE_INSET)
      faceMask.width = FACE_WIDTH
      faceMask.height = FACE_HEIGHT
      this.container.addChild(faceMask)

      const face = new Sprite()
      face.position.set(x + FACE_INSET, ROW_TOP + FACE_INSET)
      face.width = FACE_WIDTH
      face.height = FACE_HEIGHT
      face.mask = faceMask
      face.visible = false
      this.container.addChild(face)

      return { hex, face, faceMask }
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
      this.slots.forEach(({ hex, face }) => {
        hex.visible = false
        face.visible = false
      })
      this.currentGlow.visible = false
      this.currentLabel.visible = false
      return
    }

    const totalCount = Math.min(HEX_COUNT, living.length)
    const queue = predictNextActors(living, totalCount)

    this.slots.forEach(({ hex, face }, i) => {
      const unit = queue[i]
      if (!unit) {
        hex.visible = false
        face.visible = false
        return
      }
      hex.visible = true
      hex.texture = Texture.from(unit.side === 'ally' ? TURN_HEX_ACTOR_URL : TURN_HEX_ENEMY_URL)

      const texture = this.resolveFaceTexture(unit)
      if (texture) {
        face.texture = texture
        face.visible = true
      } else {
        face.visible = false
      }
    })

    this.currentGlow.visible = queue.length > 0
    this.currentLabel.visible = queue.length > 0
  }

  /** 아군은 기존 얼굴시트(sprite, "Actor1:0"), 적은 독립 이미지 파일(enemy_face) - 둘 다 없으면 undefined(진영색 육각형만 표시). */
  private resolveFaceTexture(unit: BattleUnit): Texture | undefined {
    if (unit.side === 'ally') {
      return faceTexture(unit.sprite)
    }
    return unit.enemy_face ? Texture.from(enemyFaceUrl(unit.enemy_face)) : undefined
  }
}
