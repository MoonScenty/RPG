import { Application, Assets, Container, FillGradient, Graphics, Point, Sprite, Text } from 'pixi.js'
import {
  advanceTurn,
  createOrResumeBattle,
  getBattleAnimations,
  getBattleAudio,
  isUnitAlive,
  type BattleAudio,
  type BattleLogEntry,
  type BattleLogTarget,
  type BattleState,
  type BattleUnit,
} from '@/lib/battleApi'
import { battleback1Url, battleback2Url, SHADOW_URL } from './assets'
import { playBattleBgm, playResultMe, stopBattleBgm } from './audio'
import { BattlerSprite, LOW_HP_RATIO, type Animator } from './BattlerSprite'
import { characterKeyForSprite, getCharacterFrames } from './characters'
import { loadCircleImage } from './circleImages'
import { buildArmature } from './dragonbones'
import { DragonBonesAnimator } from './DragonBonesAnimator'
import { MvAnimationPlayer } from './mvAnimation'
import { slotPosition, STAGE_HEIGHT, STAGE_WIDTH } from './layout'
import { FONT_FAMILY } from './theme'
import { easeOutQuad, tween } from './tween'
import { estimateNextTurnDelayMs, interpolateGauges } from './atbPrediction'
import { PartyHud } from './PartyHud'
import { TurnOrderStrip } from './TurnOrderStrip'

// 모든 유닛(아군+적)이 동일한 sv 배틀러 스타일 애니메이션 시트를 쓰므로 단일 스케일.
// 사용자 지시로 전체 80% 축소(1.3 -> 1.04) - layout.ts의 LAYOUT_SCALE(배치 간격도
// 같은 0.8배로 화면 중앙 기준 재배치)과 세트로 적용한 값이라 반드시 같이 맞춰야 한다.
const BATTLER_SCALE = 1.3 * 0.8

// DragonBones 스켈레톤은 sv 시트와 전혀 다른 내부 좌표 단위로 작업되어(고블린
// HeadlessHW 리그는 aabb 높이가 1000px대) 고정 배율을 쓸 수 없다 - getLocalBounds()로
// 실측한 높이를 이 값에 맞춰 매 유닛마다 동적으로 정규화한다. sv 배틀러의 대략적인
// 화면상 높이(cellHeight*BATTLER_SCALE)에 맞춘 추정값이라, 실제로 보면서 조정이 필요할 수 있다.
// 사용자 지시로 전체 80% 축소(150 -> 120) - 위 BATTLER_SCALE과 같은 이유.
const DRAGONBONES_TARGET_HEIGHT = 150 * 0.8

// MZ 사이드뷰 배틀처럼 대상 앞까지 걸어가지 않고, 제자리에서 이 거리만큼만
// 살짝 앞으로 내딛고 공격 모션을 취한다(frontstep/backstep은 원래 이런 짧은
// 스텝용 모션이지, 화면을 가로지르는 이동용이 아니다).
const ATTACK_STEP_DISTANCE = 60

// 배틀로그 한 줄을 담는 반투명 박스(사용자 지시) - 화면 최상단에서 10px, 가로
// 중앙, 폭 600px, 모서리 반경 5, 검정 배경 불투명도 0.2. 항상 떠있지 않고
// 메시지가 바뀔 때마다 페이드인 -> 잠깐 유지 -> 페이드아웃한다(showLog() 참고).
// 124 -> 10(사용자 지시).
const LOG_BOX_TOP = 10
const LOG_BOX_WIDTH = 600
const LOG_BOX_PADDING_Y = 10
const LOG_BOX_RADIUS = 5
const LOG_BOX_COLOR = 0x000000
const LOG_BOX_ALPHA = 0.2
const LOG_FADE_MS = 250
const LOG_HOLD_MS = 2500

// 적 스프라이트 발밑에 이름을 표시(reference_resource/design/ref5.png 참고, HP 바는 사용자
// 요청으로 제거됨). 스프라이트가 공격 시 앞으로 살짝 내딛는 애니메이션을 타므로,
// 라벨은 유닛 컨테이너가 아니라 unitLayer에 별도로 얹어서 흔들리지 않게 고정한다.
const ENEMY_LABEL_GAP = 10 // 발밑~이름

interface EnemyLabel {
  group: Container
}

interface UnitView {
  unit: BattleUnit
  container: Container
  sprite: Container
  hitOverlay: Container
  animator: Animator
  basePos: { x: number; y: number }
  displayHeight: number
  /** 부활 스킬 등으로 죽었다 살아나는 걸 감지하기 위한 직전 생존 여부. */
  wasAlive: boolean
}

export class BattleScene {
  readonly root = new Container()

  private readonly app: Application
  private readonly mvAnimations: MvAnimationPlayer
  private readonly onExit: () => void

  // 기본값(Grassland)으로 우선 그려두고, start()가 상태를 받으면 실제 트룹의
  // battleback1/2로 텍스처만 교체한다(applyBattleback() 참고).
  private readonly bg1: Sprite
  private readonly bg2: Sprite
  private readonly unitLayer = new Container()
  private readonly statusText: Text
  // 배틀로그 박스(배경+텍스트)를 한 덩어리로 페이드인/아웃시키기 위한 래퍼.
  private readonly logGroup = new Container()
  private readonly resultLayer = new Container()
  private readonly turnOrderStrip: TurnOrderStrip

  private unitViews = new Map<number, UnitView>()
  private enemyLabels = new Map<number, EnemyLabel>()
  private partyHud: PartyHud | null = null
  private audio: BattleAudio | null = null
  private battleId = 0
  private stopped = false
  // loop()가 다음 advanceTurn() 호출 전에 "게이지가 실제로 차오르는" 대기 연출을
  // 계산/재생하는 데 쓰는 가장 최근 상태 스냅샷(atb_gauge/spd 기준).
  private latestState: BattleState | null = null
  // showLog() 호출마다 증가시켜서, 새 메시지가 오면 이전 메시지의 유지/페이드아웃
  // 대기 중이던 타이머가 뒤늦게 끼어들어 화면을 지우지 않게 막는다.
  private logToken = 0

