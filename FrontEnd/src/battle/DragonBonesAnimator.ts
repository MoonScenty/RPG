import type { Container } from 'pixi.js'
import { EventObject, type PixiArmatureDisplay } from './dragonbones'
import type { Animator, StateMotion } from './BattlerSprite'
import { ATTACK_ANIM_CANDIDATES, DEATH_ANIM_CANDIDATES, type AnimName } from './characters'

/**
 * 우리 내부 모션 이름(idle/damage/swing/thrust/missile/dying/dead/victory/frontstep/
 * backstep/pinch...) -> 이 유닛의 스켈레톤 클립 이름(예: "Attack A"). 스켈레톤마다
 * 클립 이름이 제각각이라 <DragonBonesMotion: 모션이름, 클립이름> 노트태그로 유닛별로
 * 채워진다(units.dragonbones_motions, MzNoteTagParser::parseEnemyTags() 참고).
 */
export type DragonBonesMotionMap = Partial<Record<AnimName, string>>

// sv 배틀러의 1회성 포즈(swing/damage 등)는 프레임이 2~3장뿐이라 0.3초 안팎으로
// 끝나는데, DragonBones 클립은 프레임 수가 훨씬 많아 원본 그대로 재생하면 턴 진행이
// 그 긴 시간만큼 멈춰 있는 것처럼 보인다(공격 -> 피격 -> 원위치까지 전부 이전 단계가
// 끝나야 다음이 시작되는 BattleScene.animateAction()의 순차 구조라 더 두드러짐).
// 그래서 1회 재생 클립은 timeScale로 이 길이에 맞춰 압축한다 - 원본이 이보다 짧으면
// (느려지지 않도록) 그대로 둔다.
const ONE_SHOT_TARGET_SECONDS = 0.35

// <DragonBonesMotion> 노트태그의 클립 이름이 실제 스켈레톤에 없거나(오타, 리그
// 교체 등) 애니메이션이 재생 도중 다른 상태로 끊기면 EventObject.COMPLETE가
// 영영 안 올 수 있다 - 그러면 playOnce()의 Promise가 영원히 안 풀려서 턴 진행
// 전체가 멈춰버린다(전투 중 캐릭터가 멈추는 버그로 보고됨). 예상 재생시간을
// 넘기면 그냥 완료 처리하는 안전장치를 둔다.
const PLAYONCE_TIMEOUT_SLACK_MS = 1000

/** DragonBones 스켈레톤 애니메이션을 BattlerSprite와 동일한 인터페이스로 감싼 래퍼. */
export class DragonBonesAnimator implements Animator {
  readonly view: Container

  private readonly display: PixiArmatureDisplay
  private readonly motions: DragonBonesMotionMap
  private stateMotion: StateMotion = null

  constructor(display: PixiArmatureDisplay, motions: DragonBonesMotionMap) {
    this.display = display
    this.motions = motions
    this.view = display
    this.playIdleBaseline()
  }

  // 매핑 안 된 모션은 sv 배틀러가 없는 포즈를 조용히 건너뛰는 것과 동일하게 처리된다.
  hasAnimation(name: AnimName): boolean {
    return Boolean(this.motions[name])
  }

  firstAvailable(candidates: AnimName[]): AnimName | undefined {
    return candidates.find((name) => this.hasAnimation(name))
  }

  // 리그에 별도 pinch 포즈가 없어 저체력 표현은 지금은 생략(추후 색조 등으로 보강 가능).
  setLowHp(_low: boolean): void {}

  setStateMotion(motion: StateMotion): void {
    if (this.stateMotion === motion) return
    this.stateMotion = motion
    this.playIdleBaseline()
  }

  /** <DragonBonesMotion: abnormal/sleep, 클립> 매핑이 있으면 우선하고, 없으면 sv 배틀러와 동일하게 조용히 idle로 대체. */
  playIdleBaseline(): void {
    const clip = (this.stateMotion && this.motions[this.stateMotion]) || this.motions.idle
    if (!clip) return
    this.display.animation.play(clip, 0)
  }

  playOnce(name: AnimName): Promise<void> {
    const clip = this.motions[name]
    if (!clip) {
      return Promise.resolve()
    }

    return new Promise((resolve) => {
      let done = false
      const finish = (): void => {
        if (done) return
        done = true
        this.display.removeDBEventListener(EventObject.COMPLETE, onComplete, this)
        clearTimeout(timer)
        resolve()
      }
      const onComplete = (): void => finish()

      this.display.addDBEventListener(EventObject.COMPLETE, onComplete, this)
      const state = this.display.animation.play(clip, 1)
      if (state && state.totalTime > ONE_SHOT_TARGET_SECONDS) {
        state.timeScale = state.totalTime / ONE_SHOT_TARGET_SECONDS
      }

      const expectedSeconds = state ? Math.min(state.totalTime, ONE_SHOT_TARGET_SECONDS) : ONE_SHOT_TARGET_SECONDS
      const timer = setTimeout(finish, expectedSeconds * 1000 + PLAYONCE_TIMEOUT_SLACK_MS)
    })
  }

  playOnceAny(candidates: AnimName[]): Promise<void> {
    const name = this.firstAvailable(candidates)
    return name ? this.playOnce(name) : Promise.resolve()
  }

  /** sv 배틀러와 동일하게 장착 무기 모션(thrust/swing/missile)을 우선 시도하고, 없으면 swing으로 대체. */
  playAttack(motion?: AnimName): Promise<void> {
    const candidates = motion ? [motion, ...ATTACK_ANIM_CANDIDATES] : ATTACK_ANIM_CANDIDATES
    return this.playOnceAny(candidates)
  }

  playFrontstep(): Promise<void> {
    return this.playOnceAny(['frontstep'])
  }

  async playDown(): Promise<void> {
    if (this.hasAnimation('dying')) {
      await this.playOnce('dying')
    }
    await this.playOnceAny(DEATH_ANIM_CANDIDATES)
    this.display.animation.stop()
  }

  snapToDeadPose(): void {
    const name = this.firstAvailable(DEATH_ANIM_CANDIDATES)
    const clip = name ? this.motions[name] : undefined
    if (!clip) {
      this.display.animation.stop()
      return
    }
    this.display.animation.play(clip, 1)
  }
}
