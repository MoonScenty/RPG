<script setup lang="ts">
import { ref } from 'vue'
import InventoryTab from '@/components/crafting/InventoryTab.vue'
import strings from '@/locales/ko'

// 예전엔 아이템 인벤토리가 연구소(CraftingPanel lab), 무기/방어구 인벤토리가
// 대장간(CraftingPanel smithy)에 각각 흩어져 있었다 - 재사용 가능한
// InventoryTab.vue(components/crafting/) 자체는 그대로 두고, 이 패널에서
// 셋을 한 곳(하단 메뉴 "인벤토리")에 모아 보여준다.
type TabKey = 'items' | 'weapons' | 'armors'

const emit = defineEmits<{ close: [] }>()

const activeTab = ref<TabKey>('items')

const tabs: Array<{ key: TabKey; label: string }> = [
  { key: 'items', label: strings.crafting.materialType.item },
  { key: 'weapons', label: strings.crafting.materialType.weapon },
  { key: 'armors', label: strings.crafting.materialType.armor },
]
</script>

<template>
  <div class="inventory-panel">
    <div class="header">
      <div class="panel-title">{{ strings.home.menu.inventory }}</div>
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
      <InventoryTab v-if="activeTab === 'items'" type="item" />
      <InventoryTab v-else-if="activeTab === 'weapons'" type="weapon" />
      <InventoryTab v-else type="armor" />
    </div>
  </div>
</template>

<style scoped>
.inventory-panel {
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
  gap: 0.75rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.15);
  padding: 0.4rem 0.6rem;
  flex-shrink: 0;
}

.panel-title {
  font-weight: bold;
  font-size: 0.9rem;
  padding-left: 0.3rem;
}

.tab-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
  flex: 1;
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
