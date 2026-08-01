<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  FORMATION_PRESET_COUNT,
  FORMATION_MAX_PLACED,
  FRONT_SLOTS,
  BACK_SLOTS,
  getFormation,
  updateFormationPreset,
  setActiveFormationPreset,
  type FormationData,
  type Mercenary,
} from '@/lib/mercenaryApi'
import { portraitStyle } from '@/lib/portrait'
import strings from '@/locales/ko'

const presetNumbers = Array.from({ length: FORMATION_PRESET_COUNT }, (_, i) => i + 1)

const formation = ref<FormationData | null>(null)
const viewingPreset = ref(1)
const workingSlots = ref<Record<number, Mercenary | null>>({})
const workingUnplaced = ref<Mercenary[]>([])
const selectedUnitId = ref<number | null>(null)
const dirty = ref(false)
const loading = ref(true)
const saving = ref(false)
const message = ref('')

const placedCount = computed(
  () => Object.values(workingSlots.value).filter((u): u is Mercenary => u !== null).length,
)

function loadWorkingCopy(presetNumber: number) {
  const preset = formation.value?.presets[String(presetNumber)]
  workingSlots.value = { ...(preset?.slots ?? {}) } as Record<number, Mercenary | null>
  workingUnplaced.value = [...(preset?.unplaced ?? [])]
  selectedUnitId.value = null
  dirty.value = false
}

async function load() {
  loading.value = true
  try {
    formation.value = await getFormation()
    loadWorkingCopy(viewingPreset.value)
  } catch {
    message.value = strings.mercenary.formation.loadFailed
  } finally {
    loading.value = false
  }
}

function confirmLeavingIfDirty(): boolean {
  if (!dirty.value) return true
  return window.confirm(strings.mercenary.formation.confirmLeave)
}

function switchPreset(presetNumber: number) {
  if (presetNumber === viewingPreset.value) return
  if (!confirmLeavingIfDirty()) return
  viewingPreset.value = presetNumber
  loadWorkingCopy(presetNumber)
}

function selectPoolUnit(unit: Mercenary) {
  selectedUnitId.value = selectedUnitId.value === unit.id ? null : unit.id
}

function clickSlot(slot: number) {
  message.value = ''
  const current = workingSlots.value[slot]
  if (current) {
    workingUnplaced.value.push(current)
    workingSlots.value[slot] = null
    dirty.value = true
    return
  }

  if (selectedUnitId.value === null) return
  if (placedCount.value >= FORMATION_MAX_PLACED) {
    message.value = strings.mercenary.formation.maxPlaced(FORMATION_MAX_PLACED)
    return
  }
  const idx = workingUnplaced.value.findIndex((u) => u.id === selectedUnitId.value)
  if (idx === -1) return
  const unit = workingUnplaced.value.splice(idx, 1)[0]!
  workingSlots.value[slot] = unit
  selectedUnitId.value = null
  dirty.value = true
}

async function save() {
  saving.value = true
  message.value = ''
  try {
    const slots: Record<number, number | null> = {}
    for (const slot of [...FRONT_SLOTS, ...BACK_SLOTS]) {
      slots[slot] = workingSlots.value[slot]?.id ?? null
    }
    await updateFormationPreset(viewingPreset.value, slots)
    await load()
  } catch (e) {
    message.value = e instanceof Error ? e.message : strings.mercenary.formation.saveFailed
  } finally {
    saving.value = false
  }
}

async function useThisPreset() {
  try {
    await setActiveFormationPreset(viewingPreset.value)
    if (formation.value) formation.value.active_preset = viewingPreset.value
  } catch (e) {
    message.value = e instanceof Error ? e.message : strings.mercenary.formation.applyFailed
  }
}

const isActivePreset = computed(() => formation.value?.active_preset === viewingPreset.value)

onMounted(load)
</script>

