import type { Application } from 'pixi.js'

/** durationMs 동안 매 프레임 onUpdate(progress)를 호출한 뒤 resolve. */
export function tween(
  app: Application,
  durationMs: number,
  onUpdate: (progress: number) => void,
): Promise<void> {
  return new Promise((resolve) => {
    let elapsed = 0
    const step = (): void => {
      elapsed += app.ticker.deltaMS
      const progress = Math.min(1, elapsed / durationMs)
      onUpdate(progress)
      if (progress >= 1) {
        app.ticker.remove(step)
        resolve()
      }
    }
    app.ticker.add(step)
  })
}

export function easeOutQuad(t: number): number {
  return 1 - (1 - t) * (1 - t)
}
