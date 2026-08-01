<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { mountBattle } from '@/battle/pixiApp'

const router = useRouter()
const stageEl = ref<HTMLElement | null>(null)

let teardown: (() => void) | null = null

onMounted(async () => {
  if (!stageEl.value) return
  teardown = await mountBattle(stageEl.value, () => {
    router.push({ name: 'home' })
  })
})

// 결과 화면의 "메인으로"를 누르기 전에 이 화면을 떠나는 경우(뒤로가기 등)
// PixiJS Application이 남아있지 않도록 정리한다.
onUnmounted(() => {
  teardown?.()
})
</script>

<template>
  <div class="screen-wrap">
    <div ref="stageEl" class="battle-stage"></div>
  </div>
</template>

<style scoped>
/* HomeView.vue의 .screen-wrap/.main-screen과 같은 패턴 - 메인 화면과
   동일한 16:9 창 크기/위치로 맞춰서 화면 전환 시 인터페이스 크기가
   갑자기 바뀌는 느낌이 없게 한다. */
.screen-wrap {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.battle-stage {
  position: relative;
  width: 100%;
  max-width: 1280px;
  height: 100%;
  overflow: hidden;
  background: #0a0a0a;
}

@media (min-width: 900px) {
  .battle-stage {
    width: min(1280px, 177.78vh);
    height: auto;
    aspect-ratio: 16 / 9;
    box-shadow: 0 18px 60px rgba(0, 0, 0, 0.4);
  }
}
</style>
