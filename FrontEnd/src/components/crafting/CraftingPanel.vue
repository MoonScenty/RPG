<script setup lang="ts">
import { computed } from 'vue'
import CraftingTab from './CraftingTab.vue'
import type { Workshop } from '@/lib/craftingApi'
import strings from '@/locales/ko'

// 용병소(MercenaryPanel.vue)와 동일한 헤더+닫기 버튼 쉘을 재사용한다. 예전엔
// 여기에 아이템/무기/방어구 인벤토리 탭도 있었지만, 인벤토리 열람은 새
// InventoryPanel.vue(하단 메뉴 "인벤토리")로 한 곳에 모았으므로 이 패널은
// 조합 화면 하나만 보여준다.
const props = defineProps<{ workshop: Workshop }>()
const emit = defineEmits<{ close: [] }>()

const title = computed(() => (props.workshop === 'lab' ? strings.home.menu.lab : strings.home.menu.blacksmith))
</script>

<template>
  <div class="crafting-panel">
    <div class="header">
      <div class="panel-title">{{ title }}</div>
      <button class="close-button" @click="emit('close')">✕</button>
    </div>

    <div class="tab-content">
      <CraftingTab :workshop="workshop" />
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
  justify-content: space-between;
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
