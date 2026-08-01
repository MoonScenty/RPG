import { Assets, type Texture } from 'pixi.js'

// mz_project/img/circle/*.png를 그대로 복사한 정적 서빙 - 스킬의 <CircleImage: 배율, 이름>
// 노트태그가 이름만으로 참조하므로(effekseer/effects/dragonbones와 동일한 컨벤션),
// 이름->URL 규칙만 고정해두고 파일을 늘리는 건 코드 변경 없이 그대로 된다.
const CIRCLE_BASE_URL = '/assets/circle/'

const cache = new Map<string, Promise<Texture>>()

/** 같은 이름은 한 번만 로드해서 캐싱 - 캐스팅 스킬이 여러 번 쓰여도 재요청하지 않는다. */
export function loadCircleImage(name: string): Promise<Texture> {
  let pending = cache.get(name)
  if (!pending) {
    pending = Assets.load(`${CIRCLE_BASE_URL}${name}.png`)
    cache.set(name, pending)
  }

  return pending
}
