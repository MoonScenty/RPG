// MZ 얼굴 시트 공통 규격: 4열 x 2행, 셀 144x144 (576x288 전체).
const FACE_SHEET = { width: 576, height: 288, cols: 4, cell: 144 }

/** sprite는 "Actor1:0" 형식(파일명:faceIndex). frontend/public/assets/portraits/{파일명}.png를 크롭한다. */
export function portraitStyle(sprite: string | undefined, size: number) {
  const match = sprite ? /^([A-Za-z0-9_]+):(\d+)$/.exec(sprite) : null
  if (!match) return { width: `${size}px`, height: `${size}px` }

  const [, sheet, indexStr] = match
  const index = Number(indexStr)
  const col = index % FACE_SHEET.cols
  const row = Math.floor(index / FACE_SHEET.cols)
  const scale = size / FACE_SHEET.cell

  return {
    backgroundImage: `url(/assets/portraits/${sheet}.png)`,
    backgroundPosition: `-${col * FACE_SHEET.cell * scale}px -${row * FACE_SHEET.cell * scale}px`,
    backgroundSize: `${FACE_SHEET.width * scale}px ${FACE_SHEET.height * scale}px`,
    backgroundRepeat: 'no-repeat',
    width: `${size}px`,
    height: `${size}px`,
    imageRendering: 'pixelated' as const,
  }
}
