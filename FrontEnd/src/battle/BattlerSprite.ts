import { AnimatedSprite, type Container, Texture, type FrameObject } from 'pixi.js'
import {
  ATTACK_ANIM_CANDIDATES,
  DEATH_ANIM_CANDIDATES,
  FRONTSTEP_ANIM_CANDIDATES,
  type AnimName,
} from './characters'

export const LOW_HP_RATIO = 0.2

/**
 * BattleScene이 실제로 의존하는 최소 공개 API - sv 프레임시트 기반 BattlerSprite와
 * 스켈레톤 기반 DragonBonesAnimator(./DragonBonesAnimator.ts) 둘 다 이 인터페이스만
 * 만족하면 BattleScene 쪽 코드는 어느 쪽인지 몰라도 된다.
 */
/** BattleUnit.state_motion과 동일 - 걸린 상태 중 우선순위가 가장 높은 것의 대기 포즈. */
export type StateMotion = 'abnormal' | 'sleep' | null

export interface Animator {
  readonly view: Container
  hasAnimation(name: AnimName): boolean
  setLowHp(low: boolean): void
  setStateMotion(motion: StateMotion): void
  playIdleBaseline(): void
  playOnce(name: AnimName): Promise<void>
  playOnceAny(candidates: AnimName[]): Promise<void>
  playAttack(motion?: AnimName): Promise<void>
  playFrontstep(): Promise<void>
  playDown(): Promise<void>
  snapToDeadPose(): void
}

const MULTI_FRAME_MS = 90 // 다프레임 모션(swing/victory 등) 1프레임당
const SINGLE_FRAME_MS = 260 // 단일프레임 포즈(damage/guard/frontstep 등) 유지 시간
// idle/pinch는 항시 루프 재생되는 대기 자세라, 같은 3프레임이라도 공격
// 모션과 같은 속도(90ms)로 돌리면 숨쉬기가 아니라 파르르 떠는 것처럼 보인다.
const IDLE_FRAME_MS = 220

function toFrameObjects(textures: Texture[], frameMs = MULTI_FRAME_MS): FrameObject[] {
  const time = textures.length > 1 ? frameMs : SINGLE_FRAME_MS
  return textures.map((texture) => ({ texture, time }))
}

/** 캐릭터 한 명의 애니메이션 프레임을 이름으로 재생하는 PixiJS AnimatedSprite 래퍼. */
export class BattlerSprite implements Animator {
  readonly view: AnimatedSprite

  private readonly frames: Partial<Record<AnimName, Texture[]>>
  private lowHp = false
  private stateMotion: StateMotion = null
  private oneShotActive = false

  constructor(frames: Partial<Record<AnimName, Texture[]>>) {
    this.frames = frames
    const idle = frames.idle ?? [Texture.EMPTY]
    this.view = new AnimatedSprite(toFrameObjects(idle, IDLE_FRAME_MS))
    this.view.loop = true
    this.view.play()
  }

  hasAnimation(name: AnimName): boolean {
    return (this.frames[name]?.length ?? 0) > 0
  }

  /** 후보 이름 중 이 캐릭터가 실제로 갖고 있는 첫 번째 이름. */
  firstAvailable(candidates: AnimName[]): AnimName | undefined {
    return candidates.find((name) => this.hasAnimation(name))
  }

  setLowHp(low: boolean): void {
    if (this.lowHp === low) return
    this.lowHp = low
    if (!this.oneShotActive) {
      this.playIdleBaseline()
    }
  }

  setStateMotion(motion: StateMotion): void {
    if (this.stateMotion === motion) return
    this.stateMotion = motion
    if (!this.oneShotActive) {
      this.playIdleBaseline()
    }
  }

  /** 루핑 idle로 복귀 - 상태이상 모션(sleep/abnormal) > 저체력(pinch) > 기본(idle) 순으로 우선한다. */
  playIdleBaseline(): void {
    this.oneShotActive = false
    const name: AnimName =
      (this.stateMotion && this.hasAnimation(this.stateMotion) ? this.stateMotion : null) ??
      (this.lowHp && this.hasAnimation('pinch') ? 'pinch' : 'idle')
    const textures = this.frames[name]
    if (!textures || textures.length === 0) return

    this.view.textures = toFrameObjects(textures, IDLE_FRAME_MS)
    this.view.loop = textures.length > 1
    this.view.gotoAndPlay(0)
  }

  /**
   * 애니메이션 하나를 끝까지 재생한 뒤 idle/pinch로 복귀한다.
   * 이 캐릭터에 해당 클립이 없으면 즉시 resolve(no-op) - 호출부가 다른
   * 방식(예: 틴트 플래시)으로 대체하도록 되어 있다.
   */
  playOnce(name: AnimName): Promise<void> {
    const textures = this.frames[name]
    if (!textures || textures.length === 0) {
      return Promise.resolve()
    }

    this.oneShotActive = true

    return new Promise((resolve) => {
      this.view.loop = false
      this.view.textures = toFrameObjects(textures)
      this.view.onComplete = () => resolve()
      this.view.gotoAndPlay(0)
    })
  }

  /** candidates 중 이 캐릭터가 가진 첫 애니메이션을 재생. 하나도 없으면 no-op. */
  playOnceAny(candidates: AnimName[]): Promise<void> {
    const name = this.firstAvailable(candidates)
    return name ? this.playOnce(name) : Promise.resolve()
  }

  /**
   * 공격 모션 재생 - 장착 무기 기반 모션(thrust/swing/missile)을 우선 시도하고,
   * 이 캐릭터에게 없으면 기본 후보(swing)로 대체한다.
   */
  playAttack(motion?: AnimName): Promise<void> {
    const candidates = motion ? [motion, ...ATTACK_ANIM_CANDIDATES] : ATTACK_ANIM_CANDIDATES
    return this.playOnceAny(candidates)
  }

  /** 전진 모션 재생 (frontstep, 12_Akja는 flontstep 오타). */
  playFrontstep(): Promise<void> {
    return this.playOnceAny(FRONTSTEP_ANIM_CANDIDATES)
  }

  /** 사망 연출: dying(있으면) -> dead/down 순으로 재생 후 마지막 프레임에 고정. */
  async playDown(): Promise<void> {
    if (this.hasAnimation('dying')) {
      await this.playOnce('dying')
    }
    await this.playOnceAny(DEATH_ANIM_CANDIDATES)
    this.oneShotActive = true
    this.view.stop()
  }

  /** 이미 죽어있는 유닛을 로드할 때 애니메이션 없이 사망 포즈로 바로 고정. */
  snapToDeadPose(): void {
    const name = this.firstAvailable(DEATH_ANIM_CANDIDATES)
    const textures = name ? this.frames[name] : undefined
    if (!textures || textures.length === 0) return

    this.oneShotActive = true
    this.view.loop = false
    this.view.textures = toFrameObjects(textures)
    this.view.gotoAndStop(textures.length - 1)
  }
}
