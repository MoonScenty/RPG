<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { getMercenaries, purchaseMercenary, type Mercenary } from '@/lib/mercenaryApi'
import { portraitStyle } from '@/lib/portrait'
import strings from '@/locales/ko'

const auth = useAuthStore()
const mercenaries = ref<Mercenary[]>([])
const loading = ref(true)
const message = ref('')
const purchasingId = ref<number | null>(null)

async function load() {
  loading.value = true
  try {
    mercenaries.value = await getMercenaries()
  } catch {
    message.value = strings.mercenary.purchase.loadFailed
  } finally {
    loading.value = false
  }
}

async function buy(unit: Mercenary) {
  if (unit.owned || purchasingId.value !== null) return
  message.value = ''
  purchasingId.value = unit.id
  try {
    const result = await purchaseMercenary(unit.id)
    if (auth.user) auth.user.gold = result.gold
    await load()
  } catch (e) {
    message.value = e instanceof Error ? e.message : strings.mercenary.purchase.buyFailed
  } finally {
    purchasingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="purchase-tab">
    <p v-if="message" class="message">{{ message }}</p>
    <p v-if="loading" class="hint">{{ strings.mercenary.purchase.loading }}</p>

    <div v-else class="grid">
      <div v-for="unit in mercenaries" :key="unit.id" class="card" :class="{ owned: unit.owned }">
        <div class="portrait" :style="portraitStyle(unit.sprite, 72)"></div>
        <div class="name">{{ unit.name }}</div>
        <div class="stats">
          <span>HP {{ unit.max_hp }}</span>
          <span>MP {{ unit.max_mp }}</span>
          <span>ATK {{ unit.atk }}</span>
          <span>DEF {{ unit.def }}</span>
          <span>SPD {{ unit.spd }}</span>
        </div>
        <button
          v-if="!unit.owned"
          class="buy-button"
          :disabled="purchasingId === unit.id || (auth.user?.gold ?? 0) < unit.price"
          @click="buy(unit)"
        >
          {{
            purchasingId === unit.id
              ? strings.mercenary.purchase.buying
              : unit.price === 0
                ? strings.mercenary.purchase.free
                : strings.mercenary.purchase.buyFor(unit.price.toLocaleString())
          }}
        </button>
        <div v-else class="owned-badge">{{ strings.mercenary.purchase.owned }}</div>
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
  margin-bottom: 0.75rem;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
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

.card.owned {
  opacity: 0.75;
}

.portrait {
  border-radius: 8px;
  background-color: rgba(0, 0, 0, 0.3);
}

.name {
  font-weight: bold;
}

.stats {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.4rem;
  font-size: 0.72rem;
  opacity: 0.75;
}

.buy-button {
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

.buy-button:disabled {
  opacity: 0.5;
  cursor: default;
}

.owned-badge {
  width: 100%;
  text-align: center;
  padding: 0.5rem;
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.08);
  font-size: 0.85rem;
}
</style>
