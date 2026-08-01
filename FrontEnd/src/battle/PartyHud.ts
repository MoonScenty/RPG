import { type Application, Assets, Container, Graphics, Rectangle, Sprite, Text, Texture } from 'pixi.js'
import { isUnitAlive, type BattleUnit } from '@/lib/battleApi'
import { ICON_SET_URL, PARTY_HUD_BACK_URL, PARTY_HUD_DOT_URL, PARTY_HUD_HP_URL, PARTY_HUD_MP_URL } from './assets'
import { predictCurrentActorId } from './atbPrediction'
import { STAGE_WIDTH } from './layout'
import { FONT_FAMILY } from './theme'
import { easeOutQuad, tween } from './tween'

// reference_resource/ui/party_hud_{back,hp,mp}.png 참고 - 반투명 카드 배경(back)에
// HP/MP 게이지가 이미 정해진 자리에 그려진 220x120 고정 캔버스 3장을 그대로 쓴다.
// 예전엔 bar_bg/bar_hp/bar_mp/bar_frame(210x32) 4장을 코드에서 HP 줄/MP 줄로
// 조립했지만, 이제 카드 하나가 이름+HP+MP 자리를 전부 포함한 완성된 레이아웃이라
// 그 위에 이름/수치 텍스트만 얹으면 된다.
const CARD_WIDTH = 220
const CARD_HEIGHT = 120
// 패널 전체 위치(사용자 실측 지시, 누적) - 오른쪽으로 5+5px(마진 축소), 위로 10+10px.
const PANEL_TOP = 20
const PANEL_RIGHT_MARGIN = 8
const CARD_GAP = 10
const ROW_PITCH = CARD_HEIGHT + CARD_GAP

// party_hud_hp.png/party_hud_mp.png 안에서 실제 게이지가 차지하는 영역(카드 캔버스
// 220x120 기준 픽셀 좌표) - PIL로 non-transparent 영역을 실측한 값. 그 외 영역은
// 전부 투명이라 back 위에 그대로 얹어도 되지만, 채워진 비율만큼만 보이게 하려면
// 이 사각형만 마스킹해서 좌측 끝을 고정한 채 가로 폭만 줄여야 한다.
const HP_BAR_RECT = { x: 32, y: 60, width: 169, height: 6 }
const MP_BAR_RECT = { x: 63, y: 90, width: 138, height: 6 }

const NAME_FONT_SIZE = 16
const LABEL_FONT_SIZE = 13
const VALUE_FONT_SIZE = 25
const LABEL_COLOR = 0xcbd5e1
const VALUE_COLOR = 0xffffff
const LABEL_ROW_GAP = 14 // 게이지 바로 위 라벨/수치 줄의 바닥선 ~ 게이지 상단 간격(1차 배치, 실측 조정 예정)
// HP/MP 라벨(수치 아님) 미세 위치 보정(사용자 실측 지시, 누적) - 아래로 14+1px, 오른쪽으로 2-1px.
const LABEL_OFFSET_X = 1
const LABEL_OFFSET_Y = 15
// HP/MP 수치(현재/최대) 미세 위치 보정(사용자 실측 지시, 누적) - 아래로 16+2px.
const VALUE_OFFSET_Y = 18
// 이름 미세 위치 보정(사용자 실측 지시, 누적) - 아래로 7px, 왼쪽으로 132-5px.
const NAME_OFFSET_X = -127
const NAME_OFFSET_Y = 7
// 자기 턴 여부에 따른 이름 색상(사용자 요청) - 턴이 아니면 회색, 자기 턴이면 흰색.
const NAME_COLOR_IDLE = 0x8a8f98
const NAME_COLOR_ACTIVE = 0xf1f5f9
// 자기 턴일 때 카드 전체가 왼쪽으로 튀어나오는 연출(사용자 실측 지시, 누적 10+10px) -
// 상태가 바뀔 때만 부드럽게 이동시킨다(animateCardX 참고).
const ACTIVE_CARD_SHIFT_X = -20
const CARD_MOVE_DURATION_MS = 220

// party_hud_back.png 안에 이미 그려진 TP 점 5개(빈 상태)의 중심 좌표(카드 캔버스
// 220x120 기준) - PIL로 각 점의 흰색 픽셀 클러스터 중심을 실측한 값.
const TP_DOT_CENTERS = [
  { x: 115.5, y: 23.5 },
  { x: 126.5, y: 23.5 },
  { x: 137.5, y: 23.5 },
  { x: 148.5, y: 23.5 },
  { x: 159.5, y: 23.5 },
]
// party_hud_dot.png(20x20 원본)를 얼마나 줄여서 그릴지 - 점 5개가 11px 간격으로
// 촘촘히 붙어 있어서 원본 크기 그대로면 서로 겹친다(1차 배치, 실측 조정 예정).
const TP_DOT_DISPLAY_SIZE = 9
// "Turn Point" 점 5개는 별도로 쌓이는 자원이 아니라 이미 있는 atb_gauge(0~100,
// 100 도달 시 그 유닛이 행동)를 5칸으로 환산해서 실시간으로 보여주는 것뿐이다 -
// 유닛 답변: "dot갯수 = round((ATB 게이지 / 100) * 5)". atb_gauge가 100을 살짝
// 넘는 순간이 있을 수 있어(효과 적용 후 즉시 차감 전) 5로 clamp한다.
const TP_DOTS_MAX = 5
const TP_LABEL_GAP = 4 // 점 5개 왼쪽 끝 ~ "TBP" 라벨 오른쪽 끝 간격

