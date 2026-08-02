import { Assets, Rectangle, Texture } from 'pixi.js'
import { enemyFaceUrl, FACE_SHEET_URLS, HUD_FACE_SHEET_URLS } from './assets'

interface FaceSheetLayout {
  urls: Record<string, string>
  cols: number
  cellWidth: number
  cellHeight: number
}

// FrontEnd/src/lib/portrait.ts(DOM용 CSS 크롭)와 동일한 규격 - 4열x2행, 셀 144x144(정사각형).
const FACE_SHEET: FaceSheetLayout = { urls: FACE_SHEET_URLS, cols: 4, cellWidth: 144, cellHeight: 144 }

// RPGProject/img/hud_faces 원본이 288x96(가로로 긴 상반부 크롭)이라 정사각형이
// 아니다 - 4열x2행, 셀 288x96 그대로(축소 없음).
const HUD_FACE_SHEET: FaceSheetLayout = { urls: HUD_FACE_SHEET_URLS, cols: 4, cellWidth: 288, cellHeight: 96 }

/** sprite("Actor1:0" 형식, 얼굴시트 파일명:faceIndex)를 layout에서 찾아 해당 칸만 크롭한다. */
function cropFaceTexture(layout: FaceSheetLayout, sprite: string): Texture | undefined {
  const match = /^([A-Za-z0-9_]+):(\d+)$/.exec(sprite)
  if (!match) return undefined

  const [, sheet, indexStr] = match
  if (!sheet || !indexStr) return undefined
  const url = layout.urls[sheet]
  if (!url) return undefined

  const index = Number(indexStr)
  const col = index % layout.cols
  const row = Math.floor(index / layout.cols)

  const sheetTexture = Assets.get<Texture>(url)
  return new Texture({
    source: sheetTexture.source,
    frame: new Rectangle(col * layout.cellWidth, row * layout.cellHeight, layout.cellWidth, layout.cellHeight),
  })
}

/** sprite는 "Actor1:0" 형식(얼굴시트 파일명:faceIndex) - 없으면 undefined(적 등 얼굴시트가 없는 유닛). */
export function faceTexture(sprite: string): Texture | undefined {
  return cropFaceTexture(FACE_SHEET, sprite)
}

/** hud_sprite는 "Actor1:0" 형식(파티 HUD 전용 얼굴시트:faceIndex) - 없으면 undefined. */
export function hudFaceTexture(sprite: string): Texture | undefined {
  return cropFaceTexture(HUD_FACE_SHEET, sprite)
}

// 적 얼굴(enemyFaceUrl)은 preloadBattleAssets()에 등록 안 하고 즉석에서 로드한다 -
// Texture.from(url)은 아직 캐시에 없는 URL 문자열을 넘기면 실제로 fetch를
// 트리거하지 않고 빈 텍스처만 반환해서(실서버에서 확인 - 육각형은 나오는데
// 얼굴 이미지는 영영 안 뜸) 절대 안 뜬다. Assets.load()로 직접 불러와서 캐시에
// 채워야 한다 - 로드 중엔 undefined를 반환하고(그 프레임은 얼굴 없이 진영색
// 육각형만 보임), 로드가 끝나면 이후 update() 호출(매 프레임 돈다) 때 자동으로
// 채워진 텍스처를 돌려주게 된다.
const enemyFaceTextureCache = new Map<string, Texture>()
const enemyFaceLoadInFlight = new Set<string>()

/** enemyFace는 img/faces 파일명(시트 아님, 독립 이미지 한 장) - 아직 로드 전이면 undefined. */
export function enemyFaceTexture(name: string): Texture | undefined {
  const cached = enemyFaceTextureCache.get(name)
  if (cached) return cached

  if (!enemyFaceLoadInFlight.has(name)) {
    enemyFaceLoadInFlight.add(name)
    void Assets.load<Texture>(enemyFaceUrl(name))
      .then((texture) => {
        enemyFaceTextureCache.set(name, texture)
      })
      .finally(() => {
        enemyFaceLoadInFlight.delete(name)
      })
  }

  return undefined
}
