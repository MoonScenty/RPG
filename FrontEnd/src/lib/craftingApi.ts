import { apiRequest } from './api'

/** 연구소(포션류)/대장간(무기·방어구) - BackEnd CraftingController와 1:1 대응. */
export type Workshop = 'lab' | 'smithy'
export type RecipeType = 'item' | 'weapon' | 'armor'
/** 조합 재료의 소속 인벤토리 종류 - RecipeType과 값 집합은 같지만 의미가 달라 별도 타입으로 둠. */
export type MaterialType = 'item' | 'weapon' | 'armor'

export interface CraftingJob {
  id: number
  workshop: Workshop
  recipe_type: RecipeType
  recipe_id: number
  recipe_name: string | null
  finishes_at: string
  remaining_seconds: number
  ready: boolean
}

export interface RecipeMaterial {
  type: MaterialType
  name: string
  need: number
  have: number
}

export interface Recipe {
  recipe_type: RecipeType
  recipe_id: number
  name: string
  seconds: number
  gold_cost: number
  materials: RecipeMaterial[]
  craftable: boolean
}

/** 본인의 활성 작업 목록(양쪽 작업장 합쳐서, 남은 시간 포함). */
export function getCraftingJobs(): Promise<CraftingJob[]> {
  return apiRequest('GET', '/api/crafting')
}

/** 해당 작업장에서 만들 수 있는 레시피 목록(재료 보유량/제작 가능 여부 포함). */
export function getRecipes(workshop: Workshop): Promise<Recipe[]> {
  return apiRequest('GET', `/api/crafting/${workshop}/recipes`)
}

/** 제작 시작 - 성공 시 골드가 이미 차감된 상태로 반환된다(구매 API와 동일한 관례). */
export function startCrafting(
  workshop: Workshop,
  recipeType: RecipeType,
  recipeId: number,
): Promise<CraftingJob & { gold: number }> {
  return apiRequest('POST', `/api/crafting/${workshop}/start`, {
    recipe_type: recipeType,
    recipe_id: recipeId,
  })
}

/** 완료된 작업 수령 - 완성품이 인벤토리에 쌓인다. */
export function collectCraftingJob(id: number): Promise<{ quantity: number }> {
  return apiRequest('POST', `/api/crafting/jobs/${id}/collect`)
}