  constructor(app: Application, onExit: () => void) {
    this.app = app
    this.mvAnimations = new MvAnimationPlayer(app)
    this.onExit = onExit
    this.turnOrderStrip = new TurnOrderStrip(app)

    // back1(근경 - 들판, 완전 불투명)을 화면 전체에 먼저 깔고, back2(원경 - 위쪽엔
    // 하늘/산, 아래쪽은 투명)를 그 위에 풀스크린으로 덮는다. back2의 투명한
    // 아래쪽으로 back1이 그대로 비쳐 보이고, 불투명한 위쪽만 back1을 덮어써서
    // 굳이 높이를 나눠 배치하지 않아도 알파가 알아서 합성해준다.
    this.bg1 = Sprite.from(battleback1Url(null))
    this.bg1.width = STAGE_WIDTH
    this.bg1.height = STAGE_HEIGHT
    this.root.addChild(this.bg1)

    this.bg2 = Sprite.from(battleback2Url(null))
    this.bg2.width = STAGE_WIDTH
    this.bg2.height = STAGE_HEIGHT
    this.root.addChild(this.bg2)

    this.root.addChild(this.unitLayer)
    this.root.addChild(this.mvAnimations.container)

    this.statusText = new Text({
      text: '전투 준비 중...',
      style: {
        fill: 0xffe9a8,
        fontSize: 22,
        fontFamily: FONT_FAMILY,
        stroke: { color: 0x2a1a00, width: 4 },
      },
    })

    // 배틀로그 한 줄을 담는 반투명 배경 박스 - 텍스트 높이 기준으로 세로 패딩만
    // 더해서 한 줄 높이에 딱 맞춘다(내용이 바뀌어도 박스 크기는 고정).
    const logBoxHeight = this.statusText.height + LOG_BOX_PADDING_Y * 2
    const logBoxX = STAGE_WIDTH / 2 - LOG_BOX_WIDTH / 2
    const logBg = new Graphics()
      .roundRect(logBoxX, LOG_BOX_TOP, LOG_BOX_WIDTH, logBoxHeight, LOG_BOX_RADIUS)
      .fill({ color: LOG_BOX_COLOR, alpha: LOG_BOX_ALPHA })
    this.logGroup.addChild(logBg)

    this.statusText.anchor.set(0.5, 0.5)
    this.statusText.position.set(STAGE_WIDTH / 2, LOG_BOX_TOP + logBoxHeight / 2)
    this.logGroup.addChild(this.statusText)

    // 항상 떠있지 않고 showLog()가 호출될 때만 페이드인한다.
    this.logGroup.alpha = 0
    this.root.addChild(this.logGroup)

    this.root.addChild(this.turnOrderStrip.container)

    this.resultLayer.visible = false
    this.root.addChild(this.resultLayer)
  }

  /**
   * 배틀로그 박스를 페이드인 -> 잠깐 유지 -> 페이드아웃한다(사용자 지시) - 항상
   * 떠있는 대신 메시지 하나당 한 번씩 재생. 유지/페이드아웃 대기 중에 새
   * showLog()가 호출되면(다음 턴이 그 사이에 진행되는 경우) logToken이 바뀌어서
   * 이전 호출의 남은 대기는 화면을 건드리지 않고 조용히 멈춘다.
   */
  private showLog(text: string): void {
    this.statusText.text = text
    const token = ++this.logToken
    void this.playLogFade(token)
  }

  private async playLogFade(token: number): Promise<void> {
    await tween(this.app, LOG_FADE_MS, (progress) => {
      this.logGroup.alpha = easeOutQuad(progress)
    })
    if (token !== this.logToken) return

    await this.wait(LOG_HOLD_MS)
    if (token !== this.logToken) return

    await tween(this.app, LOG_FADE_MS, (progress) => {
      this.logGroup.alpha = 1 - easeOutQuad(progress)
    })
  }