<template>
  <div class="formation-tab">
    <div class="preset-bar">
      <button
        v-for="n in presetNumbers"
        :key="n"
        class="preset-tab"
        :class="{ active: viewingPreset === n, viewing: formation?.active_preset === n }"
        @click="switchPreset(n)"
      >
        {{ strings.mercenary.formation.preset(n) }}
      </button>
    </div>

    <p v-if="message" class="message">{{ message }}</p>
    <p v-if="loading" class="hint">{{ strings.mercenary.formation.loading }}</p>

    <template v-else>
      <div class="actions">
        <button class="use-button" :disabled="isActivePreset" @click="useThisPreset">
          {{ isActivePreset ? strings.mercenary.formation.usingPreset : strings.mercenary.formation.usePreset }}
        </button>
        <button class="save-button" :disabled="!dirty || saving" @click="save">
          {{ saving ? strings.mercenary.formation.saving : strings.mercenary.formation.save }}
        </button>
        <span class="placed-count">{{ strings.mercenary.formation.placedCount(placedCount, FORMATION_MAX_PLACED) }}</span>
      </div>

      <div class="columns">
        <div class="column">
          <div class="column-label">{{ strings.mercenary.formation.front }}</div>
          <div v-for="slot in FRONT_SLOTS" :key="slot" class="slot" :class="{ filled: workingSlots[slot] }" @click="clickSlot(slot)">
            <template v-if="workingSlots[slot]">
              <div class="portrait" :style="portraitStyle(workingSlots[slot]!.sprite, 56)"></div>
              <span>{{ workingSlots[slot]!.name }}</span>
            </template>
            <span v-else class="empty-label">{{ strings.mercenary.formation.emptySlot }}</span>
          </div>
        </div>
        <div class="column">
          <div class="column-label">{{ strings.mercenary.formation.back }}</div>
          <div v-for="slot in BACK_SLOTS" :key="slot" class="slot" :class="{ filled: workingSlots[slot] }" @click="clickSlot(slot)">
            <template v-if="workingSlots[slot]">
              <div class="portrait" :style="portraitStyle(workingSlots[slot]!.sprite, 56)"></div>
              <span>{{ workingSlots[slot]!.name }}</span>
            </template>
            <span v-else class="empty-label">{{ strings.mercenary.formation.emptySlot }}</span>
          </div>
        </div>
      </div>

      <div class="pool-label">{{ strings.mercenary.formation.unplacedLabel }}</div>
      <div class="pool">
        <p v-if="workingUnplaced.length === 0" class="hint">{{ strings.mercenary.formation.noUnplaced }}</p>
        <div
          v-for="unit in workingUnplaced"
          :key="unit.id"
          class="pool-card"
          :class="{ selected: selectedUnitId === unit.id }"
          @click="selectPoolUnit(unit)"
        >
          <div class="portrait" :style="portraitStyle(unit.sprite, 48)"></div>
          <span>{{ unit.name }}</span>
        </div>
      </div>
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

.preset-bar {
  display: flex;
  gap: 0.4rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.preset-tab {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #f1f5f9;
  border-radius: 6px;
  padding: 0.4rem 0.8rem;
  cursor: pointer;
  font-family: inherit;
  font-size: 0.82rem;
}

.preset-tab.active {
  background: rgba(255, 255, 255, 0.18);
}

.preset-tab.viewing {
  border-color: hsla(160, 100%, 37%, 1);
}

.actions {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 1rem;
}

.placed-count {
  margin-left: auto;
  font-size: 0.78rem;
  opacity: 0.7;
}

.use-button,
.save-button {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  font-family: inherit;
  font-size: 0.85rem;
  cursor: pointer;
}

.use-button {
  background: rgba(255, 255, 255, 0.1);
  color: #f1f5f9;
}

.use-button:disabled {
  opacity: 0.5;
  cursor: default;
}

.save-button {
  background: hsla(160, 100%, 37%, 1);
  color: #fff;
}

.save-button:disabled {
  opacity: 0.4;
  cursor: default;
}

.columns {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.column-label {
  font-size: 0.8rem;
  opacity: 0.7;
  margin-bottom: 0.4rem;
}

.slot {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 48px;
  border: 1px dashed rgba(255, 255, 255, 0.25);
  border-radius: 8px;
  padding: 0.4rem 0.6rem;
  margin-bottom: 0.5rem;
  cursor: pointer;
  font-size: 0.85rem;
}

.slot.filled {
  border-style: solid;
  background: rgba(255, 255, 255, 0.06);
}

.empty-label {
  opacity: 0.4;
  font-size: 0.78rem;
}

.portrait {
  border-radius: 6px;
  background-color: rgba(0, 0, 0, 0.3);
  flex-shrink: 0;
}

.pool-label {
  font-size: 0.8rem;
  opacity: 0.7;
  margin-bottom: 0.4rem;
}

.pool {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
}

.pool-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.3rem;
  padding: 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.78rem;
  background: rgba(255, 255, 255, 0.03);
}

.pool-card.selected {
  border-color: hsla(160, 100%, 37%, 1);
  background: rgba(16, 185, 129, 0.15);
}
</style>
