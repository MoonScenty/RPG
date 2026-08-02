import { Container, FillGradient, Graphics, Sprite, Text } from 'pixi.js'
import { isUnitAlive, type BattleUnit } from '@/lib/battleApi'
import { PARTY_HUD_BACK_URL, PARTY_HUD_HP_URL, PARTY_HUD_MP_URL } from './assets'
import { STAGE_HEIGHT } from './layout'
import { FONT_FAMILY } from './theme'

// ReferenceResource/party_hud(2026-08 재디자인) 기반 - back(360x120)에는 원래
// HP/MP/TG/%PERCENT/STATUS 글자가 전부 박혀 있었지만, 사용자가 base.png를 두
// 차례에 걸쳐 직접 수정해 게이지 트랙 두 줄만 남기고 글자를 전부 지웠다. 그
// 문구들은 그대로 유지하고 싶다고 해서(사용자 지시) 전부 코드에서 Text로 다시
// 그린다 - 위치/색상은 PIL로 예전 base.png의 글자 픽셀을 실측해서 맞춘 값.
// hp/mp 오버레이는 같은 캔버스 크기에 그 트랙 자리와 정확히 겹치는 색칠된
// 게이지만 그려져 있다. 이름/TBP 점/직업 아이콘은 이번 재디자인에서 뺐다(사용자 지시).

// 좌하단 2x2 그리드 배치 - ReferenceResource/party_hud/ref1.png 목업 배치를
// 픽셀로 실측해서 맞춘 값(카드 캔버스 자체는 위쪽에 내용이 몰려있고 아래쪽은
// 투명이라, ROW_PITCH가 카드 원본 높이(120)보다 훨씬 작아도 다음 줄과 안 겹친다).
const GRID_LEFT = 52
const COLUMN_PITCH = 410
const COLUMNS = 2
const ROW_PITCH = 71
// 화면 하단에 딱 붙어 보여서 사용자 지시로 전체를 10px 위로.
const GRID_TOP = STAGE_HEIGHT - ROW_PITCH * 2 - 18 - 10

// party_hud_hp.png/party_hud_mp.png 안에서 실제 게이지가 차지하는 영역(카드 캔버스
// 360x120 기준 픽셀 좌표) - PIL로 non-transparent 영역을 실측한 값.
const HP_BAR_RECT = { x: 177, y: 38, width: 116, height: 8 }
const MP_BAR_RECT = { x: 184, y: 51, width: 116, height: 8 }

const VALUE_FONT_SIZE = 12
const VALUE_COLOR = 0xffffff
// 바 끝 오른쪽에 현재 수치만 표시(최대치는 바 길이로 이미 보이고, 카드가 좁아
// "382/382" 전부 넣을 자리가 없다) - 바 우측 끝에서 이 만큼 띄운다.
const VALUE_GAP_X = 6

// "HP"/"MP" 라벨: 각 바 시작 x좌표 바로 왼쪽에 우측 정렬로 붙는다(실측한 여백 3px).
// 10 -> 11(사용자 지시로 1px 확대).
const LABEL_FONT_SIZE = 11
const LABEL_GAP_X = 3
const HP_LABEL_COLOR = 0x08eabc
const MP_LABEL_COLOR = 0x03cff6
const HP_LABEL_X = HP_BAR_RECT.x - LABEL_GAP_X
const HP_LABEL_Y = HP_BAR_RECT.y + HP_BAR_RECT.height / 2
const MP_LABEL_X = MP_BAR_RECT.x - LABEL_GAP_X
const MP_LABEL_Y = MP_BAR_RECT.y + MP_BAR_RECT.height / 2

// HP/MP 수치 텍스트를 TG 수치(atbValue)와 동일한 크기/테두리로 맞추고(사용자 지시,
// ATB_PERCENT_FONT_SIZE 재사용), 그라디언트만 각자 색으로: 상단은 공통 흰색, 하단은
// HP=틸그린(#01d0a2)/MP=네이비(#053f7c). 정렬: HP 좌측/MP·TG 우측(사용자 지시).
const BIG_VALUE_GRADIENT_TOP = '#ffffff'
const HP_VALUE_GRADIENT_BOTTOM = '#01d0a2'
const MP_VALUE_GRADIENT_BOTTOM = '#053f7c'

// 예전 base.png에 박혀 있던 "TG"(x178~189)와 "%PERCENT"(x235~291) 라벨 위치를
// PIL로 실측한 값 - 지금은 base.png에서 지워져서 코드에서 Text로 다시 그린다.
const TG_LABEL_CENTER_X = (177 + 189) / 2
const PERCENT_LABEL_CENTER_X = (235 + 291) / 2
const TG_PERCENT_LABEL_Y = 67
const PINK_LABEL_COLOR = 0xed74b0

