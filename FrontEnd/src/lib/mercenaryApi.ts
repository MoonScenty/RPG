import { apiRequest } from './api'
import strings from '@/locales/ko'

export interface Mercenary {
  id: number
  name: string
  type: 'ally' | 'enemy'
  max_hp: number
  max_mp: number
  atk: number
  def: number
  spd: number
  sprite: string
  owned: boolean
  price: number
}

export function getMercenaries(): Promise<Mercenary[]> {
  return apiRequest('GET', '/api/mercenaries')
}

export function purchaseMercenary(id: number): Promise<{ gold: number }> {
  return apiRequest('POST', `/api/mercenaries/${id}/purchase`)
}

// ---- 진형 ----

export const FORMATION_PRESET_COUNT = 5
/** 슬롯은 6개(전열3+후열3)지만 실제 배치 가능한 인원은 4명까지 - 백엔드 FormationController와 동일. */
export const FORMATION_MAX_PLACED = 4
export const FRONT_SLOTS = [1, 2, 3]
export const BACK_SLOTS = [4, 5, 6]

export interface FormationPreset {
  slots: Record<string, Mercenary | null>
  unplaced: Mercenary[]
}

export interface FormationData {
  active_preset: number
  presets: Record<string, FormationPreset>
}

export function getFormation(): Promise<FormationData> {
  return apiRequest('GET', '/api/formation')
}

/** slots는 "1".."6" -> unitId|null. 비어있는 슬롯은 굳이 포함 안 해도 됨. */
export function updateFormationPreset(preset: number, slots: Record<number, number | null>): Promise<void> {
  return apiRequest('PUT', '/api/formation', { preset, slots })
}

export function setActiveFormationPreset(preset: number): Promise<void> {
  return apiRequest('PUT', '/api/formation/active', { preset })
}

// ---- 행동(gambit) ----

export type ConditionKind = 'always' | 'first_action' | 'stat' | 'debuff' | 'position' | 'dead_ally'
export type ConditionScope = 'self' | 'ally' | 'enemy'
export type StatKind = 'hp' | 'mp'
export type ValueType = 'percent' | 'flat'
export type Operator = 'gte' | 'lte'
export type PositionKind = 'front' | 'back'
export type GambitActionType = 'attack' | 'skill' | 'item' | 'cancel'

export interface GambitRule {
  condition_kind: ConditionKind
  condition_scope: ConditionScope | null
  condition_stat: StatKind | null
  condition_value_type: ValueType | null
  condition_operator: Operator | null
  condition_value: number | null
  condition_debuff_key: string | null
  condition_position: PositionKind | null
  action_type: GambitActionType
  action_skill_key: string | null
}

export const GAMBIT_PRESET_COUNT = 5

export interface GambitPresetsData {
  active_preset: number
  presets: Record<string, GambitRule[]>
}

export interface SkillInfo {
  key: string
  name: string
  target_side: 'ally' | 'enemy' | 'self'
  mp_cost: number
  /** 사용 후 재사용까지 대기해야 하는 턴 수(0 = 제한 없음). */
  cooldown: number
  /** 캐스팅 지연 턴 수(0 = 즉시 발동). */
  casting: number
}

export interface ItemInfo {
  key: string
  name: string
  target_side: 'ally' | 'enemy' | 'self'
  description: string | null
}

export interface DebuffInfo {
  key: string
  label: string
}

export interface GambitCatalogData {
  skills: SkillInfo[]
  items: ItemInfo[]
  debuffs: DebuffInfo[]
}

export interface ConditionOption {
  id: string
  label: string
  condition_kind: ConditionKind
  condition_scope: ConditionScope | null
  condition_stat: StatKind | null
  condition_value_type: ValueType | null
  condition_operator: Operator | null
  condition_position: PositionKind | null
  needsValue: boolean
  needsDebuff: boolean
}

const t = strings.mercenary.gambit.conditions

