// Effekseer(파티클 WASM 런타임)를 걷어내고, RPG Maker MV의 원시 스프라이트시트
// 셀 애니메이션 포맷(mz_project/js/rmmz_sprites.js의 Sprite_AnimationMV를 그대로
// PixiJS 8로 이식)으로 대체한다. Effekseer와 달리 완전히 우리 PixiJS 스테이지
// 안에서 평범한 Sprite 트리로 그려지므로, 별도 WebGL 캔버스/컨텍스트 오버레이가
// 필요 없다(EffekseerOverlay가 씨름하던 GL 상태 공유 문제 자체가 사라진다).
import { Assets, Container, Rectangle, Sprite, Texture, type Application } from 'pixi.js'
import type { MvAnimation, MvAnimationCell, MvAnimationTiming } from '@/lib/battleApi'
import { playSe } from './audio'

// mz_project/js/rmmz_sprites.js Sprite_AnimationMV.updateCellSprite 기준 고정값.
const CELL_SIZE = 192
const CELLS_PER_ROW = 5
const MAX_CELL_SPRITES = 16
// setupRate()의 _rate=4(애니메이션 프레임 하나 = 게임 틱 4개, 60fps 기준 15fps).
const RATE = 4
const MS_PER_TICK = 1000 / 60

const BLEND_MODE_BY_CODE: Record<number, 'normal' | 'add' | 'multiply' | 'screen'> = {
  0: 'normal',
  1: 'add',
  2: 'multiply',
  3: 'screen',
}

function animationSheetUrl(name: string): string {
  return `/assets/img/animations/${encodeURIComponent(name)}.png`
}

async function loadSheet(name: string | null): Promise<Texture | null> {
  if (!name) return null
  try {
    return await Assets.load<Texture>(animationSheetUrl(name))
  } catch (err) {
    console.warn(`애니메이션 시트를 불러오지 못했습니다: ${name}`, err)
    return null
  }
}

// 같은 시트에서 자주 재사용되는 192x192 셀 텍스처를 캐싱한다(패턴 인덱스당 1개).
const cellTextureCache = new WeakMap<Texture, Map<number, Texture>>()
function cellTexture(sheet: Texture, patternInSheet: number): Texture {
  let cache = cellTextureCache.get(sheet)
  if (!cache) {
    cache = new Map()
    cellTextureCache.set(sheet, cache)
  }
  let texture = cache.get(patternInSheet)
  if (!texture) {
    const col = patternInSheet % CELLS_PER_ROW
    const row = Math.floor(patternInSheet / CELLS_PER_ROW)
    const frame = new Rectangle(col * CELL_SIZE, row * CELL_SIZE, CELL_SIZE, CELL_SIZE)
    texture = new Texture({ source: sheet.source, frame })
    cache.set(patternInSheet, texture)
  }
  return texture
}

export interface MvAnimationTarget {
  /** 애니메이션 원점의 화면 좌표(부모 스테이지 기준, Container.toGlobal() 결과). */
  x: number
  y: number
  /** position(0=머리/1=중앙)의 y 오프셋 계산에 쓰는 대상의 화면상 높이. */
  height: number
  /** 대상에게 화면 플래시가 아닌 타겟 플래시(timing.flashScope===1)를 걸 때 틴트를 입힐 대상. 없으면 무시. */
  applyFlash?: (color: [number, number, number, number]) => void
  clearFlash?: () => void
  hide?: () => void
  show?: () => void
}

/**
 * 배틀 하나당 인스턴스 하나 - start()에서 애니메이션 카탈로그(GET /api/battle-animations)를
 * 한 번 받아 id로 색인해두고, playAt()으로 재생한다. Effekseer와 달리 로드 실패해도
 * 재생만 조용히 건너뛰면 되므로 런타임 준비 단계가 따로 없다.
 */
export class MvAnimationPlayer {
  readonly container = new Container()

  private readonly app: Application
  private catalog = new Map<number, MvAnimation>()

  constructor(app: Application) {
    this.app = app
  }

  /** start()가 GET /api/battle-animations 응답을 받은 뒤 한 번 호출(배틀 시작 전엔 컨테이너만 존재). */
  setCatalog(list: MvAnimation[]): void {
    this.catalog = new Map(list.map((a) => [a.id, a]))
  }

  hasAnimation(animationId: number): boolean {
    return this.catalog.has(animationId)
  }

