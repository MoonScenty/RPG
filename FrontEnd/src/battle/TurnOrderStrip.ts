import type { Application } from 'pixi.js'
import { Container, Graphics, Sprite, Text, Texture } from 'pixi.js'
import { isUnitAlive, type BattleState, type BattleUnit } from '@/lib/battleApi'
import { enemyFaceUrl, TURN_HEX_ACTOR_URL, TURN_HEX_CURRENT_GLOW_URL, TURN_HEX_ENEMY_URL } from './assets'
import { predictNextActors } from './atbPrediction'
import { faceTexture } from './faces'
import { STAGE_HEIGHT, STAGE_WIDTH } from './layout'
import { FONT_FAMILY } from './theme'
import { tween } from './tween'

// ReferenceResource/turn_hud(2026-08 재디자인) 기반 - 캐릭터 초상화가 있던 옥토패스
// 스타일 다이아몬드 스트립을 걷어내고, 우하단에 육각형 7개(현재 턴 포함, 백엔드
// BattleEngine::pickReadyActor()와 동일한 ATB 예측으로 다음 몇 턴이 아군/적 중
// 누구 차례인지만 단순 표시)로 교체했다(사용자 지시). 공유 바닥 이미지(base.png)는
// 빼고 - 각 칸은 그 자체로 완성된 육각형 그림(actor/enemy)이라 데이터 없는 칸은
// 그냥 숨긴다. 맨 오른쪽(현재 턴) 칸에만 은은한 강조 장식(turn.png)을 육각형보다
// 뒤에 원본 비율 그대로 겹쳐서 다른 칸과 구분되게 한다(사용자 지시).
//
// 얼굴 그래픽 파이프라인(party hud/hud_faces, 적 얼굴 img/faces)이 갖춰져서
// 육각형 안에도 얼굴을 보여주도록 재추가했다(사용자 지시) - 육각형 자체는 그대로
// 두고, 살짝 안쪽으로 들여보낸(FACE_INSET) 자리에 얼굴을 육각형 모양 그대로
// Graphics 마스크(PartyHud.ts 게이지 마스킹과 동일한 패턴)로 얹는다. 그러면
// 원래 육각형의 테두리 부분이 자연스럽게 진영색 테두리로 남는다.
//
// 큐 각 칸은 고정 슬롯 인덱스가 아니라 유닛 id로 추적한다(사용자 지시 - 턴이
// 바뀔 때 페이드+이동 애니메이션) - update()마다 새 예측 큐와 이전에 그려둔
// 유닛 집합을 diff해서: 계속 큐에 남아있지만 자리가 바뀐 유닛은 새 위치로
// 이동(move), 큐에서 빠진 유닛(방금 행동했거나 순위 밖으로 밀림)은 페이드아웃 후
// 제거, 새로 큐에 들어온 유닛은 페이드인으로 등장시킨다.
//
// predictNextActors()는 spd가 빠른 유닛이 같은 예측 창 안에서 여러 번(예:
// [43,44,41,43,44,42,43]) 등장할 수 있다 - 그냥 unit.id를 키로 쓰면 중복
// 항목이 서로 덮어써서 실제로 몇 칸 빠지고 위치도 뒤엉키는 버그가 있었다
// (실서버에서 확인 - 적 육각형이 아예 안 보이고 아군 육각형도 자리가 밀림).
// 배열에 나온 순서대로 "이 id의 n번째 등장"까지 합쳐 키로 써서 각 등장을
// 독립된 칸으로 추적한다.
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
const FACE_MASK_WIDTH = HEX_WIDTH - FACE_INSET * 2
const FACE_MASK_HEIGHT = HEX_HEIGHT - FACE_INSET * 2
// 얼굴 원본(144x144/96x96)이 정사각형이라 가로:세로를 억지로 안 늘리고 1:1
// 유지(사용자 지시) - 육각형이 세로보다 가로가 넓어서 세로 길이(FACE_MASK_HEIGHT)
// 기준으로 정사각형을 만들고 안쪽 자리에서 가로 중앙 정렬한다.
// 사용자 지시로 2px 확대 + 아래로 1px 이동, 이어서 크기만 2px 추가 확대(마스크도
// FACE_SIZE를 그대로 쓰므로 같이 커짐).
const FACE_SIZE = FACE_MASK_HEIGHT + 4
const FACE_X_OFFSET = FACE_INSET + (FACE_MASK_WIDTH - FACE_SIZE) / 2
// +1(아래) -> +0(사용자 지시로 위로 1px).
const FACE_Y_OFFSET = FACE_INSET

// turn-hex-actor.png(32x25) 알파 채널을 픽셀 단위로 실측해서 뽑은 육각형 윤곽선
// 좌표(플랫탑 육각형).
const HEX_POLYGON_POINTS = [6, 0, 25, 0, 31, 12, 25, 24, 6, 24, 0, 12]