/** 고정 조건 템플릿. 백엔드 MercenaryGambitController::validateRule()과 1:1 대응. */
export const CONDITION_OPTIONS: ConditionOption[] = [
  { id: 'always', label: t.always, condition_kind: 'always', condition_scope: null, condition_stat: null, condition_value_type: null, condition_operator: null, condition_position: null, needsValue: false, needsDebuff: false },
  { id: 'first_action', label: t.firstAction, condition_kind: 'first_action', condition_scope: null, condition_stat: null, condition_value_type: null, condition_operator: null, condition_position: null, needsValue: false, needsDebuff: false },

  { id: 'self_position_front', label: t.selfPositionFront, condition_kind: 'position', condition_scope: null, condition_stat: null, condition_value_type: null, condition_operator: null, condition_position: 'front', needsValue: false, needsDebuff: false },
  { id: 'self_position_back', label: t.selfPositionBack, condition_kind: 'position', condition_scope: null, condition_stat: null, condition_value_type: null, condition_operator: null, condition_position: 'back', needsValue: false, needsDebuff: false },

  { id: 'self_mp_pct_gte', label: t.selfMpPctGte, condition_kind: 'stat', condition_scope: 'self', condition_stat: 'mp', condition_value_type: 'percent', condition_operator: 'gte', condition_position: null, needsValue: true, needsDebuff: false },
  { id: 'self_mp_pct_lte', label: t.selfMpPctLte, condition_kind: 'stat', condition_scope: 'self', condition_stat: 'mp', condition_value_type: 'percent', condition_operator: 'lte', condition_position: null, needsValue: true, needsDebuff: false },
  { id: 'self_mp_flat_gte', label: t.selfMpFlatGte, condition_kind: 'stat', condition_scope: 'self', condition_stat: 'mp', condition_value_type: 'flat', condition_operator: 'gte', condition_position: null, needsValue: true, needsDebuff: false },
  { id: 'self_mp_flat_lte', label: t.selfMpFlatLte, condition_kind: 'stat', condition_scope: 'self', condition_stat: 'mp', condition_value_type: 'flat', condition_operator: 'lte', condition_position: null, needsValue: true, needsDebuff: false },

  { id: 'self_hp_pct_gte', label: t.selfHpPctGte, condition_kind: 'stat', condition_scope: 'self', condition_stat: 'hp', condition_value_type: 'percent', condition_operator: 'gte', condition_position: null, needsValue: true, needsDebuff: false },
  { id: 'self_hp_pct_lte', label: t.selfHpPctLte, condition_kind: 'stat', condition_scope: 'self', condition_stat: 'hp', condition_value_type: 'percent', condition_operator: 'lte', condition_position: null, needsValue: true, needsDebuff: false },
  { id: 'self_hp_flat_gte', label: t.selfHpFlatGte, condition_kind: 'stat', condition_scope: 'self', condition_stat: 'hp', condition_value_type: 'flat', condition_operator: 'gte', condition_position: null, needsValue: true, needsDebuff: false },
  { id: 'self_hp_flat_lte', label: t.selfHpFlatLte, condition_kind: 'stat', condition_scope: 'self', condition_stat: 'hp', condition_value_type: 'flat', condition_operator: 'lte', condition_position: null, needsValue: true, needsDebuff: false },

  { id: 'ally_mp_pct_gte', label: t.allyMpPctGte, condition_kind: 'stat', condition_scope: 'ally', condition_stat: 'mp', condition_value_type: 'percent', condition_operator: 'gte', condition_position: null, needsValue: true, needsDebuff: false },
  { id: 'ally_mp_pct_lte', label: t.allyMpPctLte, condition_kind: 'stat', condition_scope: 'ally', condition_stat: 'mp', condition_value_type: 'percent', condition_operator: 'lte', condition_position: null, needsValue: true, needsDebuff: false },
  { id: 'ally_mp_flat_gte', label: t.allyMpFlatGte, condition_kind: 'stat', condition_scope: 'ally', condition_stat: 'mp', condition_value_type: 'flat', condition_operator: 'gte', condition_position: null, needsValue: true, needsDebuff: false },
  { id: 'ally_mp_flat_lte', label: t.allyMpFlatLte, condition_kind: 'stat', condition_scope: 'ally', condition_stat: 'mp', condition_value_type: 'flat', condition_operator: 'lte', condition_position: null, needsValue: true, needsDebuff: false },

  { id: 'ally_hp_pct_gte', label: t.allyHpPctGte, condition_kind: 'stat', condition_scope: 'ally', condition_stat: 'hp', condition_value_type: 'percent', condition_operator: 'gte', condition_position: null, needsValue: true, needsDebuff: false },
  { id: 'ally_hp_pct_lte', label: t.allyHpPctLte, condition_kind: 'stat', condition_scope: 'ally', condition_stat: 'hp', condition_value_type: 'percent', condition_operator: 'lte', condition_position: null, needsValue: true, needsDebuff: false },
  { id: 'ally_hp_flat_gte', label: t.allyHpFlatGte, condition_kind: 'stat', condition_scope: 'ally', condition_stat: 'hp', condition_value_type: 'flat', condition_operator: 'gte', condition_position: null, needsValue: true, needsDebuff: false },
  { id: 'ally_hp_flat_lte', label: t.allyHpFlatLte, condition_kind: 'stat', condition_scope: 'ally', condition_stat: 'hp', condition_value_type: 'flat', condition_operator: 'lte', condition_position: null, needsValue: true, needsDebuff: false },

  { id: 'self_debuff', label: t.selfDebuff, condition_kind: 'debuff', condition_scope: 'self', condition_stat: null, condition_value_type: null, condition_operator: null, condition_position: null, needsValue: false, needsDebuff: true },
  { id: 'ally_debuff', label: t.allyDebuff, condition_kind: 'debuff', condition_scope: 'ally', condition_stat: null, condition_value_type: null, condition_operator: null, condition_position: null, needsValue: false, needsDebuff: true },
  { id: 'enemy_debuff', label: t.enemyDebuff, condition_kind: 'debuff', condition_scope: 'enemy', condition_stat: null, condition_value_type: null, condition_operator: null, condition_position: null, needsValue: false, needsDebuff: true },

  { id: 'ally_dead', label: t.allyDead, condition_kind: 'dead_ally', condition_scope: null, condition_stat: null, condition_value_type: null, condition_operator: null, condition_position: null, needsValue: false, needsDebuff: false },
]

