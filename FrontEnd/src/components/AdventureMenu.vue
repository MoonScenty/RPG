<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { markBattleEntryIntentional } from '@/battle/battleEntry'
import { getFormation } from '@/lib/mercenaryApi'
import strings from '@/locales/ko'

const emit = defineEmits<{ close: []; preparing: [label: string]; toast: [message: string] }>()
const router = useRouter()

// reference_resource/design/ref4.png 참고 - 항목을 고르면 하이라이트만 되고, 실제
// "준비 중" 처리는 하단 확정 버튼을 눌러야 일어난다(즉시 토스트가 뜨던
// 이전 버전과 차이점). 각 항목마다 고유 이미지를 씀(공용 게이트 프레임
// 대신) - reference_resource/ui/의 이 4개.
// size: 아이콘 표시 크기(px). 넷 다 동일(원래 크기).
const BASE_SIZE = 192
const items: Array<{ key: string; label: string; image: string; size: number }> = [
  { key: 'trial', label: strings.home.adventure.trialBattle, image: 'Tree_idol_human.png', size: BASE_SIZE },
  { key: 'dungeon', label: strings.home.adventure.dungeon, image: 'Dark_totem_dark_shadow1.png', size: BASE_SIZE },
  { key: 'duel', label: strings.home.adventure.duel, image: 'Rock_statue_mother_grass_shadow.png', size: BASE_SIZE },
  { key: 'subjugation', label: strings.home.adventure.subjugation, image: 'Statue2_shadow3.png', size: BASE_SIZE },
]

const selectedKey = ref<string | null>(null)

async function start() {
  const item = items.find((i) => i.key === selectedKey.value)
  if (!item) return

  if (item.key === 'trial') {
    // 진형에 아무도 없으면 적만 있는 전투로 넘어가 버리니, 이동하기 전에
    // 미리 막는다(백엔드 BattleEngine::createOrResume()도 최종 방어선으로
    // 동일하게 막아둠 - 여기 체크가 실패해도 안전).
    try {
      const formation = await getFormation()
      const preset = formation.presets[String(formation.active_preset)]
      const hasAnyPlaced = preset ? Object.values(preset.slots).some((unit) => unit !== null) : false
      if (!hasAnyPlaced) {
        emit('toast', strings.home.toasts.formationRequired)
        return
      }
    } catch {
      // 진형 조회 자체가 실패하면 여기서 막지 않고 넘어간다 - 실제 전투
      // 생성 시 백엔드 검증이 다시 한번 걸러준다.
    }

    markBattleEntryIntentional()
    router.push('/battle')
    return
  }

  emit('preparing', item.label)
}
</script>

<template>
  <div class="adventure-menu">
    <div class="header">
      <span class="title">{{ strings.home.adventureButton }}</span>
      <button class="close-button" @click="emit('close')">✕</button>
    </div>

    <div class="gate-row">
      <button
        v-for="item in items"
        :key="item.key"
        class="gate-card"
        :class="{ selected: selectedKey === item.key }"
        @click="selectedKey = item.key"
      >
        <span class="banner">{{ item.label }}</span>
        <img
          class="gate"
          :src="`/assets/ui/${item.image}`"
          :alt="item.label"
          :style="{ width: item.size + 'px', height: item.size + 'px' }"
        />
      </button>
    </div>

    <div class="footer">
      <button class="start-button" :disabled="!selectedKey" @click="start">
        {{ strings.home.adventureButton }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.adventure-menu {
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
  padding: 0.6rem 0.9rem;
  flex-shrink: 0;
}

.title {
  font-weight: bold;
  font-size: 0.9rem;
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

.gate-row {
  flex: 1;
  overflow-y: auto;
  display: flex;
  align-items: center;
  justify-content: space-evenly;
  gap: 1rem;
  padding: 1.25rem;
  flex-wrap: wrap;
}

.gate-card {
  background: none;
  border: none;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-family: inherit;
  padding: 0.4rem;
  border-radius: 12px;
  transition: background 0.15s;
}

.gate-card:hover {
  background: rgba(255, 255, 255, 0.06);
}

.gate-card.selected {
  background: rgba(200, 162, 74, 0.18);
}

.banner {
  padding: 0.3rem 0.8rem;
  border: 1px solid #c8a24a;
  border-radius: 999px;
  background: rgba(20, 15, 5, 0.8);
  color: #f0e0b0;
  font-size: 0.75rem;
  white-space: nowrap;
}

.gate {
  /* width/height는 item.size로 인라인 지정(항목마다 다름) */
  object-fit: contain;
  image-rendering: pixelated;
  filter: drop-shadow(0 0 8px rgba(147, 197, 253, 0.35));
  transition: filter 0.15s;
}

.gate-card.selected .gate {
  filter: drop-shadow(0 0 14px rgba(250, 204, 21, 0.75));
}

.footer {
  flex-shrink: 0;
  display: flex;
  justify-content: flex-end;
  padding: 0.75rem 1.25rem;
  border-top: 1px solid rgba(255, 255, 255, 0.15);
}

.start-button {
  padding: 0.7rem 2.2rem;
  border: none;
  border-radius: 12px;
  background: linear-gradient(180deg, #fbbf24, #d97706);
  color: #241a00;
  font-weight: bold;
  font-size: 1.1rem;
  cursor: pointer;
  font-family: inherit;
  box-shadow: 0 4px 14px rgba(217, 119, 6, 0.45);
}

.start-button:hover {
  filter: brightness(1.08);
}

.start-button:disabled {
  opacity: 0.4;
  cursor: default;
  filter: none;
}
</style>
