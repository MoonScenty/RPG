import { type Application, Container, Sprite, Texture } from 'pixi.js'
import { isUnitAlive, type BattleState, type BattleUnit } from '@/lib/battleApi'
import {
  TURN_ORDER_ACTOR2_URL,
  TURN_ORDER_ACTOR_URL,
  TURN_ORDER_BAR_URL,
  TURN_ORDER_ENEMY2_URL,
  TURN_ORDER_ENEMY_URL,
  TURN_ORDER_FRAME_CURRENT_URL,
  TURN_ORDER_FRAME_WAIT_URL,
} from './assets'
import { characterKeyForSprite, getCharacterFrames, getStaticBattlerTexture } from './characters'
import { predictNextActors } from './atbPrediction'
import { easeOutQuad, tween } from './tween'

const STRIP_MARGIN = 16

// frame1/frame2/bar 전부 원본 텍스처 크기의 0.6배로 통일해서 그린다(사용자 지시).
const FRAME_SCALE = 0.6
const FRAME1_NATIVE_SIZE = 191 // turn_order_frame1.png
const FRAME2_NATIVE_SIZE = 79 // turn_order_frame2.png

const CURRENT_SIZE = FRAME1_NATIVE_SIZE * FRAME_SCALE
const QUEUE_SIZE = FRAME2_NATIVE_SIZE * FRAME_SCALE
const QUEUE_GAP = 0 // frame2끼리 틈 없이 붙임(사용자 지시)
const QUEUE_COUNT = 6

const BAR_GAP = 0 // 현재 턴 프레임(frame1) ~ 대기열 바, 틈 없이 붙임(사용자 지시)
const BAR_PADDING_X = 0 // 대기열 바 왼쪽 끝 ~ 첫 frame2, 틈 없이 붙임(사용자 지시)

// 턴이 넘어갈 때 재생하는 전환 애니메이션 길이(사용자 요청) - 이전 턴 아이콘은
// 페이드아웃, 대기열은 한 칸씩 왼쪽으로, 새로 현재 턴이 된 아이콘은 frame2 크기에서
// frame1 크기로 부드럽게 커진다. 셋 다 같은 지속시간으로 동시에 재생해서 하나의
// 자연스러운 전환처럼 보이게 한다.
const TRANSITION_DURATION_MS = 350

type IconRole = 'current' | 'queue'

interface IconHandle {
  container: Container
  frame: Sprite
  accent: Sprite
  character?: Sprite
  role: IconRole
  size: number
}

interface TargetSlot {
  /**
   * 아이콘 재사용 키 - 보통 유닛 id 그대로지만, 스피드 차이가 커서 같은 유닛이
   * 예측 구간 안에 두 번 이상 나오는 경우(현재 턴+대기열에 동시 등장 등) 두 번째
   * 이후 등장은 별도 키를 써서 서로 다른 아이콘으로 다룬다 - 안 그러면 같은
   * Map 키를 두 target이 덮어써서 현재 턴 아이콘이 대기열 위치/크기로 밀려버린다.
   */
  key: string
  unit: BattleUnit
  role: IconRole
  x: number
  y: number
  size: number
  frameUrl: string
  accentAllyUrl: string
  accentEnemyUrl: string
}

/**
 * 좌상단 CURRENT/다음 턴 순서 미리보기 - 옥토패스 트래블러 스타일(reference_resource/design/ref6.png
 * 참고). 백엔드 BattleEngine::pickReadyActor()와 동일한 ATB 틱 시뮬레이션(게이지가
 * spd만큼 매 틱 증가하다 100 도달 시 행동, 동시 도달이면 게이지->spd 순 우선순위)을
 * 프론트에서 그대로 재현해서, 다음 몇 턴이 누구 차례인지 미리 보여준다. 실제 진행
 * 중 GaugeBoost 사용이나 사망/부활은 미리 알 수 없어 100% 정확한 예측은 아니지만
 * (그 시점 state의 스냅샷 기준일 뿐), 매 턴 최신 state로 다시 계산되므로 어긋나도
 * 바로 다음 갱신에서 보정된다.
 *
 * update()는 매번 통째로 다시 그리지 않고, 유닛 id 기준으로 이전에 그려둔 아이콘을
 * 재사용/애니메이션한다 - 그래야 "현재 턴 아이콘이 사라지고 대기열이 한 칸씩 당겨오며
 * 새 현재 턴 아이콘이 커지는" 전환 연출이 가능하다(사용자 요청, buildIcon/applyTargets 참고).
 */
