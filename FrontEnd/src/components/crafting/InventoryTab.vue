<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { getArmorInventory, getItemInventory, getWeaponInventory, type InventoryEntry } from '@/lib/inventoryApi'
import { mzIconStyle } from '@/lib/mzIcon'
import strings from '@/locales/ko'

const props = defineProps<{ type: 'item' | 'weapon' | 'armor' }>()

const entries = ref<InventoryEntry[]>([])
const loading = ref(true)
const message = ref('')

function fetchFor(type: 'item' | 'weapon' | 'armor'): Promise<InventoryEntry[]> {
  if (type === 'item') return getItemInventory()
  if (type === 'weapon') return getWeaponInventory()
  return getArmorInventory()
}

onMounted(async () => {
  loading.value = true
  try {
    // 카탈로그 전체(아이템 166종/무기 140종/방어구 335종)를 다 보여주면 대부분
    // 수량 0짜리라 훑어보기 어려우니, "인벤토리 보기"라는 취지에 맞게 실제로
    // 보유한(quantity > 0) 것만 남긴다 - 카탈로그 전체 구경은 상점(미구현) 몫.
    const all = await fetchFor(props.type)
    entries.value = all.filter((entry) => entry.quantity > 0)
  } catch {
    message.value = strings.crafting.inventory.loadFailed
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="inventory-tab">
    <p v-if="message" class="message">{{ message }}</p>
    <p v-else-if="loading" class="hint">{{ strings.crafting.inventory.loading }}</p>
    <p v-else-if="entries.length === 0" class="hint">{{ strings.crafting.inventory.empty }}</p>
    <div v-else class="grid">
      <div v-for="entry in entries" :key="entry.id" class="card">
        <span class="icon" :style="mzIconStyle(entry.icon_index, 32)"></span>
        <div class="name">{{ entry.name }}</div>
        <div class="quantity">{{ strings.crafting.inventory.quantity(entry.quantity) }}</div>
      </div>
    </div>
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
}

.grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.9rem;
}

.card {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 10px;
  padding: 0.7rem 0.9rem;
}

.icon {
  flex-shrink: 0;
  background-image: url('/assets/icons/IconSet.png');
  background-repeat: no-repeat;
  image-rendering: pixelated;
}

.name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: bold;
}

.quantity {
  flex-shrink: 0;
  opacity: 0.8;
  font-size: 0.8rem;
  white-space: nowrap;
}
</style>