// 현재 턴 칸 밑에 붙는 라벨(사용자 지시).
// 10 -> 11(사용자 지시로 1px 확대).
const CURRENT_LABEL_FONT_SIZE = 11
// 4 -> 5(사용자 지시로 1px 아래로).
const CURRENT_LABEL_GAP = 5 // 육각형 바닥 ~ 라벨 상단 간격

// 턴 순서 UI 전체 확대 배율(사용자 지시) - 우하단(현재 턴 칸 바닥 오른쪽) 기준으로
// 확대해서 화면 밖으로 안 밀려나게 그 점을 고정축(pivot)으로 잡는다.
const UI_SCALE = 1.5
const SCALE_ANCHOR_X = ROW_RIGHT
const SCALE_ANCHOR_Y = ROW_TOP + HEX_HEIGHT

const MOVE_DURATION_MS = 250
const FADE_DURATION_MS = 200

// 현재 턴 강조 장식(turn.png)이 깜빡거리게(사용자 지시) - 사인파로 알파를
// MIN~MAX 사이에서 부드럽게 반복시킨다(BLINK_PERIOD_MS가 한 번 깜빡이는 주기).
const BLINK_PERIOD_MS = 900
const BLINK_MIN_ALPHA = 0.35
const BLINK_MAX_ALPHA = 1

interface SlotVisual {
  hex: Sprite
  face: Sprite
  faceMask: Graphics
  x: number
}

/** 우하단 턴 순서 큐: 육각형 7개(현재 턴 + 다음 6턴), 진영색 육각형 안에 얼굴을 마스킹해서 얹고, 순서가 바뀌면 페이드+이동으로 전환한다. */
export class TurnOrderStrip {
  readonly container = new Container()

  private readonly app: Application
  private readonly visuals = new Map<string, SlotVisual>()
  private readonly currentGlow: Sprite
  private readonly currentLabel: Text