export class TurnOrderStrip {
  readonly container = new Container()

  private readonly app: Application
  private readonly bar: Sprite
  private readonly icons = new Map<string, IconHandle>()

  constructor(app: Application) {
    this.app = app

    this.bar = Sprite.from(TURN_ORDER_BAR_URL)
    this.bar.scale.set(FRAME_SCALE)
    const barX = STRIP_MARGIN + CURRENT_SIZE + BAR_GAP
    this.bar.position.set(barX, STRIP_MARGIN + (CURRENT_SIZE - this.bar.height) / 2)
    this.bar.visible = false
    this.container.addChild(this.bar)
  }

  update(state: BattleState): void {
    const living = state.units.filter(isUnitAlive)
    if (living.length === 0) {
      this.clearAll()
      return
    }

    const totalCount = Math.min(1 + QUEUE_COUNT, living.length)
    const rotated = predictNextActors(living, totalCount)

    const current = rotated[0]
    if (!current) {
      this.clearAll()
      return
    }

    // 스피드 차가 크면 같은 유닛이 예측 구간 안에 두 번 이상 등장할 수 있어(빠른
    // 유닛이 느린 유닛들을 기다리는 동안 먼저 한 번 더 행동), 등장 순서별로 키를
    // 나눠서 서로 다른 아이콘으로 다룬다(TargetSlot.key 참고).
    const occurrenceCount = new Map<number, number>()
    const keyFor = (unitId: number): string => {
      const occurrence = (occurrenceCount.get(unitId) ?? 0) + 1
      occurrenceCount.set(unitId, occurrence)
      return occurrence === 1 ? String(unitId) : `${unitId}:${occurrence}`
    }

    const queue = rotated.slice(1)
    const targets: TargetSlot[] = [
      {
        key: keyFor(current.id),
        unit: current,
        role: 'current',
        x: STRIP_MARGIN,
        y: STRIP_MARGIN,
        size: CURRENT_SIZE,
        frameUrl: TURN_ORDER_FRAME_CURRENT_URL,
        accentAllyUrl: TURN_ORDER_ACTOR_URL,
        accentEnemyUrl: TURN_ORDER_ENEMY_URL,
      },
    ]

    this.bar.visible = queue.length > 0
    if (queue.length > 0) {
      const barX = STRIP_MARGIN + CURRENT_SIZE + BAR_GAP
      const queueY = STRIP_MARGIN + (CURRENT_SIZE - QUEUE_SIZE) / 2
      queue.forEach((unit, i) => {
        targets.push({
          key: keyFor(unit.id),
          unit,
          role: 'queue',
          x: barX + BAR_PADDING_X + i * (QUEUE_SIZE + QUEUE_GAP),
          y: queueY,
          size: QUEUE_SIZE,
          frameUrl: TURN_ORDER_FRAME_WAIT_URL,
          accentAllyUrl: TURN_ORDER_ACTOR2_URL,
          accentEnemyUrl: TURN_ORDER_ENEMY2_URL,
        })
      })
    }

    this.applyTargets(targets)
  }

  /**
   * 화면에 이미 떠있는 아이콘(TargetSlot.key 기준)은 새 목표 위치/크기/역할로
   * 애니메이션하고, 새로 등장한 유닛은 페이드인, 더 이상 목표 목록에 없는 유닛
   * (사망/창밖으로 밀림)은 페이드아웃 후 제거한다. 첫 렌더(아무 아이콘도 없을 때)는
   * 애니메이션 없이 바로 배치.
   */
  private applyTargets(targets: TargetSlot[]): void {
    const isFirstRender = this.icons.size === 0
    const usedKeys = new Set<string>()

    for (const target of targets) {
      usedKeys.add(target.key)
      const existing = this.icons.get(target.key)

      if (!existing) {
        const handle = this.buildIcon(target)
        if (isFirstRender) {
          handle.container.alpha = 1
        } else {
          handle.container.alpha = 0
          this.fadeIn(handle)
        }
        this.icons.set(target.key, handle)
        continue
      }

      const samePlace =
        existing.role === target.role &&
        Math.abs(existing.container.position.x - target.x) < 0.5 &&
        Math.abs(existing.container.position.y - target.y) < 0.5
      if (!samePlace) {
        this.animateToTarget(existing, target)
      }
    }

    for (const [key, handle] of this.icons) {
      if (!usedKeys.has(key)) {
        this.icons.delete(key)
        void this.fadeOutAndDestroy(handle)
      }
    }
  }

