import { Container, FillGradient, Graphics, Sprite, Text } from 'pixi.js'
import { isUnitAlive, type BattleUnit } from '@/lib/battleApi'
import { PARTY_HUD_BACK_URL, PARTY_HUD_HP_URL, PARTY_HUD_MP_URL } from './assets'
import { STAGE_HEIGHT } from './layout'
import { FONT_FAMILY } from './theme'

// ReferenceResource/party_hud(2026-08 재디자인) 기반 - back(360x120)에 HP/MP
// 라벨+게이지 트랙과 장식 텍스트(SG/%PERCENT/STATUS, 기능 없음 - 사용자 확인)가
// 이미 그려져 있고, hp/mp는 같은 캔버스 크기에 그 트랙 자리와 정확히 겹치는
// 색칠된 게이지만 그려진 오버레이다. 이름/TBP 점/직업 아이콘은 이번 재디자인에서
// 뺐다(사용자 지시) - HP/MP 바 + 현재 수치만 표시.

// 좌하단 2x2 그리드 배치 - ReferenceResource/party_hud/ref1.png 목업 배치를
// 픽셀로 실측해서 맞춘 값(카드 캔버스 자체는 위쪽에 내용이 몰려있고 아래쪽은
// 투명이라, ROW_PITCH가 카드 원본 높이(120)보다 훨씬 작아도 다음 줄과 안 겹친다).
const GRID_LEFT = 52
const COLUMN_PITCH = 410
const COLUMNS = 2
const ROW_PITCH = 71
const GRID_TOP = STAGE_HEIGHT - ROW_PITCH * 2 - 18

// party_hud_hp.png/party_hud_mp.png 안에서 실제 게이지가 차지하는 영역(카드 캔버스
// 360x120 기준 픽셀 좌표) - PIL로 non-transparent 영역을 실측한 값.
const HP_BAR_RECT = { x: 177, y: 38, width: 116, height: 8 }
const MP_BAR_RECT = { x: 184, y: 51, width: 116, height: 8 }

const VALUE_FONT_SIZE = 12
const VALUE_COLOR = 0xffffff
// 바 끝 오른쪽에 현재 수치만 표시(최대치는 바 길이로 이미 보이고, 카드가 좁아
// "382/382" 전부 넣을 자리가 없다) - 바 우측 끝에서 이 만큼 띄운다.
const VALUE_GAP_X = 6

// base.png에 박힌 "TG"(x178~189)와 "%PERCENT"(x235~291) 라벨 사이 빈 칸(PIL로
// 핑크색 텍스트 픽셀만 골라 실측) 정중앙에 진짜 ATB 게이지(0~100+, atb_gauge)를
// 퍼센트로 표기한다 - TG("턴 게이지") 라벨의 실제 값 자리.
const ATB_PERCENT_CENTER_X = (189 + 235) / 2
// 기준 67에서 사용자 지시로 2px 위로.
const ATB_PERCENT_CENTER_Y = 65
// 13 -> 16 -> 19 -> 22 -> 24 -> 22(사용자 지시로 최종 축소).
const ATB_PERCENT_FONT_SIZE = 22
// 사용자 지시: 폰트 내부 그라데이션(위쪽 흰색 -> 아래쪽 핑크), 테두리는 검정 50% 불투명.
const ATB_PERCENT_GRADIENT_TOP = '#ffffff'
const ATB_PERCENT_GRADIENT_BOTTOM = '#ed74b0'

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
    atbValue.anchor.set(0.5, 0.5)
    atbValue.position.set(ATB_PERCENT_CENTER_X, ATB_PERCENT_CENTER_Y)
    card.addChild(atbValue)

    return {
      card,
      hpMask: hp.mask,
      hpValue: hp.valueText,
      mpMask: mp.mask,
      mpValue: mp.valueText,
      atbValue,
    }
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
