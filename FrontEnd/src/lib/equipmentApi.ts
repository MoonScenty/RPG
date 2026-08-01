import { apiRequest } from './api'

export type EquipmentSlot = 'weapon' | 'shield' | 'body_armor' | 'accessory'

export interface EquipmentItem {
  id: number
  name: string
  icon_index: number
}

export interface EquipmentOption extends EquipmentItem {
  /** 이 슬롯에 장착하지 않고 인벤토리 풀에 남아있는(예약되지 않은) 보유 수량. */
  quantity: number
}

export interface EquipmentData {
  equipped: Record<EquipmentSlot, EquipmentItem | null>
  options: Record<EquipmentSlot, EquipmentOption[]>
}

export function getEquipment(unitId: number): Promise<EquipmentData> {
  return apiRequest('GET', `/api/mercenaries/${unitId}/equipment`)
}

/** itemId를 null로 보내면 해제(인벤토리 풀로 반환). */
export function updateEquipment(unitId: number, slot: EquipmentSlot, itemId: number | null): Promise<EquipmentData> {
  return apiRequest('PUT', `/api/mercenaries/${unitId}/equipment`, { slot, item_id: itemId })
}
