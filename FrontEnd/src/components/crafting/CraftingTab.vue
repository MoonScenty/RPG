<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  collectCraftingJob,
  getCraftingJobs,
  getRecipes,
  startCrafting,
  type CraftingJob,
  type MaterialType,
  type Recipe,
  type RecipeMaterial,
  type Workshop,
} from '@/lib/craftingApi'
import strings from '@/locales/ko'

// backend CraftingController::SLOT_CAP과 동일 - 서버가 진짜 판정하는 값이고
// 여기서는 버튼을 미리 비활성화해서 헛수고 요청을 줄이는 용도로만 쓴다.
const SLOT_CAP = 5
// 재료가 아이템/무기/방어구 여러 종류일 수 있어(예: "강화 검"은 아이템+무기가
// 섞여 있음) 종류별로 묶어서 "아이템: ...", "무기: ..."처럼 보여준다 - 이 순서로.
const MATERIAL_TYPE_ORDER: MaterialType[] = ['item', 'weapon', 'armor']

const props = defineProps<{ workshop: Workshop }>()

const auth = useAuthStore()
const jobs = ref<CraftingJob[]>([])
const recipes = ref<Recipe[]>([])
const loading = ref(true)
const message = ref('')
const startingKey = ref<string | null>(null)
const collectingId = ref<number | null>(null)

function recipeKey(recipe: Recipe): string {
  return `${recipe.recipe_type}:${recipe.recipe_id}`
}

function materialGroups(recipe: Recipe): Array<{ type: MaterialType; items: RecipeMaterial[] }> {
  return MATERIAL_TYPE_ORDER.map((type) => ({
    type,
    items: recipe.materials.filter((m) => m.type === type),
  })).filter((group) => group.items.length > 0)
}

async function load() {
  loading.value = true
  try {
    const [allJobs, recipeList] = await Promise.all([getCraftingJobs(), getRecipes(props.workshop)])
    jobs.value = allJobs.filter((job) => job.workshop === props.workshop)
    recipes.value = recipeList
  } catch {
    message.value = strings.crafting.loadFailed
  } finally {
    loading.value = false
  }
}

// 서버가 finishes_at만 갖고 있고 별도 타이머/폴링을 안 두는 것과 마찬가지로,
// 여기서도 표시용 카운트다운만 1초마다 로컬에서 깎는다 - 실제로 수령 가능한지는
// collect() 호출 시 서버가 다시 확정한다(먼저 눌러도 서버가 400으로 막아준다).
let tickTimer: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  load()
  tickTimer = setInterval(() => {
    for (const job of jobs.value) {
      if (job.remaining_seconds > 0) {
        job.remaining_seconds -= 1
        if (job.remaining_seconds <= 0) job.ready = true
      }
    }
  }, 1000)
})
onUnmounted(() => {
  if (tickTimer) clearInterval(tickTimer)
})

async function start(recipe: Recipe) {
  if (startingKey.value !== null || jobs.value.length >= SLOT_CAP || !recipe.craftable) return
  message.value = ''
  startingKey.value = recipeKey(recipe)
  try {
    const result = await startCrafting(props.workshop, recipe.recipe_type, recipe.recipe_id)
    if (auth.user) auth.user.gold = result.gold
    await load()
  } catch (e) {
    message.value = e instanceof Error ? e.message : strings.crafting.startFailed
  } finally {
    startingKey.value = null
  }
}

async function collect(job: CraftingJob) {
  if (collectingId.value !== null) return
  message.value = ''
  collectingId.value = job.id
  try {
    await collectCraftingJob(job.id)
    await load()
  } catch (e) {
    message.value = e instanceof Error ? e.message : strings.crafting.collectFailed
  } finally {
    collectingId.value = null
  }
}

function formatSeconds(total: number): string {
  const m = Math.floor(total / 60)
  const s = total % 60
  return m > 0 ? `${m}분 ${s}초` : strings.crafting.seconds(s)
}
</script>

