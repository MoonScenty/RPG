import { Application, Container } from 'pixi.js'
import { preloadBattleAssets } from './assets'
import { BattleScene } from './BattleScene'
import { preloadCharacterAnimations } from './characters'
import { STAGE_HEIGHT, STAGE_WIDTH } from './layout'

let assetsReady: Promise<void> | null = null

function ensureAssetsLoaded(): Promise<void> {
  assetsReady ??= Promise.all([preloadBattleAssets(), preloadCharacterAnimations()]).then(() => undefined)
  return assetsReady
}

/**
 * container에 새 PixiJS Application을 마운트하고 전투를 시작한다.
 * onExit이 불리기 전에 Application을 완전히 정리하므로, 호출부는 바로
 * container를 다른 화면으로 교체해도 된다.
 *
 * 반환하는 함수는 "결과 화면의 메인으로 버튼을 누르기 전에" 호출부(Vue
 * 컴포넌트)가 먼저 사라지는 경우(예: 브라우저 뒤로가기)를 위한 강제 정리용
 * - onExit과 중복 실행되지 않도록 내부에서 한 번만 실행되게 막는다.
 */
export async function mountBattle(container: HTMLElement, onExit: () => void): Promise<() => void> {
  await ensureAssetsLoaded()

  const app = new Application()
  await app.init({
    width: STAGE_WIDTH,
    height: STAGE_HEIGHT,
    background: '#0a0a0a',
    antialias: true,
    resolution: window.devicePixelRatio || 1,
    autoDensity: true,
  })

  container.innerHTML = ''
  container.appendChild(app.canvas)

  const stageRoot = new Container()
  app.stage.addChild(stageRoot)

  function fitToContainer(): void {
    const width = container.clientWidth || window.innerWidth
    const height = container.clientHeight || window.innerHeight
    const scale = Math.min(width / STAGE_WIDTH, height / STAGE_HEIGHT)
    app.renderer.resize(width, height)
    stageRoot.scale.set(scale)
    stageRoot.position.set((width - STAGE_WIDTH * scale) / 2, (height - STAGE_HEIGHT * scale) / 2)
  }
  window.addEventListener('resize', fitToContainer)
  fitToContainer()

  let torndown = false
  function teardown(): void {
    if (torndown) return
    torndown = true
    window.removeEventListener('resize', fitToContainer)
    scene.destroy() // 렌더러가 죽기 전에 턴 루프부터 멈춘다
    app.destroy(true)
  }

  const scene = new BattleScene(app, () => {
    teardown()
    onExit()
  })
  stageRoot.addChild(scene.root)

  void scene.start()

  return teardown
}
