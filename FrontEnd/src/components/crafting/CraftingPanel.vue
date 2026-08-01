<script setup lang="ts">
import { computed, ref } from 'vue'
import CraftingTab from './CraftingTab.vue'
import InventoryTab from './InventoryTab.vue'
import type { Workshop } from '@/lib/craftingApi'
import strings from '@/locales/ko'

// 용병소(MercenaryPanel.vue)와 동일한 헤더+탭바+닫기 버튼 쉘을 그대로
// 재사용한다. 탭 구성은 작업장마다 다르다 - 연구소는 조합+아이템 인벤토리,
// 대장간은 조합+무기 인벤토리+방어구 인벤토리(대장간에서 만드는 완성품이
// 무기/방어구 둘 다라 인벤토리도 둘로 나눔). HomeView.vue가 이 컴포넌트에
// activePanel(lab/smithy)을 key로 줘서 작업장을 바꾸면 매번 새로 마운트되므로
// (activeTab 등 이 안의 상태가 이전 작업장 걸로 남아있는 문제 방지),
// 여기서는 workshop 변화에 따로 대응할 필요가 없다.
type TabKey = 'craft' | 'items' | 'weapons' | 'armors'

const props = defineProps<{ workshop: Workshop }>()
const emit = defineEmits<{ close: [] }>()

const activeTab = ref<TabKey>('craft')

const tabs = computed<Array<{ key: TabKey; label: string }>>(() =>
  props.workshop === 'lab'
    ? [
        { key: 'craft', label: strings.crafting.tabs.craft },
        { key: 'items', label: strings.crafting.tabs.items },
      ]
    : [
        { key: 'craft', label: strings.crafting.tabs.craft },
        { key: 'weapons', label: strings.crafting.tabs.weapons },
        { key: 'armors', label: strings.crafting.tabs.armors },
      ],
)

const title = computed(() => (props.workshop === 'lab' ? strings.home.menu.lab : strings.home.menu.blacksmith))
</script>

<template>
  <div class="crafting-panel">
    <div class="header">
      <div class="panel-title">{{ title }}</div>
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
      <CraftingTab v-if="activeTab === 'craft'" :workshop="workshop" />
      <InventoryTab v-else-if="activeTab === 'items'" type="item" />
      <InventoryTab v-else-if="activeTab === 'weapons'" type="weapon" />
      <InventoryTab v-else-if="activeTab === 'armors'" type="armor" />
    </div>
  </div>
</template>

<style scoped>
.crafting-panel {
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
