// mz_project가 실제로 쓰는 Effekseer 런타임(js/libs/effekseer.min.js + .wasm)을
// 그대로 가져와서(FrontEnd/public/assets/effekseer/) .efkefc 파티클 이펙트를 재생한다.
// RPG Maker MZ 코어(mz_project/js/rmmz_sprites.js의 Sprite_Animation, mz_project/js/
// rmmz_core.js의 Graphics._createEffekseerContext)가 하는 걸 그대로 따라가되, MZ는
// Effekseer를 PixiJS와 같은 WebGL 컨텍스트/캔버스에 얹어서 그리는 반면(Pixi 5 기준
// renderer.gl에 직접 개입 - batch/geometry/texture/state/shader/framebuffer를 매 프레임
// reset), 우리 Pixi는 8이라 그 내부 API가 다 달라졌다. 그래서 여기서는 완전히 별도의
// <canvas>(자기만의 WebGL 컨텍스트)를 Pixi 캔버스 위에 투명 오버레이로 얹는 방식을
// 쓴다 - Pixi 렌더러 내부를 전혀 안 건드리니 버전 차이에 영향받지 않는다.
//
// 좌표계: 오버레이 캔버스는 CSS 픽셀 크기 그대로(디바이스 픽셀 배율 미적용)로
// 내부 해상도를 맞춘다 - PixiJS Container.toGlobal()이 반환하는 스크린 좌표(CSS 픽셀
// 기준)를 변환 없이 그대로 뷰포트 좌표로 쓰기 위함(HiDPI 화면에서 이펙트가 살짝
// 부드럽게 보일 수 있지만, 좌표 변환 버그 위험을 없애는 쪽을 택함).

const RUNTIME_JS_URL = '/assets/effekseer/effekseer.min.js'
const RUNTIME_WASM_URL = '/assets/effekseer/effekseer.wasm'
const EFFECTS_BASE_URL = '/assets/effects/'

// MZ 기본값(Sprite_Animation.prototype.initMembers의 _viewportSize) 그대로 - 이펙트
// 하나가 그려지는 정사각 뷰포트 한 변 길이(px, 이펙트 로컬 좌표계 스케일과 얽혀 있어
// 임의로 바꾸면 이펙트가 원본보다 커지거나 작아 보인다).
const VIEWPORT_SIZE = 4096

interface EffekseerEffect {
  isLoaded: boolean
}

interface EffekseerHandle {
  exists: boolean
  setLocation(x: number, y: number, z: number): void
  setRotation(x: number, y: number, z: number): void
  setScale(x: number, y: number, z: number): void
  setSpeed(speed: number): void
  stop(): void
}

interface EffekseerContext {
  init(gl: WebGLRenderingContext | WebGL2RenderingContext): void
  setRestorationOfStatesFlag(flag: boolean): void
  loadEffect(
    url: string,
    scale: number,
    onLoad: () => void,
    onError: (message?: string) => void,
  ): EffekseerEffect
  releaseEffect(effect: EffekseerEffect): void
  play(effect: EffekseerEffect): EffekseerHandle
  setProjectionMatrix(matrix: number[]): void
  setCameraMatrix(matrix: number[]): void
  beginDraw(): void
  drawHandle(handle: EffekseerHandle): void
  endDraw(): void
  update(): void
}

interface EffekseerGlobal {
  createContext(): EffekseerContext
  initRuntime(wasmUrl: string, onLoad: () => void, onError: (message?: string) => void): void
}

declare global {
  interface Window {
    effekseer?: EffekseerGlobal
  }
}

function loadScript(src: string): Promise<void> {
  return new Promise((resolve, reject) => {
    const el = document.createElement('script')
    el.src = src
    el.onload = () => resolve()
    el.onerror = () => reject(new Error(`스크립트를 불러오지 못했습니다: ${src}`))
    document.head.appendChild(el)
  })
}

let runtimeReady: Promise<void> | null = null

/** effekseer.min.js(전역 window.effekseer 노출) + .wasm 런타임을 한 번만 로드. */
export function ensureEffekseerRuntime(): Promise<void> {
  runtimeReady ??= (async () => {
    if (!window.effekseer) {
      await loadScript(RUNTIME_JS_URL)
    }
    const effekseer = window.effekseer
    if (!effekseer) {
      throw new Error('effekseer.min.js가 window.effekseer를 노출하지 않았습니다')
    }
    await new Promise<void>((resolve, reject) => {
      effekseer.initRuntime(RUNTIME_WASM_URL, resolve, () => reject(new Error('Effekseer WASM 로드 실패')))
    })
  })()
  return runtimeReady
}

interface ActiveEffect {
  handle: EffekseerHandle
  x: number
  y: number
}

/**
 * Pixi 캔버스 위에 겹쳐진 투명 오버레이 캔버스 하나 + Effekseer 컨텍스트 하나.
 * 배틀 하나당 인스턴스 하나(pixiApp.ts의 mountBattle()이 만들고 destroy 시 dispose()).
 */
export class EffekseerOverlay {
  readonly canvas: HTMLCanvasElement