<template>
  <div class="crafting-tab">
    <p v-if="message" class="message">{{ message }}</p>
    <p v-if="loading" class="hint">{{ strings.crafting.loading }}</p>

    <template v-else>
      <section class="section">
        <div class="section-title">{{ strings.crafting.slotsTitle(jobs.length, SLOT_CAP) }}</div>
        <p v-if="jobs.length === 0" class="hint">{{ strings.crafting.emptySlots }}</p>
        <div v-else class="grid">
          <div v-for="job in jobs" :key="job.id" class="card job-card">
            <div class="name">{{ job.recipe_name ?? `#${job.recipe_id}` }}</div>
            <div class="job-status">
              {{ job.ready ? strings.crafting.ready : strings.crafting.remaining(formatSeconds(job.remaining_seconds)) }}
            </div>
            <button
              class="action-button"
              :disabled="!job.ready || collectingId === job.id"
              @click="collect(job)"
            >
              {{ collectingId === job.id ? strings.crafting.collecting : strings.crafting.collect }}
            </button>
          </div>
        </div>
      </section>

      <section class="section">
        <div class="section-title">{{ strings.crafting.recipesTitle }}</div>
        <p v-if="recipes.length === 0" class="hint">{{ strings.crafting.noRecipes }}</p>
        <div v-else class="grid">
          <div v-for="recipe in recipes" :key="recipeKey(recipe)" class="card">
            <div class="name">{{ recipe.name }}</div>
            <div class="materials">
              <div v-for="group in materialGroups(recipe)" :key="group.type" class="material-group">
                <span class="material-group-label">{{ strings.crafting.materialType[group.type] }}:</span>
                <span
                  v-for="material in group.items"
                  :key="material.name"
                  class="material"
                  :class="{ short: material.have < material.need }"
                >
                  {{ material.name }} {{ material.have }}/{{ material.need }}
                </span>
              </div>
            </div>
            <div class="meta">
              <span>{{ formatSeconds(recipe.seconds) }}</span>
              <span>{{ strings.crafting.goldCost(recipe.gold_cost.toLocaleString()) }}</span>
            </div>
            <button
              class="action-button"
              :disabled="startingKey === recipeKey(recipe) || jobs.length >= SLOT_CAP || !recipe.craftable"
              @click="start(recipe)"
            >
              {{
                startingKey === recipeKey(recipe)
                  ? strings.crafting.starting
                  : jobs.length >= SLOT_CAP
                    ? strings.crafting.slotsFull
                    : strings.crafting.start
              }}
            </button>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.hint {
  opacity: 0.7;
  font-size: 0.9rem;
}

.message {
  color: #f87171;
  font-size: 0.9rem;
  margin-bottom: 0.75rem;
}

.section {
  margin-bottom: 1.2rem;
}

.section-title {
  font-weight: bold;
  margin-bottom: 0.6rem;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 0.9rem;
}

.card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 10px;
  padding: 1rem 0.75rem;
}

.job-card {
  justify-content: center;
}

.name {
  font-weight: bold;
  text-align: center;
}

.job-status {
  font-size: 0.75rem;
  opacity: 0.85;
}

.materials {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.3rem;
}

.material-group {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 0.3rem;
}

.material-group-label {
  font-size: 0.68rem;
  font-weight: bold;
  opacity: 0.85;
}

.material {
  font-size: 0.68rem;
  opacity: 0.75;
  background: rgba(255, 255, 255, 0.06);
  border-radius: 5px;
  padding: 0.15rem 0.4rem;
}

.material.short {
  color: #f87171;
  opacity: 1;
}

.meta {
  display: flex;
  gap: 0.6rem;
  font-size: 0.72rem;
  opacity: 0.75;
}

.action-button {
  width: 100%;
  padding: 0.5rem;
  border: none;
  border-radius: 6px;
  background: hsla(160, 100%, 37%, 1);
  color: #fff;
  font-family: inherit;
  font-size: 0.85rem;
  cursor: pointer;
}

.action-button:disabled {
  opacity: 0.5;
  cursor: default;
}
</style>