// 직업 아이콘(<PartyHudIcon> 노트태그, mz_classes -> BattleUnit.party_hud_icon) -
// IconSet.png는 32px 그리드 16열이라 인덱스 -> 픽셀 좌표 변환이 필요하다. 카드
// 우측 상단에 그린다.
const ICON_GRID = 32
const ICON_COLUMNS = 16
const CLASS_ICON_MARGIN = 8
const CLASS_ICON_SCALE = 1.2
// 아이콘 미세 위치 보정(사용자 실측 지시, 누적) - 오른쪽으로 5+5px, 위로 5+5px.
const CLASS_ICON_OFFSET_X = 10
const CLASS_ICON_OFFSET_Y = -10

interface Slot {
  card: Container
  cardLeft: number
  nameText: Text
  hpMask: Graphics
  hpValue: Text
  mpMask: Graphics
  mpValue: Text
  tpDots: Sprite[]
  isActive: boolean
  /** 진행 중인 카드 이동 애니메이션을 식별 - 상태가 빠르게 여러 번 바뀌면 이전
   * animateCardX() 호출이 뒤늦게 끝나면서 최신 목표 위치를 덮어쓰는 걸 막는다. */
  moveToken: number
}

interface BarResult {
  mask: Graphics
  valueText: Text
}

/** 우측 세로 아군 정보 패널: reference_resource/ui/party_hud_*.png 카드 3장 기반. */
export class PartyHud {
  readonly container = new Container()

  private readonly app: Application
  private slots = new Map<number, Slot>()

  constructor(app: Application, allies: BattleUnit[]) {
    this.app = app
    const cardLeft = STAGE_WIDTH - PANEL_RIGHT_MARGIN - CARD_WIDTH

    allies.forEach((unit, i) => {
      const top = PANEL_TOP + i * ROW_PITCH
      this.slots.set(unit.id, this.buildSlot(unit, cardLeft, top))
    })

    this.update(allies)
  }

  update(units: BattleUnit[]): void {
    // 다음 턴 예측(TurnOrderStrip과 동일한 ATB 시뮬레이션) - 지금 그 유닛 차례인지에
    // 따라 이름 색상/카드 위치를 바꾼다.
    const currentActorId = predictCurrentActorId(units.filter(isUnitAlive))

    for (const unit of units) {
      const slot = this.slots.get(unit.id)
      if (!slot) continue

      const hpRatio = unit.max_hp > 0 ? Math.max(0, unit.current_hp / unit.max_hp) : 0
      const mpRatio = unit.max_mp > 0 ? Math.max(0, unit.current_mp / unit.max_mp) : 0
      slot.hpMask.scale.x = hpRatio
      slot.mpMask.scale.x = mpRatio
      slot.hpValue.text = `${unit.current_hp}/${unit.max_hp}`
      slot.mpValue.text = `${unit.current_mp}/${unit.max_mp}`

      const litDots = Math.min(TP_DOTS_MAX, Math.round((unit.atb_gauge / 100) * TP_DOTS_MAX))
      slot.tpDots.forEach((dot, i) => {
        dot.visible = i < litDots
      })

      const isActive = unit.id === currentActorId
      slot.nameText.style.fill = isActive ? NAME_COLOR_ACTIVE : NAME_COLOR_IDLE
      if (isActive !== slot.isActive) {
        slot.isActive = isActive
        void this.animateCardX(slot, slot.cardLeft + (isActive ? ACTIVE_CARD_SHIFT_X : 0))
      }

      slot.card.alpha = isUnitAlive(unit) ? 1 : 0.35
    }
  }

  /** 자기 턴 진입/이탈 시 카드를 목표 x로 부드럽게 이동시킨다(사용자 요청). */
  private async animateCardX(slot: Slot, targetX: number): Promise<void> {
    const token = ++slot.moveToken
    const startX = slot.card.position.x
    if (startX === targetX) return

    await tween(this.app, CARD_MOVE_DURATION_MS, (progress) => {
      if (slot.moveToken !== token) return
      slot.card.position.x = startX + (targetX - startX) * easeOutQuad(progress)
    })
  }

