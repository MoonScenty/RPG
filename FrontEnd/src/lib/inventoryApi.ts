import { apiRequest } from './api'

/**
 * backend InventoryController - mz_items/mz_weapons/mz_armors 카탈로그 전체를
 * 계정 보유 수량(quantity)과 합쳐서 내려준다. 카탈로그 종류별로 추가 필드(가격
 * 등)가 더 있지만 인벤토리 화면에서는 이름/수량/아이콘만 쓴다.
 */
export interface InventoryEntry {
  id: number
  name: string
  quantity: number
  icon_index: number
}

export function getItemInventory(): Promise<InventoryEntry[]> {
  return apiRequest('GET', '/api/items')
}

export function getWeaponInventory(): Promise<InventoryEntry[]> {
  return apiRequest('GET', '/api/weapons')
}

export function getArmorInventory(): Promise<InventoryEntry[]> {
  return apiRequest('GET', '/api/armors')
}