  private wait(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms))
  }

  /**
   * showLog()와 달리 페이드아웃 없이 계속 떠있는 채로 보여준다 - 승패 결과처럼
   * 더 이상 다음 로그가 오지 않아 자연스럽게 안 사라지면 곤란한 마지막 문구용.
   * logToken을 증가시켜서 그 시점에 진행 중이던 showLog()의 페이드아웃 대기가
   * 뒤늦게 이 문구를 지우지 못하게 막는다.
   */
  private showLogPersistent(text: string): void {
    this.statusText.text = text
    this.logToken++
    this.logGroup.alpha = 1
  }

  async start(): Promise<void> {
    try {
      const [state, audio, animations] = await Promise.all([
        createOrResumeBattle(),
        getBattleAudio(),
        getBattleAnimations(),
      ])
      this.battleId = state.id
      this.audio = audio
      this.mvAnimations.setCatalog(animations)
      this.latestState = state

      await Promise.all([
        this.renderUnits(state.units),
        this.applyBattleback(state.battleback1, state.battleback2),
      ])
      this.buildEnemyLabels(state.units)
      this.turnOrderStrip.update(state)

      const allies = state.units.filter((u) => u.side === 'ally')
      this.partyHud = new PartyHud(this.app, allies)
      this.root.addChild(this.partyHud.container)
      this.root.addChild(this.resultLayer) // 승패 오버레이를 맨 위로

      if (state.status === 'finished') {
        this.finish(state)
        return
      }

      playBattleBgm(audio.battle_bgm)

      const lastLog = state.logs[state.logs.length - 1]
      this.showLog(lastLog ? this.describeTurn(lastLog) : '전투 시작!')
      this.loop()
    } catch (err) {
      this.showLog(`전투를 불러오지 못했습니다: ${(err as Error).message}`)
    }
  }

  destroy(): void {
    this.stopped = true
    stopBattleBgm()
    this.root.destroy({ children: true })
  }

  /** 생성자에서 기본값(Grassland)으로 미리 그려둔 배경을 실제 트룹의 battleback1/2로 교체한다. */
  private async applyBattleback(name1: string | null, name2: string | null): Promise<void> {
    const [texture1, texture2] = await Promise.all([
      Assets.load(battleback1Url(name1)),
      Assets.load(battleback2Url(name2)),
    ])
    this.bg1.texture = texture1
    this.bg2.texture = texture2
  }

  private async renderUnits(units: BattleUnit[]): Promise<void> {
    const built = await Promise.all(units.map(async (unit) => ({ unit, ...(await this.buildUnitDisplay(unit)) })))

    for (const { unit, animator, display, hitOverlay, displayHeight } of built) {
      const basePos = slotPosition(unit.side, unit.slot)
      const container = new Container()
      container.position.set(basePos.x, basePos.y)

      // 아군 배틀러 발밑 그림자(사용자 요청) - container 원점(0,0)이 곧 발밑
      // 접지점(sprite/armature 피벗이 하단 중앙)이라 그 자리에 그대로 겹치면 된다.
      if (unit.side === 'ally') {
        const shadow = Sprite.from(SHADOW_URL)
        shadow.anchor.set(0.5, 0.5)
        shadow.scale.set(BATTLER_SCALE)
        container.addChild(shadow)
      }

      container.addChild(display)
      container.addChild(hitOverlay)

      this.unitLayer.addChild(container)
      const view: UnitView = {
        unit,
        container,
        sprite: display,
        hitOverlay,
        animator,
        basePos,
        displayHeight,
        wasAlive: isUnitAlive(unit),
      }
      this.unitViews.set(unit.id, view)
      this.setAliveState(view, false)
    }

    this.resortUnitDepth()
  }

  /** 뒷줄(basePos.y가 작음)이 항상 앞줄보다 뒤에 그려지도록 고정 depth 정렬 - 슬롯이 바뀔 때도 다시 호출된다. */
  private resortUnitDepth(): void {
    const byDepth = [...this.unitViews.values()].sort((a, b) => a.basePos.y - b.basePos.y)
    for (const view of byDepth) {
      this.unitLayer.addChild(view.container)
    }
  }

  /**
   * 유닛의 <DragonBonesData>/<DragonBonesTextureAtlasData> 노트태그 유무로 렌더링
   * 방식을 분기한다. DragonBones 로드가 실패해도(패키지 미설치, 네트워크 오류,
   * 잘못된 리그 이름 등) 전투 자체가 멈추면 안 되므로 sv 배틀러로 조용히 대체한다.
   */
  private async buildUnitDisplay(unit: BattleUnit): Promise<{
    animator: Animator
    display: Container
    hitOverlay: Container
    displayHeight: number
  }> {
    if (unit.dragonbones_skeleton && unit.dragonbones_atlas) {
      try {
        return await this.buildDragonBonesDisplay(unit, unit.dragonbones_skeleton, unit.dragonbones_atlas)
      } catch (err) {
        console.warn(`DragonBones 로드 실패, sv 배틀러로 대체합니다: ${unit.dragonbones_skeleton}`, err)
      }
    }

    return this.buildSvDisplay(unit)
  }

  private async buildDragonBonesDisplay(
    unit: BattleUnit,
    skeletonName: string,
    atlasName: string,
  ): Promise<{ animator: Animator; display: Container; hitOverlay: Container; displayHeight: number }> {
    const armature = await buildArmature(skeletonName, atlasName)
    const animator = new DragonBonesAnimator(armature, unit.dragonbones_motions ?? {})

    const bounds = armature.getLocalBounds()
    // 자동 정규화(bounds 실측 기반) 위에 <DragonBonesScale: n>(퍼센트, 기본 100) 노트태그로 미세 조정.
    const autoScale = bounds.height > 0 ? DRAGONBONES_TARGET_HEIGHT / bounds.height : 1
    const scale = autoScale * ((unit.dragonbones_scale ?? 100) / 100)

    // sv 배틀러의 anchor(0.5, 1)(발밑 중앙이 원점)과 같은 정렬이 되도록, 피벗을
    // 실측 바운딩박스의 하단 중앙으로 옮긴다(스켈레톤 원본 원점은 리그마다 제각각).
    armature.pivot.set(bounds.x + bounds.width / 2, bounds.y + bounds.height)
    armature.scale.set(scale)
    // sv 배틀러 시트가 왼쪽을 보도록 그려진 것과 같은 이유로 적 쪽만 좌우 반전.
    if (unit.side === 'enemy') {
      armature.scale.x *= -1
    }

    const displayHeight = bounds.height * scale
    const displayWidth = bounds.width * scale

    // 슬롯이 여러 장의 텍스처로 나뉘어 있어 sv처럼 텍스처 하나를 틴트 복제할 수
    // 없으니, 실측 크기의 사각형을 대신 겹쳐서 같은 피격 플래시 연출을 낸다.
    const hitOverlay = new Graphics()
      .rect(-displayWidth / 2, -displayHeight, displayWidth, displayHeight)
      .fill(0xffffff)
    hitOverlay.tint = 0xff2b2b
    hitOverlay.blendMode = 'add'
    hitOverlay.alpha = 0

    return { animator, display: armature, hitOverlay, displayHeight }
  }

  private buildSvDisplay(unit: BattleUnit): {
    animator: Animator
    display: Container
    hitOverlay: Container
    displayHeight: number
  } {
    const characterKey = characterKeyForSprite(unit.sprite)
    const frames = (characterKey ? getCharacterFrames(characterKey) : undefined) ?? {}
    const animator = new BattlerSprite(frames)
    const sprite = animator.view
    sprite.anchor.set(0.5, 1)
    sprite.scale.set(BATTLER_SCALE)
    const displayHeight = sprite.texture.height * BATTLER_SCALE

    // sv 배틀러 시트는 왼쪽을 보도록 그려져 있다(오른쪽에 서는 아군 기준).
    // 적은 반대편(왼쪽)에 서서 오른쪽을 봐야 하므로 좌우 반전한다.
    if (unit.side === 'enemy') {
      sprite.scale.x *= -1
    }

    // 같은 텍스처를 빨갛게 틴트+가산블렌드해 겹쳐두고, 피격 시에만 반짝이게 한다.
    const hitOverlay = new Sprite(sprite.texture)
    hitOverlay.anchor.copyFrom(sprite.anchor)
    hitOverlay.scale.copyFrom(sprite.scale)
    hitOverlay.tint = 0xff2b2b
    hitOverlay.blendMode = 'add'
    hitOverlay.alpha = 0

    return { animator, display: sprite, hitOverlay, displayHeight }
  }

  /** 이전 전투(재도전 등)의 잔여 라벨을 지운다 - buildEnemyLabels()가 호출부의 사전 정리에
   * 의존하지 않고 스스로 방어하도록, retry()의 정리 로직과 이 메서드로 통일했다. */
  private clearEnemyLabels(): void {
    for (const label of this.enemyLabels.values()) {
      label.group.destroy({ children: true })
    }
    this.enemyLabels.clear()
  }

  /** 적 스프라이트 발밑에 이름+HP 바 라벨을 붙인다(reference_resource/design/ref5.png 참고). */
  private buildEnemyLabels(units: BattleUnit[]): void {
    this.clearEnemyLabels()
    for (const unit of units) {
      if (unit.side !== 'enemy') continue
      const view = this.unitViews.get(unit.id)
      if (!view) continue

      const group = new Container()
      group.position.set(view.basePos.x, view.basePos.y + ENEMY_LABEL_GAP)
      this.unitLayer.addChild(group)

      const nameText = new Text({
        text: unit.name,
        style: {
          fill: 0xffe9a8,
          fontSize: 14,
          fontFamily: FONT_FAMILY,
          stroke: { color: 0x2a1a00, width: 3 },
        },
      })
      nameText.anchor.set(0.5, 0)
      group.addChild(nameText)

      this.enemyLabels.set(unit.id, { group })
    }
  }

  private updateEnemyLabels(units: BattleUnit[]): void {
    for (const unit of units) {
      if (unit.side !== 'enemy') continue
      const label = this.enemyLabels.get(unit.id)
      if (!label) continue

      label.group.visible = isUnitAlive(unit)
    }
  }

  /**
   * 죽어도 화면에서 지우지 않고 그 자리에 사망 포즈로 남긴다(소생술 같은 부활
   * 스킬이 대상으로 삼을 수 있어야 하므로). 죽는 순간에만 dying->dead 애니메이션을
   * 재생하고, 이미 죽어있던 유닛은 매 턴 다시 재생하지 않는다(wasAlive로 판별).
   * 죽어있다가 다시 살아나면(부활) 사망 포즈에서 idle로 복귀시킨다.
   *
   * 단, 이 캐릭터에 dying/dead 포즈가 아예 없으면(주로 <DragonBonesMotion>에
   * dying/dead를 안 걸어준 DragonBones 유닛) 죽은 채로 idle 포즈가 얼어붙어
   * "살아있는 것처럼" 보이는 게 더 어색하므로, 예전처럼 페이드아웃 후 숨긴다.
   *
   * 적은 부활 스킬의 대상이 될 일이 없어서(사용자 확인) 사망 포즈로 화면에
   * 계속 남겨두지 않는다 - dying/dead 포즈가 있으면 그 모션을 재생한 뒤에
   * 페이드아웃하며 사라지고, 없으면 기존처럼 바로 페이드아웃한다.
   */
  private setAliveState(view: UnitView, animate: boolean): void {
    const aliveNow = isUnitAlive(view.unit)

    if (aliveNow) {
      view.container.visible = true
      view.container.alpha = 1
      const hpRatio = view.unit.max_hp > 0 ? view.unit.current_hp / view.unit.max_hp : 0
      view.animator.setLowHp(hpRatio <= LOW_HP_RATIO)
      view.animator.setStateMotion(view.unit.state_motion)

      if (!view.wasAlive) {
        view.animator.playIdleBaseline()
      }
      view.wasAlive = true
      return
    }

    const isEnemy = view.unit.side === 'enemy'
    const hasDeathPose = view.animator.hasAnimation('dying') || view.animator.hasAnimation('dead')

    if (!hasDeathPose) {
      if (animate && view.wasAlive) {
        void this.fadeOutAndHide(view)
      } else {
        view.container.visible = false
      }
      view.wasAlive = false
      return
    }

    if (isEnemy) {
      if (animate && view.wasAlive) {
        view.container.visible = true
        view.container.alpha = 1
        void view.animator.playDown().then(() => this.fadeOutAndHide(view))
      } else {
        // 전투 재개 시점에 이미 죽어있던 적은 모션을 다시 재생할 필요 없이 바로 숨긴다.
        view.container.visible = false
      }
      view.wasAlive = false
      return
    }

    view.container.visible = true
    view.container.alpha = 1

    if (animate && view.wasAlive) {
      void view.animator.playDown()
    } else if (!animate) {
      view.animator.snapToDeadPose()
    }

    view.wasAlive = false
  }

  private fadeOutAndHide(view: UnitView): Promise<void> {
    return tween(this.app, 400, (p) => {
      view.container.alpha = 1 - p
    }).then(() => {
      view.container.visible = false
    })
  }

  /**
   * 다음 행동을 서버에 요청하기 전에, 실제로 게이지가 차오르는 것처럼 보이는
   * 대기 연출을 먼저 재생한다(사용자 요청 - 예전엔 고정 1.1초 대기였는데, 이제는
   * 다음 행동자의 spd/atb_gauge로 실제 걸리는 시간을 추정해서 그만큼만 기다리고,
   * 그 동안 PartyHud/TurnOrderStrip을 매 프레임 보간된 게이지로 다시 그린다).
   * 캐스팅 턴도 그 유닛의 ATB 주기가 한 번 더 도는 것뿐이라 별도 처리 없이 이
   * 대기 연출을 그대로 통과한다.
   */
  private async playGaugeFillDelay(): Promise<void> {
    const living = this.latestState?.units.filter(isUnitAlive) ?? []
    if (living.length === 0) return

    const delayMs = estimateNextTurnDelayMs(living)
    await tween(this.app, delayMs, (progress) => {
      if (this.stopped) return
      this.renderInterpolatedGauges(interpolateGauges(living, progress))
    })
  }

  private renderInterpolatedGauges(gauges: Map<number, number>): void {
    if (!this.latestState) return
    const units = this.latestState.units.map((u) => ({ ...u, atb_gauge: gauges.get(u.id) ?? u.atb_gauge }))
    this.partyHud?.update(units)
    this.turnOrderStrip.update({ ...this.latestState, units })
  }

  private async loop(): Promise<void> {
    if (this.stopped) return

    // playGaugeFillDelay()(게이지 보간 렌더링 - PartyHud/TurnOrderStrip.update() 호출)가
    // try 밖에 있으면, 그 안에서 예외가 하나라도 던져질 때 loop()의 재귀 호출(void this.loop())까지
    // 아무 캐치 없이 통째로 끊겨서 이후로 다시는 안 불린다 - 화면엔 에러 메시지도 안 뜨고
    // 그냥 "게임이 멈추는" 것처럼 보이는 버그였다(사용자 보고). 턴 사이클 전체를 try로
    // 감싸서 어떤 단계에서 문제가 생겨도 로그만 띄우고 다음 루프를 계속 이어가게 한다.
    try {
      await this.playGaugeFillDelay()
      if (this.stopped) return

      const state = await advanceTurn(this.battleId)
      this.latestState = state
      await this.playTurn(state)

      if (state.status === 'finished') {
        this.finish(state)
        return
      }
    } catch (err) {
      this.showLog(`오류: ${(err as Error).message}`)
    }

    if (!this.stopped) {
      void this.loop()
    }
  }

  private async playTurn(state: BattleState): Promise<void> {
    const turn = state.logs[state.logs.length - 1]

    // TURN ORDER(NEXT ACTION)는 이 턴의 타격/피격 연출과 무관하게, 서버가 이미
    // 이 행동을 확정한 시점(advanceTurn 응답)에 곧바로 다음 대기열로 넘어가야
    // 한다 - 아래 animateAction() 뒤로 이 update()가 밀려 있으면, 지금 막 공격을
    // "시작"한 유닛이 자기 공격 애니메이션(전진~타격~후진, 수백ms)을 재생하는
    // 내내 NEXT ACTION 칸에 자기 자신이 계속 떠 있는 것처럼 보인다(사용자 보고:
    // NEXT ACTION이 다음 차례가 아니라 지금 행동 "중"인 캐릭터를 보여줌). 유닛
    // 위치/HUD/사망 연출(아래)은 애니메이션 순서에 맞물려 있어 그대로 두고,
    // TurnOrderStrip만 먼저 갱신한다.
    this.turnOrderStrip.update(state)
    // PartyHud 카드 배경도 같은 이유로 애니메이션 시작 전에 미리 활성 유닛을
    // 넘긴다 - "지금 행동 중인 그 카드만 base.png, 나머지는 base2.png"가 실제
    // 타격 애니메이션 재생 내내 보여야 한다(사용자 지시). animateAction()이 끝난
    // 뒤 아래에서 다시 update()를 부르는데, 그땐 activeUnitId를 안 넘겨서 전부
    // base2.png로 돌아간다.
    this.partyHud?.update(state.units, turn?.actor_battle_unit_id)

    if (turn) {
      // 연출이 끝난 뒤가 아니라 시작할 때 로그 문구를 보여준다.
      this.showLog(this.describeTurn(turn))

      const actorView = this.unitViews.get(turn.actor_battle_unit_id)
      const targetView = turn.target_battle_unit_id
        ? this.unitViews.get(turn.target_battle_unit_id)
        : undefined

      if (actorView) {
        await this.animateAction(actorView, targetView, turn)
      }
    }

    // "자리 변경"(<SwapPosition>) 등으로 슬롯이 바뀐 유닛은 basePos/화면 위치를
    // 새 슬롯 기준으로 다시 계산한다 - 스킬 자체엔 별도 이동 연출이 없어(순간이동
    // 취급) 다음 애니메이션부터 바로 새 자리에서 시작한다.
    let slotsChanged = false
    for (const unit of state.units) {
      const view = this.unitViews.get(unit.id)
      if (view) {
        if (view.unit.slot !== unit.slot) {
          view.basePos = slotPosition(unit.side, unit.slot)
          view.container.position.set(view.basePos.x, view.basePos.y)
          slotsChanged = true
        }
        view.unit = unit
        this.setAliveState(view, true)
      }
    }
    if (slotsChanged) {
      this.resortUnitDepth()
    }

    this.partyHud?.update(state.units)
    this.updateEnemyLabels(state.units)
  }

  private async animateAction(
    actor: UnitView,
    target: UnitView | undefined,
    turn: BattleLogEntry,
  ): Promise<void> {
    // cancel(가늠쇠 규칙이 행동을 건너뛰기로 결정)은 대상도 이동도 없다 -
    // 아직 별도 연출이 없으니 중립색 플래시만 보여준다.
    if (turn.action_type === 'cancel') {
      await this.selfFlash(actor, 0x999999)
      return
    }

    // 캐스팅(시전 대기) 중인 턴 - 실제로 아무것도 맞지 않으므로 앞으로 나가지 않고
    // 제자리에서 chant 포즈만 취한다. 시전 중인 스킬에 <CircleImage: 배율, 이름>
    // 태그가 있으면(turn.circle_image_name) 그 이미지를 발밑에 겹쳐서 보여준다 -
    // 어떤 이펙트가 뜨는지는 이제 코드가 아니라 스킬 노트태그가 결정한다.
    if (turn.action_type === 'casting') {
      const castAnim = actor.animator.playOnce('chant')
      if (turn.circle_image_name) {
        void this.playCastingCircle(actor, turn.circle_image_name, turn.circle_image_scale ?? 1)
      }
      await castAnim
      actor.animator.playIdleBaseline()
      return
    }

    // 광역기(scope 2/8/10) - 대상이 여럿이라 한쪽으로 다가갔다 물러나는 연출은
    // 의미가 없으므로 제자리에서 공격 모션만 취하고, 맞는 진영 전원이 동시에
    // 피격 반응한다(백엔드 resolveAoeSkillUse가 채운 turn.targets).
    if (turn.targets && turn.targets.length > 0) {
      await this.animateAoeAction(actor, turn.targets, turn.skill_motion ?? undefined, turn.skill_animation_id ?? undefined)
      return
    }

    const forward = target
      ? {
          x: actor.basePos.x + Math.sign(target.basePos.x - actor.basePos.x) * ATTACK_STEP_DISTANCE,
          y: actor.basePos.y,
        }
      : actor.basePos

    const frontstep = actor.animator.playFrontstep()
    await tween(this.app, 180, (p) => {
      const e = easeOutQuad(p)
      actor.container.position.x = actor.basePos.x + (forward.x - actor.basePos.x) * e
      actor.container.position.y = actor.basePos.y + (forward.y - actor.basePos.y) * e
    })
    await frontstep

    // 일반 공격은 장착 무기 모션을, 스킬은 <SkillMotion> 태그가 있으면 그 모션을
    // 따른다 - 둘 다 없으면(태그 없는 스킬) playAttack()이 기본값(thrust)으로 폴백한다.
    const motion = turn.action_type === 'attack' ? actor.unit.attack_motion : (turn.skill_motion ?? undefined)
    const attackAnim = actor.animator.playAttack(motion)

    // 타격 모션이 다 끝난 뒤가 아니라 "취하는 바로 그 순간" 이펙트가 같이 나가야
    // 자연스럽다 - playAttack을 기다리기 전에 바로 쏜다. 일반 공격은 weapon_animation_id
    // (side 구분 없음: 아군은 장착 무기(ActorSeeder), 적은 <AttackAnimation> 노트태그
    // (EnemySeeder))를 우선 쓰고, 무기가 없어 그 값이 없을 때만 turn.skill_animation_id
    // (백엔드가 "공격"(id 0) 스킬 자체의 애니메이션을 폴백으로 실어보낸 값)로 대신한다.
    // 스킬은 skill_animation_id(RPGEditor 스킬 편집 화면의 "애니메이션" 필드,
    // SkillSeeder)를 그대로 쓴다 - 전부 없으면 이펙트 없이 모션만 재생.
    if (turn.action_type === 'attack' && target) {
      const attackAnimationId = actor.unit.weapon_animation_id ?? turn.skill_animation_id
      if (attackAnimationId !== null) void this.playWeaponEffect(attackAnimationId, target)
    } else if (turn.action_type === 'skill' && turn.skill_animation_id !== null && target) {
      void this.playWeaponEffect(turn.skill_animation_id, target)
    }

    await attackAnim

    if (target) {
      if (turn.hit_outcome === 'missed') {
        // 빗나감 - 공격이 대상에게 아예 안 닿았으니 대상은 반응하지 않는다.
      } else if (turn.hit_outcome === 'evaded') {
        await target.animator.playOnce('evade')
        target.animator.playIdleBaseline()
      } else {
        await target.animator.playOnce('damage')
        const targetSurvived = turn.target_hp_after === null || turn.target_hp_after > 0
        if (targetSurvived) {
          target.animator.playIdleBaseline()
        }

        if (turn.damage !== null && turn.damage > 0) {
          void this.spawnDamageNumber(target, turn.damage, turn.is_critical ?? false)
        }
      }
    }

    const backstep = actor.animator.playOnce('backstep')
    await tween(this.app, 160, (p) => {
      const e = easeOutQuad(p)
      actor.container.position.x = forward.x + (actor.basePos.x - forward.x) * e
      actor.container.position.y = forward.y + (actor.basePos.y - forward.y) * e
    })
    actor.container.position.set(actor.basePos.x, actor.basePos.y)
    await backstep
    actor.animator.playIdleBaseline()
  }

  /**
   * 광역기(scope 2/8/10) 연출 - 제자리에서 공격 모션 1회, 그동안 맞는 진영
   * 전원이 동시에 피격 반응 + 데미지 숫자를 띄운다. 대상 하나하나에게 다가가지
   * 않으므로 forward/backstep 이동이 없다. motion은 <SkillMotion> 태그 값 -
   * 없으면 playAttack()이 기본값(swing)으로 폴백한다.
   */
  private async animateAoeAction(
    actor: UnitView,
    targets: BattleLogTarget[],
    motion?: string,
    animationId?: number,
  ): Promise<void> {
    const attackAnim = actor.animator.playAttack(motion)

    // 단일 대상 스킬과 동일하게 "모션을 취하는 바로 그 순간" 이펙트가 나가야 하고,
    // 광역기라 맞은 대상 전원(빗나간 대상 제외) 위치에 각각 재생한다.
    if (animationId !== undefined) {
      for (const t of targets) {
        if (t.hit_outcome === 'missed') continue
        const view = this.unitViews.get(t.battle_unit_id)
        if (view) void this.playWeaponEffect(animationId, view)
      }
    }

    await attackAnim

    await Promise.all(
      targets.map(async (t) => {
        const view = this.unitViews.get(t.battle_unit_id)
        if (!view) return

        if (t.hit_outcome === 'missed') {
          return
        }
        if (t.hit_outcome === 'evaded') {
          await view.animator.playOnce('evade')
          view.animator.playIdleBaseline()
          return
        }

        await view.animator.playOnce('damage')
        if (t.hp_after > 0) {
          view.animator.playIdleBaseline()
        }
        if (t.damage !== null && t.damage > 0) {
          void this.spawnDamageNumber(view, t.damage, t.critical ?? false)
        }
      }),
    )

    actor.animator.playIdleBaseline()
  }

  /** 이 캐릭터에게 맞는 포즈가 없는 자기 반응(예: cancel)에 대한 대체 플래시. */
  private async selfFlash(view: UnitView, color: number): Promise<void> {
    const originalTint = view.hitOverlay.tint
    view.hitOverlay.tint = color
    await tween(this.app, 140, (p) => {
      view.hitOverlay.alpha = p < 0.5 ? p * 2 : (1 - p) * 2
    })
    view.hitOverlay.alpha = 0
    view.hitOverlay.tint = originalTint
  }

  /**
   * 대상의 화면상 위치에 MV 스프라이트시트 애니메이션(mz_animations)을 재생한다.
   * target.container의 원점이 이미 발밑(bottom-anchor)이라 그대로 기준점으로
   * 쓰면 되고, 머리/중앙/발밑 오프셋은 MvAnimationPlayer가 position 값으로
   * 알아서 계산한다. 로드/재생 실패는 전투 진행에 영향 없이 조용히 무시
   * - 애니메이션은 부가 연출이지 전투 로직의 일부가 아니다.
   */
  private async playWeaponEffect(animationId: number, target: UnitView): Promise<void> {
    try {
      const point = target.container.toGlobal(new Point(0, 0))
      // DragonBones 유닛은 슬롯이 여러 장의 텍스처로 나뉘어 있어 실루엣 모양
      // 플래시를 못 만들고, hitOverlay가 바운딩박스 사각형이라(buildDragonBonesDisplay
      // 참고) 대상 플래시(flashScope===1)를 그대로 적용하면 캐릭터 모양이 아니라
      // 네모 박스가 번쩍이는 것으로 보인다 - sv 배틀러(실루엣 플래시 가능)만 적용.
      const isDragonBones = target.unit.dragonbones_skeleton !== null
      await this.mvAnimations.playAt(animationId, {
        x: point.x,
        y: point.y,
        height: target.displayHeight,
        applyFlash: isDragonBones
          ? undefined
          : (color) => {
              target.hitOverlay.tint = (color[0] << 16) | (color[1] << 8) | color[2]
              target.hitOverlay.alpha = color[3] / 255
            },
        clearFlash: isDragonBones
          ? undefined
          : () => {
              target.hitOverlay.alpha = 0
            },
        hide: () => {
          target.container.visible = false
        },
        show: () => {
          target.container.visible = true
        },
      })
    } catch (err) {
      console.warn(`무기 애니메이션 재생 실패: ${animationId}`, err)
    }
  }

  /**
   * 캐스팅 스킬의 <CircleImage: 배율, 이름> 태그로 지정된 정지 이미지를 시전자
   * 발밑(container 원점 - sv/DragonBones 공통으로 발밑이 (0,0))에 겹쳐서
   * 페이드인 -> 유지 -> 페이드아웃한다. 캐릭터보다 뒤에 그리도록 맨 아래(0번
   * 인덱스)에 넣는다. 로드 실패는 조용히 무시(부가 연출).
   */
  private async playCastingCircle(actor: UnitView, imageName: string, scale: number): Promise<void> {
    try {
      const texture = await loadCircleImage(imageName)
      const sprite = new Sprite(texture)
      sprite.anchor.set(0.5, 1)
      sprite.scale.set(scale)
      sprite.alpha = 0
      actor.container.addChildAt(sprite, 0)

      await tween(this.app, 200, (p) => {
        sprite.alpha = p
      })
      await tween(this.app, 400, () => {})
      await tween(this.app, 200, (p) => {
        sprite.alpha = 1 - p
      })

      actor.container.removeChild(sprite)
      sprite.destroy()
    } catch (err) {
      console.warn(`캐스팅 서클 이미지 로드 실패: ${imageName}`, err)
    }
  }

  /**
   * 빨강/흰색 그라디언트 채움의 떠오르며 페이드아웃하는 데미지 숫자. 치명타면
   * 금색 그라디언트+더 큰 글자+"!" 접미사로 한눈에 구분되게 한다.
   */
  private async spawnDamageNumber(target: UnitView, damage: number, critical = false): Promise<void> {
    const gradient = new FillGradient(0, 0, 0, 1)
    if (critical) {
      gradient.addColorStop(0, '#fff4b8')
      gradient.addColorStop(1, '#ff8c00')
    } else {
      gradient.addColorStop(0, '#ffffff')
      gradient.addColorStop(1, '#ff2b2b')
    }

    const text = new Text({
      text: critical ? `${damage}!` : String(damage),
      style: {
        fontSize: critical ? 48 : 36,
        fill: gradient,
        stroke: { color: critical ? 0x4a2600 : 0x4a0000, width: 5 },
        fontFamily: FONT_FAMILY,
      },
    })
    text.anchor.set(0.5)
    text.position.set(0, -target.displayHeight - 40)
    target.container.addChild(text)

    await tween(this.app, 700, (p) => {
      text.position.y = -target.displayHeight - 40 - 30 * p
      text.alpha = 1 - p
    })

    target.container.removeChild(text)
    text.destroy()
  }

  /** 상단 상태 텍스트에 표시할, 턴 하나를 요약한 한 줄. */
  private describeTurn(turn: BattleLogEntry): string {
    const actorName = this.unitViews.get(turn.actor_battle_unit_id)?.unit.name ?? '???'
    const targetName = turn.target_battle_unit_id
      ? (this.unitViews.get(turn.target_battle_unit_id)?.unit.name ?? '???')
      : null

    switch (turn.action_type) {
      case 'attack': {
        if (turn.hit_outcome === 'missed') {
          return `${actorName}의 공격! 빗나갔다!`
        }
        if (turn.hit_outcome === 'evaded') {
          return `${actorName}의 공격! ${targetName}이(가) 회피했다!`
        }
        const critPrefix = turn.is_critical ? '치명타! ' : ''
        return `${critPrefix}${actorName}의 공격! ${targetName}에게 ${turn.damage ?? 0}의 피해`
      }
      case 'skill':
        // 광역기(scope 2/8/10)는 target_battle_unit_id가 없고 대신 targets 배열에
        // 대상별 결과가 담긴다 - 명중한 대상만 골라 피해를 합산해서 보여주고, 하나라도
        // 치명타가 있었으면 접두어를 붙인다. 전원이 빗나가거나 회피했으면 그것대로 표시.
        if (turn.targets && turn.targets.length > 0) {
          const hitTargets = turn.targets.filter((t) => t.hit_outcome === 'hit')
          if (hitTargets.length === 0) {
            return `${actorName}의 광역 스킬! 전부 빗나가거나 회피당했다`
          }
          const total = hitTargets.reduce((sum, t) => sum + Math.max(0, t.damage ?? 0), 0)
          const anyCrit = hitTargets.some((t) => t.critical)
          return `${anyCrit ? '치명타! ' : ''}${actorName}의 광역 스킬! 전체 대상에게 ${total}의 피해`
        }
        if (turn.hit_outcome === 'missed') {
          return `${actorName}의 스킬! 빗나갔다!`
        }
        if (turn.hit_outcome === 'evaded') {
          return `${actorName}의 스킬! ${targetName}이(가) 회피했다!`
        }
        return `${turn.is_critical ? '치명타! ' : ''}${actorName}의 강력한 스킬! ${targetName}에게 ${turn.damage ?? 0}의 피해`
      case 'item': {
        // 소모 아이템은 명중/회피 판정이 없어(항상 성공) hit_outcome 분기가 필요 없다.
        // damage는 HP 회복 스킬과 동일 컨벤션으로 음수(회복량) - MP 회복/상태 부여·해제는
        // 별도 로그 필드가 없어 문구에 수치로는 안 뜨고 다음 상태 갱신에 바로 반영된다.
        const itemLabel = turn.item_name ?? '아이템'
        if (turn.damage !== null && turn.damage < 0) {
          return `${actorName}이(가) ${itemLabel}을(를) 사용해 ${targetName}의 HP를 ${-turn.damage} 회복!`
        }
        return `${actorName}이(가) ${targetName}에게 ${itemLabel}을(를) 사용했다`
      }
      case 'casting': {
        // GambitCatalog::CASTING_SKILL_NAME("캐스팅") 더미 스킬의 message1(MZ 표준
        // %1/%2 치환 문법) - %1은 시전자, %2는 대상으로 치환한다(이 스킬 하나가 모든
        // 캐스팅 턴에 재사용되므로 다른 스킬처럼 %2가 스킬명은 아님). 비어있으면 기본 문구.
        const template = this.audio?.casting_message
        if (template) {
          return template.replaceAll('%1', actorName).replaceAll('%2', targetName ?? '???')
        }
        return `${actorName}이(가) ${targetName ?? '???'}을(를) 향해 주문을 준비하고 있다...`
      }
      case 'cancel':
        return `${actorName}이(가) 아무 행동도 하지 않았다`
      default:
        return `${actorName}의 행동`
    }
  }

  private finish(state: BattleState): void {
    const won = state.winner_side === 'ally'
    this.showLogPersistent(won ? '승리!' : '패배...')

    stopBattleBgm()
    playResultMe(won ? (this.audio?.victory_me ?? null) : (this.audio?.defeat_me ?? null))

    if (won) {
      for (const view of this.unitViews.values()) {
        if (view.unit.side === 'ally' && isUnitAlive(view.unit)) {
          void view.animator.playOnce('victory')
        }
      }
    }

    const overlay = new Graphics().rect(0, 0, STAGE_WIDTH, STAGE_HEIGHT).fill({ color: 0x000000, alpha: 0.5 })
    this.resultLayer.addChild(overlay)

    const label = new Text({
      text: won ? '승리했습니다!' : '패배했습니다...',
      style: { fill: won ? 0xffe066 : 0xff8a80, fontSize: 48, fontFamily: FONT_FAMILY },
    })
    label.anchor.set(0.5)
    label.position.set(STAGE_WIDTH / 2, STAGE_HEIGHT / 2 - 40)
    this.resultLayer.addChild(label)

    const buttonY = STAGE_HEIGHT / 2 + 50

    // 승리했을 때만 "재도전"을 같이 보여준다(패배는 기존처럼 메인으로만) - 둘 다
    // 있을 땐 가운데 기준으로 나란히, 하나만 있을 땐 정중앙에 둔다.
    if (won) {
      this.resultLayer.addChild(this.buildResultButton('재도전', STAGE_WIDTH / 2 - 130, buttonY, () => void this.retry()))
      this.resultLayer.addChild(this.buildResultButton('메인으로', STAGE_WIDTH / 2 + 130, buttonY, () => this.onExit()))
    } else {
      this.resultLayer.addChild(this.buildResultButton('메인으로', STAGE_WIDTH / 2, buttonY, () => this.onExit()))
    }

    this.resultLayer.visible = true
  }

  private buildResultButton(label: string, x: number, y: number, onClick: () => void): Container {
    const button = new Container()
    button.eventMode = 'static'
    button.cursor = 'pointer'
    const btnBg = new Graphics()
      .roundRect(-120, -28, 240, 56, 10)
      .fill({ color: 0x2b2320 })
      .stroke({ color: 0xc8a24a, width: 2 })
    const btnText = new Text({
      text: label,
      style: { fill: 0xf0e0b0, fontSize: 22, fontFamily: FONT_FAMILY },
    })
    btnText.anchor.set(0.5)
    button.addChild(btnBg, btnText)
    button.position.set(x, y)
    button.on('pointertap', onClick)

    return button
  }

  /**
   * "재도전" - 메인 화면으로 나가지 않고 같은 진형·같은 트룹으로 새 전투를 바로
   * 시작한다. 방금 끝난 전투는 status='finished'라 createOrResumeBattle()이
   * 자동으로 새 전투를 만들어준다(BattleEngine::createOrResume() 참고) - 여기서는
   * 화면에 남아있는 이전 전투의 유닛/HUD를 정리하고 start()를 다시 타면 된다.
   */
  private async retry(): Promise<void> {
    this.resultLayer.removeChildren()
    this.resultLayer.visible = false

    for (const view of this.unitViews.values()) {
      view.container.destroy({ children: true })
    }
    this.unitViews.clear()

    this.clearEnemyLabels()

    this.partyHud?.container.destroy({ children: true })
    this.partyHud = null

    this.stopped = false
    this.statusText.text = '전투 준비 중...'

    await this.start()
  }
}