  private readonly gl: WebGLRenderingContext | WebGL2RenderingContext
  private readonly context: EffekseerContext
  private readonly effectCache = new Map<string, EffekseerEffect>()
  private active: ActiveEffect[] = []
  private width = 0
  private height = 0
  private rafId = 0
  private disposed = false

  constructor(host: HTMLElement) {
    const effekseer = window.effekseer
    if (!effekseer) {
      throw new Error('ensureEffekseerRuntime()을 먼저 호출해야 합니다')
    }

    this.canvas = document.createElement('canvas')
    this.canvas.style.position = 'absolute'
    this.canvas.style.inset = '0'
    this.canvas.style.pointerEvents = 'none'
    this.canvas.style.zIndex = '1'
    host.appendChild(this.canvas)

    const gl =
      this.canvas.getContext('webgl2', { alpha: true }) ?? this.canvas.getContext('webgl', { alpha: true })
    if (!gl) {
      this.canvas.remove()
      throw new Error('WebGL 컨텍스트를 생성하지 못했습니다')
    }
    this.gl = gl

    this.context = effekseer.createContext()
    this.context.init(this.gl)
    this.context.setRestorationOfStatesFlag(false)

    this.renderLoop()
  }

  /** CSS 픽셀 기준 - Pixi 캔버스와 항상 같은 크기로 맞춰 호출(pixiApp.ts의 fitToContainer 참고). */
  resize(width: number, height: number): void {
    this.width = width
    this.height = height
    this.canvas.width = width
    this.canvas.height = height
  }

  /** 같은 이펙트를 여러 번 재생해도 파일은 한 번만 로드(이 오버레이 인스턴스 생존 동안 캐시). */
  async loadEffect(name: string): Promise<EffekseerEffect> {
    const cached = this.effectCache.get(name)
    if (cached) return cached

    const url = `${EFFECTS_BASE_URL}${encodeURIComponent(name)}.efkefc`
    const effect = await new Promise<EffekseerEffect>((resolve, reject) => {
      const loaded = this.context.loadEffect(
        url,
        1,
        () => resolve(loaded),
        () => reject(new Error(`이펙트를 불러오지 못했습니다: ${name}`)),
      )
    })
    this.effectCache.set(name, effect)
    return effect
  }

  /**
   * x/y는 오버레이 캔버스 기준 CSS 픽셀 좌표(보통 PixiJS Container.toGlobal() 결과를
   * 그대로 씀). scale은 mz_animations.scale/100(MZ 원본이 퍼센트 단위라 - rmmz_sprites.js
   * Sprite_Animation.updateEffectGeometry 참고) - 안 주면 원본 크기(1)로 재생.
   */
  playAt(effect: EffekseerEffect, x: number, y: number, scale = 1): void {
    const handle = this.context.play(effect)
    handle.setScale(scale, scale, scale)
    this.active.push({ handle, x, y })
  }

  dispose(): void {
    if (this.disposed) return
    this.disposed = true
    cancelAnimationFrame(this.rafId)
    for (const a of this.active) a.handle.stop()
    this.active = []
    for (const effect of this.effectCache.values()) this.context.releaseEffect(effect)
    this.effectCache.clear()
    this.canvas.remove()
  }

  /**
   * mz_project/js/rmmz_sprites.js의 Sprite_Animation.setProjectionMatrix를 그대로
   * 옮김(미러링만 뺌) - p가 캔버스 높이에 반비례해야 이펙트 크기가 화면 크기와
   * 무관하게 일정하게 보인다.
   */
  private setProjectionMatrix(): void {
    const p = -(VIEWPORT_SIZE / this.height)
    // prettier-ignore
    this.context.setProjectionMatrix([
      1, 0, 0, 0,
      0, -1, 0, 0,
      0, 0, 1, p,
      0, 0, 0, 1,
    ])
  }

  /** 위 파일의 setCameraMatrix와 동일(고정값). */
  private setCameraMatrix(): void {
    // prettier-ignore
    this.context.setCameraMatrix([
      1, 0, 0, 0,
      0, 1, 0, 0,
      0, 0, 1, 0,
      0, 0, -10, 1,
    ])
  }

  private renderLoop = (): void => {
    if (this.disposed) return
    this.rafId = requestAnimationFrame(this.renderLoop)
    if (this.width === 0 || this.height === 0) return

    this.context.update()

    this.gl.viewport(0, 0, this.width, this.height)
    this.gl.clearColor(0, 0, 0, 0)
    this.gl.clear(this.gl.COLOR_BUFFER_BIT | this.gl.DEPTH_BUFFER_BIT)

    this.active = this.active.filter((a) => a.handle.exists)
    for (const a of this.active) {
      this.setProjectionMatrix()
      this.setCameraMatrix()
      const vw = VIEWPORT_SIZE
      const vh = VIEWPORT_SIZE
      // WebGL 뷰포트는 좌하단이 원점이라, 위가 원점인 화면 좌표(a.y)를 뒤집어야 한다.
      const vx = a.x - vw / 2
      const vy = this.height - a.y - vh / 2
      this.gl.viewport(vx, vy, vw, vh)
      this.context.beginDraw()
      this.context.drawHandle(a.handle)
      this.context.endDraw()
    }
    this.gl.viewport(0, 0, this.width, this.height)
  }
}
