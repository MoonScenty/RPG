// mz_project/img/system/IconSet.png(32px 그리드, 16열)에서 mz_items/mz_weapons/
// mz_armors의 icon_index 정수값으로 크롭 좌표를 뽑는 헬퍼. HomeView.vue의 메뉴/
// 화폐 아이콘은 좌표를 직접 골라 쓰지만(iconX/iconY), 여기는 DB의 icon_index를
// 그대로 계산해야 해서 별도로 뺐다. backgroundImage는 CSS에서 지정하고(스캐일링
// 시 이미지 재요청 방지), 이 함수는 크기/backgroundPosition/backgroundSize만 준다.
const ICON_SHEET_SIZE = { width: 512, height: 5344 }
const ICON_GRID = 32
const ICON_COLUMNS = ICON_SHEET_SIZE.width / ICON_GRID

export function mzIconStyle(iconIndex: number, size = 32) {
  const scale = size / ICON_GRID
  const col = iconIndex % ICON_COLUMNS
  const row = Math.floor(iconIndex / ICON_COLUMNS)
  return {
    width: `${size}px`,
    height: `${size}px`,
    backgroundPosition: `-${col * ICON_GRID * scale}px -${row * ICON_GRID * scale}px`,
    backgroundSize: `${ICON_SHEET_SIZE.width * scale}px ${ICON_SHEET_SIZE.height * scale}px`,
  }
}