  private buildSlot(unit: BattleUnit, left: number, top: number): Slot {
    const card = new Container()
    card.position.set(left, top)
    this.container.addChild(card)

    card.addChild(Sprite.from(PARTY_HUD_BACK_URL))

    const nameText = new Text({
      text: unit.name,
      style: {
        fill: NAME_COLOR_IDLE,
        fontSize: NAME_FONT_SIZE,
        fontFamily: FONT_FAMILY,
        stroke: { color: 0x1a1a1a, width: 2.5 },
      },
    })
    nameText.anchor.set(1, 0)
    nameText.position.set(CARD_WIDTH - 14 + NAME_OFFSET_X, 6 + NAME_OFFSET_Y)
    card.addChild(nameText)

    if (unit.party_hud_icon !== null) {
      const classIcon = new Sprite(this.classIconTexture(unit.party_hud_icon))
      classIcon.anchor.set(1, 0)
      classIcon.width = ICON_GRID * CLASS_ICON_SCALE
      classIcon.height = ICON_GRID * CLASS_ICON_SCALE
      classIcon.position.set(
        CARD_WIDTH - CLASS_ICON_MARGIN + CLASS_ICON_OFFSET_X,
        CLASS_ICON_MARGIN + CLASS_ICON_OFFSET_Y,
      )
      card.addChild(classIcon)
    }

    const hp = this.buildBar(card, PARTY_HUD_HP_URL, HP_BAR_RECT, 'HP')
    const mp = this.buildBar(card, PARTY_HUD_MP_URL, MP_BAR_RECT, 'MP')

    const tpLabel = new Text({
      text: 'TBP',
      style: {
        fill: LABEL_COLOR,
        fontSize: LABEL_FONT_SIZE,
        fontFamily: FONT_FAMILY,
        stroke: { color: 0x1a1a1a, width: 2 },
      },
    })
    tpLabel.anchor.set(1, 0.5)
    tpLabel.position.set(TP_DOT_CENTERS[0]!.x - TP_DOT_DISPLAY_SIZE / 2 - TP_LABEL_GAP, TP_DOT_CENTERS[0]!.y)
    card.addChild(tpLabel)

    const tpDots = TP_DOT_CENTERS.map((center) => {
      const dot = Sprite.from(PARTY_HUD_DOT_URL)
      dot.anchor.set(0.5)
      dot.width = TP_DOT_DISPLAY_SIZE
      dot.height = TP_DOT_DISPLAY_SIZE
      dot.position.set(center.x, center.y)
      dot.visible = false
      card.addChild(dot)
      return dot
    })

    return {
      card,
      cardLeft: left,
      nameText,
      hpMask: hp.mask,
      hpValue: hp.valueText,
      mpMask: mp.mask,
      mpValue: mp.valueText,
      tpDots,
      isActive: false,
      moveToken: 0,
    }
  }

  /** IconSet.png(16열 x 32px 그리드)에서 인덱스에 해당하는 한 칸만 잘라낸 텍스처. */
  private classIconTexture(iconIndex: number): Texture {
    const sheet = Assets.get<Texture>(ICON_SET_URL)
    const col = iconIndex % ICON_COLUMNS
    const row = Math.floor(iconIndex / ICON_COLUMNS)
    const frame = new Rectangle(col * ICON_GRID, row * ICON_GRID, ICON_GRID, ICON_GRID)

    return new Texture({ source: sheet.source, frame })
  }

  private buildBar(
    card: Container,
    fillUrl: string,
    rect: { x: number; y: number; width: number; height: number },
    label: string,
  ): BarResult {
    const labelText = new Text({
      text: label,
      style: {
        fill: LABEL_COLOR,
        fontSize: LABEL_FONT_SIZE,
        fontFamily: FONT_FAMILY,
        stroke: { color: 0x1a1a1a, width: 2 },
      },
    })
    labelText.anchor.set(0, 1)
    labelText.position.set(rect.x + LABEL_OFFSET_X, rect.y - LABEL_ROW_GAP + LABEL_OFFSET_Y)
    card.addChild(labelText)

    const valueText = new Text({
      text: '',
      style: {
        fill: VALUE_COLOR,
        fontSize: VALUE_FONT_SIZE,
        fontFamily: FONT_FAMILY,
        stroke: { color: 0x1a1a1a, width: 2.5 },
      },
    })
    valueText.anchor.set(1, 1)
    valueText.position.set(rect.x + rect.width, rect.y - LABEL_ROW_GAP + VALUE_OFFSET_Y)
    card.addChild(valueText)

    // 채움 이미지는 카드 전체(220x120) 크기 그대로 (0,0)에 얹고, 실제 게이지가
    // 그려진 rect만 마스킹한다. 마스크는 로컬 (0,0)에서 rect 크기로 그린 뒤
    // 컨테이너 위치를 rect.x/y로 옮겨서 로컬 원점을 rect 좌측 끝에 고정시킨다 -
    // 이렇게 해야 scale.x를 곱했을 때 좌측 끝은 그대로 두고 우측만 줄어든다
    // (반대로 rect를 원래 좌표에 그린 채로 scale하면 카드 원점(0,0) 기준으로
    // 줄어들어서 게이지 전체가 왼쪽으로 밀려버린다).
    const fill = Sprite.from(fillUrl)
    fill.position.set(0, 0)
    card.addChild(fill)

    const mask = new Graphics().rect(0, 0, rect.width, rect.height).fill(0xffffff)
    mask.position.set(rect.x, rect.y)
    card.addChild(mask)
    fill.mask = mask

    return { mask, valueText }
  }
}