export const ACTION_OPTIONS: Array<{ type: GambitActionType; label: string }> = [
  { type: 'attack', label: strings.mercenary.gambit.actions.attack },
  { type: 'skill', label: strings.mercenary.gambit.actions.skill },
  { type: 'item', label: strings.mercenary.gambit.actions.item },
  { type: 'cancel', label: strings.mercenary.gambit.actions.cancel },
]

/** 저장된 규칙이 20개 고정 템플릿 중 어디에 해당하는지 찾는다(드롭다운 초기 선택용). */
export function findConditionOption(rule: GambitRule): ConditionOption {
  const match = CONDITION_OPTIONS.find(
    (opt) =>
      opt.condition_kind === rule.condition_kind &&
      opt.condition_scope === rule.condition_scope &&
      opt.condition_stat === rule.condition_stat &&
      opt.condition_value_type === rule.condition_value_type &&
      opt.condition_operator === rule.condition_operator &&
      opt.condition_position === rule.condition_position,
  )
  return match ?? CONDITION_OPTIONS[0]!
}

/** 이 용병(직업)이 쓸 수 있는 스킬만 내려온다 - 직업마다 배울 수 있는 스킬이 다름. */
export function getGambitCatalog(unitId: number): Promise<GambitCatalogData> {
  return apiRequest('GET', `/api/mercenaries/${unitId}/gambits/catalog`)
}

export function getGambits(unitId: number): Promise<GambitPresetsData> {
  return apiRequest('GET', `/api/mercenaries/${unitId}/gambits`)
}

export function updateGambitPreset(unitId: number, preset: number, rules: GambitRule[]): Promise<void> {
  return apiRequest('PUT', `/api/mercenaries/${unitId}/gambits`, { preset, rules })
}

export function setActiveGambitPreset(unitId: number, preset: number): Promise<void> {
  return apiRequest('PUT', `/api/mercenaries/${unitId}/gambits/active`, { preset })
}
