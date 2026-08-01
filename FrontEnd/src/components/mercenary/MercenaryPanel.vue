<script setup lang="ts">
import { ref } from 'vue'
import PurchaseTab from './PurchaseTab.vue'
import FormationTab from './FormationTab.vue'
import GambitTab from './GambitTab.vue'
import EquipmentTab from './EquipmentTab.vue'
import strings from '@/locales/ko'

type TabKey = 'buy' | 'formation' | 'gambit' | 'equipment'

const emit = defineEmits<{ close: [] }>()

const activeTab = ref<TabKey>('buy')

const tabs: Array<{ key: TabKey; label: string }> = [
  { key: 'buy', label: strings.mercenary.tabs.buy },
  { key: 'formation', label: strings.mercenary.tabs.formation },
  { key: 'gambit', label: strings.mercenary.tabs.gambit },
  { key: 'equipment', label: strings.mercenary.tabs.equipment },
]
</script>

<template>
  <div class="mercenary-panel">
    <div class="header">
      <div class="tab-bar">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="tab"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>
      <button class="close-button" @click="emit('close')">✕</button>
    </div>

    <div class="tab-content">
      <PurchaseTab v-if="activeTab === 'buy'" />
      <FormationTab v-else-if="activeTab === 'formation'" />
      <GambitTab v-else-if="activeTab === 'gambit'" />
      <EquipmentTab v-else />
    </div>
  </div>
</template>

<style scoped>
.mercenary-panel {
  flex: 1 1 auto;
  align-self: stretch;
  display: flex;
  flex-direction: column;
  min-height: 0;
  background: rgba(0, 0, 0, 0.85);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 15px;
  overflow: hidden;
}

.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid rgba(255, 255, 255, 0.15);
  padding: 0.4rem 0.6rem;
  flex-shrink: 0;
}

.tab-bar {
  display: flex;
  gap: 0.3rem;
}

.tab {
  background: none;
  border: none;
  color: rgba(241, 245, 249, 0.6);
  padding: 0.5rem 0.9rem;
  cursor: pointer;
  font-family: inherit;
  font-size: 0.85rem;
  border-radius: 8px;
}

.tab.active {
  color: #f1f5f9;
  background: rgba(255, 255, 255, 0.1);
}

.tab:hover {
  color: #f1f5f9;
}

.close-button {
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #f1f5f9;
  border-radius: 8px;
  width: 30px;
  height: 30px;
  cursor: pointer;
  font-size: 0.85rem;
}

.close-button:hover {
  background: rgba(255, 255, 255, 0.18);
}

.tab-content {
  flex: 1;
  overflow-y: auto;
  padding: 0.9rem;
  color: #f1f5f9;
  font-size: 0.8rem;
}
</style>