// TG와 %PERCENT 사이 빈 칸 정중앙에 진짜 ATB 게이지(0~100+, atb_gauge)를
// 퍼센트로 표기한다 - TG("턴 게이지") 라벨의 실제 값 자리.
// 사용자 지시로 오른쪽 10px씩 두 차례 이동(총 20px).
const ATB_PERCENT_CENTER_X = (189 + 235) / 2 + 20
// 기준 67에서 사용자 지시로 2px 위로.
const ATB_PERCENT_CENTER_Y = 65
// 13 -> 16 -> 19 -> 22 -> 24 -> 22(사용자 지시로 최종 축소).
const ATB_PERCENT_FONT_SIZE = 22
// 사용자 지시: 폰트 내부 그라데이션(위쪽 흰색 -> 아래쪽 핑크), 테두리는 검정 50% 불투명.
const ATB_PERCENT_GRADIENT_TOP = '#ffffff'
const ATB_PERCENT_GRADIENT_BOTTOM = '#ed74b0'

// base.png 우하단 각진 뱃지에 있던 장식용 "STATUS" 글자가 지워져서(사용자가 직접
// 에셋 수정) 같은 문구를 코드에서 Text로 다시 그린다(사용자 지시, 이름 아님 - 그냥
// "STATUS" 고정 텍스트) - PIL로 원래 STATUS 텍스트 위치를 실측해 중심을 잡음.
// 313 -> 318(사용자 지시로 오른쪽 5px 이동).
const STATUS_LABEL_CENTER_X = 318
const STATUS_LABEL_CENTER_Y = 83
// 10 -> 11(사용자 지시로 1px 확대).
const STATUS_LABEL_FONT_SIZE = 11
const STATUS_LABEL_COLOR = 0xb8b0a0

interface Slot {
  card: Container
  hpMask: Graphics
  hpValue: Text
  mpMask: Graphics
  mpValue: Text
  atbValue: Text
}

interface BarResult {
  mask: Graphics
  valueText: Text
}

/** 좌하단 아군 정보 패널(2x2): ReferenceResource/party_hud 카드 3장 기반. */
export class PartyHud {
  readonly container = new Container()

  private slots = new Map<number, Slot>()

  constructor(allies: BattleUnit[]) {
    allies.forEach((unit, i) => {
      const col = i % COLUMNS
      const row = Math.floor(i / COLUMNS)
      const left = GRID_LEFT + col * COLUMN_PITCH
      const top = GRID_TOP + row * ROW_PITCH
      this.slots.set(unit.id, this.buildSlot(unit, left, top))
    })

    this.update(allies)
  }

  update(units: BattleUnit[]): void {
    for (const unit of units) {
      const slot = this.slots.get(unit.id)
      if (!slot) continue

      const hpRatio = unit.max_hp > 0 ? Math.max(0, unit.current_hp / unit.max_hp) : 0
      const mpRatio = unit.max_mp > 0 ? Math.max(0, unit.current_mp / unit.max_mp) : 0
      slot.hpMask.scale.x = hpRatio
      slot.mpMask.scale.x = mpRatio
      slot.hpValue.text = `${unit.current_hp}`
      slot.mpValue.text = `${unit.current_mp}`
      slot.atbValue.text = `${Math.round(Math.min(100, Math.max(0, unit.atb_gauge)))}`

      slot.card.alpha = isUnitAlive(unit) ? 1 : 0.35
    }
  }