  /**
   * target 위치에서 animationId 애니메이션을 1회 재생하고 끝나면 스스로 정리한다.
   * 로드/카탈로그 미존재는 전투 진행에 영향 없이 조용히 무시(부가 연출이지 전투
   * 로직의 일부가 아님 - 기존 Effekseer 경로와 동일한 방침).
   */
  async playAt(animationId: number, target: MvAnimationTarget): Promise<void> {
    const anim = this.catalog.get(animationId)
    if (!anim) return

    const [sheet1, sheet2] = await Promise.all([loadSheet(anim.animation1_name), loadSheet(anim.animation2_name)])
    if (!sheet1 && !sheet2) return

    const group = new Container()
    if (anim.position === 3) {
      group.position.set(this.app.screen.width / 2, this.app.screen.height / 2)
    } else {
      const yOffset = anim.position === 0 ? -target.height : anim.position === 1 ? -target.height / 2 : 0
      group.position.set(target.x, target.y + yOffset)
    }
    this.container.addChild(group)

    const cellSprites: Sprite[] = []
    for (let i = 0; i < MAX_CELL_SPRITES; i++) {
      const sprite = new Sprite()
      sprite.anchor.set(0.5)
      sprite.visible = false
      cellSprites.push(sprite)
      group.addChild(sprite)
    }

    try {
      await this.runFrames(anim, cellSprites, sheet1, sheet2, target)
    } finally {
      target.clearFlash?.()
      this.container.removeChild(group)
      group.destroy({ children: true })
    }
  }

  private runFrames(
    anim: MvAnimation,
    cellSprites: Sprite[],
    sheet1: Texture | null,
    sheet2: Texture | null,
    target: MvAnimationTarget,
  ): Promise<void> {
    const totalFrames = anim.frames.length
    const msPerFrame = RATE * MS_PER_TICK
    const durationMs = totalFrames * msPerFrame
    const firedFrames = new Set<number>()

    return new Promise((resolve) => {
      let elapsed = 0
      const step = (): void => {
        elapsed += this.app.ticker.deltaMS
        const frameIndex = Math.min(totalFrames - 1, Math.floor(elapsed / msPerFrame))

        this.applyFrame(cellSprites, anim.frames[frameIndex] ?? [], sheet1, sheet2)

        if (!firedFrames.has(frameIndex)) {
          firedFrames.add(frameIndex)
          for (const timing of anim.timings) {
            if (timing.frame === frameIndex) this.processTiming(timing, target)
          }
        }

        if (elapsed >= durationMs) {
          this.app.ticker.remove(step)
          resolve()
        }
      }
      this.app.ticker.add(step)
    })
  }

  /** Sprite_AnimationMV.prototype.updateCellSprite를 그대로 이식. */
  private applyFrame(cellSprites: Sprite[], frame: MvAnimationCell[], sheet1: Texture | null, sheet2: Texture | null): void {
    for (let i = 0; i < cellSprites.length; i++) {
      const sprite = cellSprites[i]
      if (!sprite) continue

      const cell = frame[i]
      if (!cell || cell[0] < 0) {
        sprite.visible = false
        continue
      }

      const [pattern, x, y, scale, rotation, mirror, opacity, blendType] = cell
      const sheet = pattern < 100 ? sheet1 : sheet2
      if (!sheet) {
        sprite.visible = false
        continue
      }

      sprite.texture = cellTexture(sheet, pattern % 100)
      sprite.position.set(x, y)
      sprite.rotation = (rotation * Math.PI) / 180
      sprite.scale.set((scale / 100) * (mirror ? -1 : 1), scale / 100)
      sprite.alpha = Math.max(0, Math.min(1, opacity / 255))
      sprite.blendMode = BLEND_MODE_BY_CODE[blendType] ?? 'normal'
      sprite.visible = true
    }
  }

  /** Sprite_AnimationMV.prototype.processTimingData를 그대로 이식(플래시 감쇠 트윈 대신 즉시 적용 + setTimeout 해제). */
  private processTiming(timing: MvAnimationTiming, target: MvAnimationTarget): void {
    if (timing.se) playSe(timing.se)

    const durationMs = timing.flashDuration * RATE * MS_PER_TICK
    if (timing.flashScope === 1) {
      target.applyFlash?.(timing.flashColor)
      if (durationMs > 0) setTimeout(() => target.clearFlash?.(), durationMs)
    } else if (timing.flashScope === 3) {
      target.hide?.()
      if (durationMs > 0) setTimeout(() => target.show?.(), durationMs)
    }
    // flashScope===2(화면 전체 플래시)는 대상 하나에 종속된 연출이 아니라 화면 전역
    // 연출이라 이번 스코프에서는 뺐다(현재 재생 목록 중 실사용 빈도가 낮음).
  }
}
