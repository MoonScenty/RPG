import { Assets, Rectangle, Texture } from 'pixi.js'
import { FACE_SHEET_URLS, HUD_FACE_SHEET_URLS } from './assets'

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