  constructor(app: Application) {
    this.app = app

    // 우하단 기준 확대(사용자 지시) - pivot과 position을 같은 점으로 맞춰서
    // scale=1일 때는 원래 위치 그대로, scale이 커지면 이 점을 축으로 자라난다.
    this.container.pivot.set(SCALE_ANCHOR_X, SCALE_ANCHOR_Y)
    this.container.position.set(SCALE_ANCHOR_X, SCALE_ANCHOR_Y)
    this.container.scale.set(UI_SCALE)

    // 현재 턴 칸(맨 오른쪽, slotX(0)) 중심에 원본 비율(116x31) 그대로 겹친다 -
    // 가운데가 비어있고 양쪽 끝에만 흐려지는 셰브런 무늬가 있는 모양이라 육각형
    // 자체를 안 가린다. 육각형보다 먼저 addChild해서 셰브런이 뒤로 가게 한다(사용자 지시).
    this.currentGlow = Sprite.from(TURN_HEX_CURRENT_GLOW_URL)
    this.currentGlow.anchor.set(0.5)
    this.currentGlow.position.set(slotX(0) + HEX_WIDTH / 2, ROW_TOP + HEX_HEIGHT / 2)
    this.currentGlow.visible = false
    this.container.addChild(this.currentGlow)

    // 사용자 지시로 깜빡이는 연출 추가 - 배틀 내내 도는 상시 애니메이션이라
    // tween()(1회성) 대신 ticker에 직접 건다.
    let blinkElapsedMs = 0
    this.app.ticker.add(() => {
      blinkElapsedMs += this.app.ticker.deltaMS
      const phase = (blinkElapsedMs % BLINK_PERIOD_MS) / BLINK_PERIOD_MS
      const wave = 0.5 - 0.5 * Math.cos(phase * Math.PI * 2)
      this.currentGlow.alpha = BLINK_MIN_ALPHA + (BLINK_MAX_ALPHA - BLINK_MIN_ALPHA) * wave
    })

    // 현재 턴 칸 바로 밑에 뜨는 라벨(사용자 지시). slotX(0)은 큐 순서와 무관하게
    // 항상 고정된 자리라 라벨 위치는 이동 애니메이션이 필요 없다.
    this.currentLabel = new Text({
      text: 'NEXT ACTION',
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
      for (const key of [...this.visuals.keys()]) {
        this.removeVisual(key)
      }
      this.currentGlow.visible = false
      this.currentLabel.visible = false
      return
    }

    const totalCount = Math.min(HEX_COUNT, living.length)
    const queue = predictNextActors(living, totalCount)

    // 같은 유닛이 큐에 여러 번 나올 수 있어(빠른 유닛이 예측 창 안에서 여러 번
    // 행동) "이 id의 n번째 등장"까지 합친 키로 각 등장을 독립된 칸으로 다룬다.
    const occurrenceCounts = new Map<number, number>()
    const keyedQueue = queue.map((unit) => {
      const occurrence = occurrenceCounts.get(unit.id) ?? 0
      occurrenceCounts.set(unit.id, occurrence + 1)
      return { unit, key: `${unit.id}:${occurrence}` }
    })
    const newIndexByKey = new Map(keyedQueue.map((entry, i) => [entry.key, i]))

    // 큐에서 빠진 유닛(방금 행동했거나 순위 밖으로 밀림) - 페이드아웃 후 제거.
    for (const key of [...this.visuals.keys()]) {
      if (!newIndexByKey.has(key)) {
        this.removeVisual(key)
      }
    }

    keyedQueue.forEach(({ unit, key }, index) => {
      const targetX = slotX(index)
      const existing = this.visuals.get(key)

      if (!existing) {
        this.addVisual(key, unit, targetX)
        return
      }

      existing.hex.texture = Texture.from(unit.side === 'ally' ? TURN_HEX_ACTOR_URL : TURN_HEX_ENEMY_URL)
      const texture = this.resolveFaceTexture(unit)
      existing.face.texture = texture ?? existing.face.texture
      existing.face.visible = texture !== undefined

      if (Math.abs(existing.x - targetX) > 0.5) {
        this.moveVisual(existing, targetX)
      }
    })

    this.currentGlow.visible = queue.length > 0
    this.currentLabel.visible = queue.length > 0
  }

  /** 새로 큐에 들어온 유닛(등장) - 제자리에서 페이드인으로 등장(사용자 지시 - 자연스러운 애니메이션). */
  private addVisual(key: string, unit: BattleUnit, x: number): void {
    const hex = Sprite.from(unit.side === 'ally' ? TURN_HEX_ACTOR_URL : TURN_HEX_ENEMY_URL)
    hex.position.set(x, ROW_TOP)
    this.container.addChild(hex)

    const faceMask = new Graphics().poly(HEX_POLYGON_POINTS).fill(0xffffff)
    faceMask.position.set(x + FACE_X_OFFSET, ROW_TOP + FACE_Y_OFFSET)
    faceMask.scale.set(FACE_SIZE / HEX_WIDTH, FACE_SIZE / HEX_HEIGHT)
    this.container.addChild(faceMask)

    const face = new Sprite()
    face.position.set(x + FACE_X_OFFSET, ROW_TOP + FACE_Y_OFFSET)
    face.width = FACE_SIZE
    face.height = FACE_SIZE
    face.mask = faceMask
    const texture = this.resolveFaceTexture(unit)
    if (texture) face.texture = texture
    face.visible = texture !== undefined
    this.container.addChild(face)

    const visual: SlotVisual = { hex, face, faceMask, x }
    this.visuals.set(key, visual)

    hex.alpha = 0
    face.alpha = face.visible ? 0 : 1
    void tween(this.app, FADE_DURATION_MS, (progress) => {
      hex.alpha = progress
      if (face.visible) face.alpha = progress
    })
  }

  /** 계속 큐에 남아있지만 자리가 바뀐 유닛 - 이전 자리에서 새 자리로 이동(사용자 지시). */
  private moveVisual(visual: SlotVisual, targetX: number): void {
    // 직전 이동 애니메이션이 아직 끝나기 전에 또 자리가 바뀌면(gauge-fill-delay
    // 프레임마다 update()가 다시 도니 흔함) 저장된 목표값이 아니라 실제 렌더링
    // 중인 현재 위치에서 이어서 시작해야 순간이동처럼 튀지 않는다.
    const startX = visual.hex.position.x
    const deltaX = targetX - startX
    visual.x = targetX
    void tween(this.app, MOVE_DURATION_MS, (progress) => {
      const x = startX + deltaX * progress
      visual.hex.position.x = x
      visual.faceMask.position.x = x + FACE_X_OFFSET
      visual.face.position.x = x + FACE_X_OFFSET
    })
  }

  /** 큐에서 빠진 등장(유닛+n번째) - 페이드아웃 후 파괴(사용자 지시). */
  private removeVisual(key: string): void {
    const visual = this.visuals.get(key)
    if (!visual) return
    this.visuals.delete(key)

    // 페이드인이 채 안 끝난 상태에서 바로 빠질 수도 있으니(위 moveVisual과 같은
    // 이유) 지금 실제 알파에서 이어서 줄인다.
    const startAlpha = visual.hex.alpha
    void tween(this.app, FADE_DURATION_MS, (progress) => {
      const alpha = startAlpha * (1 - progress)
      visual.hex.alpha = alpha
      visual.face.alpha = alpha
    }).then(() => {
      visual.hex.destroy()
      visual.face.destroy()
      visual.faceMask.destroy()
    })
  }

  /** 아군은 기존 얼굴시트(sprite, "Actor1:0"), 적은 독립 이미지 파일(enemy_face) - 둘 다 없으면 undefined(진영색 육각형만 표시). */
  private resolveFaceTexture(unit: BattleUnit): Texture | undefined {
    if (unit.side === 'ally') {
      return faceTexture(unit.sprite)
    }
    return unit.enemy_face ? Texture.from(enemyFaceUrl(unit.enemy_face)) : undefined
  }
}