  private animateToTarget(handle: IconHandle, target: TargetSlot): void {
    const fromX = handle.container.position.x
    const fromY = handle.container.position.y
    const fromSize = handle.size
    const toX = target.x
    const toY = target.y
    const toSize = target.size

    // 역할이 바뀌면(대기열 -> 현재 턴) 프레임/진영색 텍스처를 애니메이션 시작 시점에
    // 미리 바꿔둔다 - 위치/크기는 계속 부드럽게 움직이는 중이라 텍스처 전환 자체는
    // 순간적이어도 전체적으로 자연스럽게 이어져 보인다.
    if (handle.role !== target.role) {
      handle.frame.texture = Texture.from(target.frameUrl)
      handle.accent.texture = Texture.from(target.unit.side === 'ally' ? target.accentAllyUrl : target.accentEnemyUrl)
      handle.role = target.role
    }

    handle.size = toSize
    void tween(this.app, TRANSITION_DURATION_MS, (progress) => {
      const eased = easeOutQuad(progress)
      handle.container.position.set(fromX + (toX - fromX) * eased, fromY + (toY - fromY) * eased)
      this.applySize(handle, fromSize + (toSize - fromSize) * eased)
    })
  }

  private fadeIn(handle: IconHandle): void {
    void tween(this.app, TRANSITION_DURATION_MS, (progress) => {
      handle.container.alpha = progress
    })
  }

  private async fadeOutAndDestroy(handle: IconHandle): Promise<void> {
    await tween(this.app, TRANSITION_DURATION_MS, (progress) => {
      handle.container.alpha = 1 - progress
    })
    handle.container.destroy({ children: true })
  }

  private clearAll(): void {
    for (const [key, handle] of this.icons) {
      this.icons.delete(key)
      void this.fadeOutAndDestroy(handle)
    }
    this.bar.visible = false
  }

  private buildIcon(target: TargetSlot): IconHandle {
    const container = new Container()
    container.position.set(target.x, target.y)
    this.container.addChild(container)

    const frame = Sprite.from(target.frameUrl)
    container.addChild(frame)

    const accent = Sprite.from(target.unit.side === 'ally' ? target.accentAllyUrl : target.accentEnemyUrl)
    container.addChild(accent)

    const characterTex = this.characterTextureFor(target.unit)
    let character: Sprite | undefined
    if (characterTex && characterTex.width > 0 && characterTex.height > 0) {
      character = new Sprite(characterTex)
      character.anchor.set(0.5)
      container.addChild(character)
    }

    const handle: IconHandle = { container, frame, accent, character, role: target.role, size: target.size }
    this.applySize(handle, target.size)
    return handle
  }

  /** frame/accent를 size x size로, 캐릭터는 그 안을 꽉 채우도록(cover, 튀어나감 허용) 다시 맞춘다. */
  private applySize(handle: IconHandle, size: number): void {
    handle.frame.width = size
    handle.frame.height = size
    handle.accent.width = size
    handle.accent.height = size

    if (handle.character) {
      const tex = handle.character.texture
      const scale = Math.max(size / tex.width, size / tex.height)
      handle.character.scale.set(scale)
      handle.character.position.set(size / 2, size / 2)
    }
  }

  /**
   * DragonBones 유닛(고블린 등)은 사용자가 직접 그려서 추가하는 정적 배틀러
   * 이미지(characters.ts::getStaticBattlerTexture, <DragonBonesData> 스켈레톤
   * 이름으로 조회)를 쓴다 - sv 9x6 애니메이션 시트가 아니라 완성된 그림 한 장이라
   * 별도 프레임 계산이 필요 없다. 아직 안 추가됐으면(또는 DragonBones 유닛이
   * 아니면) 기존처럼 sv 배틀러 idle 프레임(아군은 액터 시트, 적군은 적 시트)으로
   * 대체한다. 둘 다 preloadBattleAssets()/preloadCharacterAnimations()가 전투
   * 시작 전에 이미 다 로드해두는 정적 에셋이라 런타임 타이밍 문제가 없다.
   */
  private characterTextureFor(unit: BattleUnit): Texture | undefined {
    if (unit.dragonbones_skeleton) {
      const staticTex = getStaticBattlerTexture(unit.dragonbones_skeleton)
      if (staticTex) return staticTex
    }

    const characterKey = characterKeyForSprite(unit.sprite)
    return characterKey ? getCharacterFrames(characterKey)?.idle?.[0] : undefined
  }
}
