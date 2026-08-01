import { Assets, Rectangle, Texture } from 'pixi.js'
import { FACE_SHEET_URLS } from './assets'

// FrontEnd/src/lib/portrait.ts(DOM용 CSS 크롭)와 동일한 규격 - 4열x2행, 셀 144x144.
const FACE_SHEET = { cols: 4, cell: 144 }

/** sprite는 "Actor1:0" 형식(얼굴시트 파일명:faceIndex) - 없으면 undefined(적 등 얼굴시트가 없는 유닛). */
export function faceTexture(sprite: string): Texture | undefined {
  const match = /^([A-Za-z0-9_]+):(\d+)$/.exec(sprite)
  if (!match) return undefined

  const [, sheet, indexStr] = match
  if (!sheet || !indexStr) return undefined
  const url = FACE_SHEET_URLS[sheet]
  if (!url) return undefined

  const index = Number(indexStr)
  const col = index % FACE_SHEET.cols
  const row = Math.floor(index / FACE_SHEET.cols)

  const sheetTexture = Assets.get<Texture>(url)
  return new Texture({
    source: sheetTexture.source,
    frame: new Rectangle(col * FACE_SHEET.cell, row * FACE_SHEET.cell, FACE_SHEET.cell, FACE_SHEET.cell),
  })
}
