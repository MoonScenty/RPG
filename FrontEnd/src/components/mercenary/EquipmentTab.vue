<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { getMercenaries, type Mercenary } from '@/lib/mercenaryApi'
import { getEquipment, updateEquipment, type EquipmentData, type EquipmentSlot } from '@/lib/equipmentApi'
import { portraitStyle } from '@/lib/portrait'
import { mzIconStyle } from '@/lib/mzIcon'
import strings from '@/locales/ko'

const SLOTS: EquipmentSlot[] = ['weapon', 'shield', 'body_armor', 'accessory']

const roster = ref<Mercenary[]>([])
const selectedUnit = ref<Mercenary | null>(null)
const equipment = ref<EquipmentData | null>(null)
const loading = ref(true)
const updatingSlot = ref<EquipmentSlot | null>(null)
const message = ref('')

async function loadRoster() {
  const all = await getMercenaries()
  roster.value = all.filter((u) => u.owned)
}

async function selectUnit(unit: Mercenary) {
  selectedUnit.value = unit
  loading.value = true
  message.value = ''
  try {
    equipment.value = await getEquipment(unit.id)
  } catch {
    message.value = strings.mercenary.equipment.loadFailed
  } finally {
    loading.value = false
  }
}

async function onSelect(slot: EquipmentSlot, event: Event) {
  if (!selectedUnit.value) return
  const value = (event.target as HTMLSelectElement).value
  const itemId = value === '' ? null : Number(value)
  updatingSlot.value = slot
  message.value = ''
  try {
    equipment.value = await updateEquipment(selectedUnit.value.id, slot, itemId)
  } catch (e) {
    message.value = e instanceof Error ? e.message : strings.mercenary.equipment.updateFailed
  } finally {
    updatingSlot.value = null
  }
}

function slotLabel(slot: EquipmentSlot): string {
  return strings.mercenary.equipment.slots[slot]
}

onMounted(async () => {
  try {
    await loadRoster()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="equipment-tab">
    <div class="roster">
      <div
        v-for="unit in roster"
        :key="unit.id"
        class="roster-card"
        :class="{ selected: selectedUnit?.id === unit.id }"
        @click="selectUnit(unit)"
      >
        <div class="portrait" :style="portraitStyle(unit.sprite, 48)"></div>
        <span>{{ unit.name }}</span>
      </div>
      <p v-if="roster.length === 0 && !loading" class="hint">{{ strings.mercenary.equipment.noRoster }}</p>
    </div>

    <p v-if="message" class="message">{{ message }}</p>
    <p v-else-if="selectedUnit && loading" class="hint">{{ strings.mercenary.equipment.loading }}</p>

    <div v-if="selectedUnit && equipment" class="slot-grid">
      <div v-for="slot in SLOTS" :key="slot" class="slot-card">
        <div class="slot-title">{{ slotLabel(slot) }}</div>

        <div class="equipped-row">
          <span
            v-if="equipment.equipped[slot]"
            class="icon"
            :style="mzIconStyle(equipment.equipped[slot]!.icon_index, 32)"
          ></span>
          <span class="equipped-name">{{ equipment.equipped[slot]?.name ?? strings.mercenary.equipment.empty }}</span>
        </div>

        <select
          class="select"
          :disabled="updatingSlot === slot"
          :value="equipment.equipped[slot]?.id ?? ''"
          @change="onSelect(slot, $event)"
        >
          <option value="">{{ strings.mercenary.equipment.unequip }}</option>
          <option v-for="opt in equipment.options[slot]" :key="opt.id" :value="opt.id">
            {{ opt.name }} ({{ strings.mercenary.equipment.owned(opt.quantity) }})
          </option>
        </select>

        <p v-if="equipment.options[slot].length === 0" class="hint small">{{ strings.mercenary.equipment.noOptions }}</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.hint {
  opacity: 0.7;
  font-size: 0.9rem;
}

.hint.small {
  font-size: 0.75rem;
  margin: 0.4rem 0 0;
}

.message {
  color: #f87171;
  font-size: 0.9rem;
  margin-bottom: 0.75rem;
}

.roster {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin-bottom: 1.25rem;
}

.roster-card {
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

.roster-card.selected {
  border-color: hsla(160, 100%, 37%, 1);
  background: rgba(16, 185, 129, 0.15);
}

.portrait {
  border-radius: 6px;
  background-color: rgba(0, 0, 0, 0.3);
}

.slot-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 0.9rem;
}

.slot-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 10px;
  padding: 0.7rem 0.9rem;
}

.slot-title {
  font-weight: bold;
  font-size: 0.82rem;
  margin-bottom: 0.5rem;
}

.equipped-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.6rem;
  min-height: 32px;
}

.icon {
  flex-shrink: 0;
  background-image: url('/assets/icons/IconSet.png');
  background-repeat: no-repeat;
  image-rendering: pixelated;
}

.equipped-name {
  font-size: 0.85rem;
}

.select {
  width: 100%;
  background: #1e293b;
  color: #f1f5f9;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 6px;
  padding: 0.35rem 0.5rem;
  font-family: inherit;
  font-size: 0.8rem;
}
</style>