  private buildSlot(unit: BattleUnit, left: number, top: number): Slot {
    const card = new Container()
    card.position.set(left, top)
    this.container.addChild(card)

    card.addChild(Sprite.from(PARTY_HUD_BACK_URL))

    const hp = this.buildBar(card, PARTY_HUD_HP_URL, HP_BAR_RECT)
    const mp = this.buildBar(card, PARTY_HUD_MP_URL, MP_BAR_RECT)

    // 위치는 그대로 두고 스타일(크기/그라디언트/테두리)과 정렬만 덮어씀(사용자 지시).
    this.applyBigValueStyle(hp.valueText, HP_VALUE_GRADIENT_BOTTOM, 0)
    this.applyBigValueStyle(mp.valueText, MP_VALUE_GRADIENT_BOTTOM, 1)
    // 사용자 지시로 왼쪽 10px 이동.
    mp.valueText.position.x -= 10

    const atbGradient = new FillGradient(0, 0, 0, 1)
    atbGradient.addColorStop(0, ATB_PERCENT_GRADIENT_TOP)
    atbGradient.addColorStop(1, ATB_PERCENT_GRADIENT_BOTTOM)

    const atbValue = new Text({
      text: '',
      style: {
        fill: atbGradient,
        fontSize: ATB_PERCENT_FONT_SIZE,
        fontFamily: FONT_FAMILY,
        stroke: { color: 0x000000, width: 2, alpha: 0.5 },
      },
    })
    // 중앙 -> 우측 정렬(사용자 지시).
    atbValue.anchor.set(1, 0.5)
    atbValue.position.set(ATB_PERCENT_CENTER_X, ATB_PERCENT_CENTER_Y)
    card.addChild(atbValue)

    this.buildStaticLabel(card, 'STATUS', STATUS_LABEL_CENTER_X, STATUS_LABEL_CENTER_Y, STATUS_LABEL_FONT_SIZE, STATUS_LABEL_COLOR)
    this.buildStaticLabel(card, 'HP', HP_LABEL_X, HP_LABEL_Y, LABEL_FONT_SIZE, HP_LABEL_COLOR, 1)
    this.buildStaticLabel(card, 'MP', MP_LABEL_X, MP_LABEL_Y, LABEL_FONT_SIZE, MP_LABEL_COLOR, 1)
    this.buildStaticLabel(card, 'TG', TG_LABEL_CENTER_X, TG_PERCENT_LABEL_Y, LABEL_FONT_SIZE, PINK_LABEL_COLOR)
    this.buildStaticLabel(card, '%PERCENT', PERCENT_LABEL_CENTER_X, TG_PERCENT_LABEL_Y, LABEL_FONT_SIZE, PINK_LABEL_COLOR)

    return {
      card,
      hpMask: hp.mask,
      hpValue: hp.valueText,
      mpMask: mp.mask,
      mpValue: mp.valueText,
      atbValue,
    }
  }

  /** HP/MP 수치를 TG 수치(atbValue)와 같은 큰 그라디언트 스타일로 바꾼다(사용자 지시). 위치는 그대로 두고 스타일+정렬만 덮어씀. */
  private applyBigValueStyle(text: Text, gradientBottom: string, anchorX: number): void {
    const gradient = new FillGradient(0, 0, 0, 1)
    gradient.addColorStop(0, BIG_VALUE_GRADIENT_TOP)
    gradient.addColorStop(1, gradientBottom)
    text.style = {
      fill: gradient,
      fontSize: ATB_PERCENT_FONT_SIZE,
      fontFamily: FONT_FAMILY,
      stroke: { color: 0x000000, width: 2, alpha: 0.5 },
    }
    text.anchor.set(anchorX, 0.5)
  }

  /** base.png에서 지워진 고정 문구(STATUS/HP/MP/TG/%PERCENT)를 같은 자리에 Text로 그린다. anchorX=1이면 x를 우측 정렬 기준으로 쓴다. */
  private buildStaticLabel(
    card: Container,
    text: string,
    x: number,
    y: number,
    fontSize: number,
    color: number,
    anchorX = 0.5,
  ): Text {
    const label = new Text({
      text,
      style: {
        fill: color,
        fontSize,
        fontFamily: FONT_FAMILY,
        align: 'center',
        stroke: { color: 0x000000, width: 2, alpha: 0.6 },
      },
    })
    label.anchor.set(anchorX, 0.5)
    label.position.set(x, y)
    card.addChild(label)
    return label
  }

  private buildBar(
    card: Container,
    fillUrl: string,
    rect: { x: number; y: number; width: number; height: number },
  ): BarResult {
    // 채움 이미지는 카드 전체(360x120) 크기 그대로 (0,0)에 얹고, 실제 게이지가
    // 그려진 rect만 마스킹한다. 마스크는 로컬 (0,0)에서 rect 크기로 그린 뒤
    // 컨테이너 위치를 rect.x/y로 옮겨서 로컬 원점을 rect 좌측 끝에 고정시킨다 -
    // 이렇게 해야 scale.x를 곱했을 때 좌측 끝은 그대로 두고 우측만 줄어든다.
    const fill = Sprite.from(fillUrl)
    fill.position.set(0, 0)
    card.addChild(fill)

    const mask = new Graphics().rect(0, 0, rect.width, rect.height).fill(0xffffff)
    mask.position.set(rect.x, rect.y)
    card.addChild(mask)
    fill.mask = mask

    const valueText = new Text({
      text: '',
      style: {
        fill: VALUE_COLOR,
        fontSize: VALUE_FONT_SIZE,
        fontFamily: FONT_FAMILY,
        stroke: { color: 0x1a1a1a, width: 2 },
      },
    })
    valueText.anchor.set(0, 0.5)
    valueText.position.set(rect.x + rect.width + VALUE_GAP_X, rect.y + rect.height / 2)
    card.addChild(valueText)

    return { mask, valueText }
  }
}
