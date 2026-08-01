<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { markBattleEntryIntentional } from '@/battle/battleEntry'
import { getActiveBattleId } from '@/lib/battleApi'
import { useAuthStore } from '@/stores/auth'
import { STAGE_HEIGHT, STAGE_WIDTH } from '@/battle/layout'
import MercenaryPanel from '@/components/mercenary/MercenaryPanel.vue'
import CraftingPanel from '@/components/crafting/CraftingPanel.vue'
import InventoryPanel from '@/components/inventory/InventoryPanel.vue'
import type { Workshop } from '@/lib/craftingApi'
import AdventureMenu from '@/components/AdventureMenu.vue'
import strings from '@/locales/ko'

const auth = useAuthStore()
const router = useRouter()

// 메인 화면을 배틀 화면(PixiJS 1280x720 고정 캔버스)과 동일한 방식으로 다룬다 -
// .main-screen을 항상 1280x720 고정 크기로 그린 뒤 컨테이너 크기에 맞는 배율만큼
// transform: scale()로 통째로 축소/확대한다. 예전엔 박스만 반응형으로 커졌다
// 작아졌다 하고 안의 버튼/패널은 rem 단위라 그대로라, 박스 크기에 따라 flex-wrap이
// 다르게 걸려 리사이즈할 때마다 레이아웃이 흐트러지는 문제가 있었다(사용자 확인) -
// 이제는 박스 전체가 한 덩어리로 스케일되니 어떤 화면 크기에서도 항상 같은 상대
// 위치/비율을 유지한다.
const screenWrapEl = ref<HTMLElement | null>(null)
const homeScale = ref(1)
let resizeObserver: ResizeObserver | null = null

function updateHomeScale(): void {
  const el = screenWrapEl.value
  if (!el) return
  const { width, height } = el.getBoundingClientRect()
  // 1을 넘지 않게 막아서(원본 1280x720보다 확대 안 함) 큰 화면에서 계속
  // 커지지 않게 한다 - 배틀 화면의 max-width:1280px과 같은 역할.
  homeScale.value = Math.min(width / STAGE_WIDTH, height / STAGE_HEIGHT, 1)
}

onMounted(() => {
  if (!screenWrapEl.value) return
  updateHomeScale()
  resizeObserver = new ResizeObserver(updateHomeScale)
  resizeObserver.observe(screenWrapEl.value)
})

onUnmounted(() => {
  resizeObserver?.disconnect()
})

// 새로고침 등으로 /battle에 직접 진입하는 걸 막았으니(router/index.ts 가드
// 참고), 남아있는 전투가 있으면 여기서 눈에 띄게 보여주고 실제 진입은 이
// 버튼을 눌렀을 때만 일어나게 한다 - BGM 자동재생 정책도 이 클릭이 진짜
// 사용자 제스처라 문제없이 통과한다.
const activeBattleId = ref<number | null>(null)

onMounted(async () => {
  try {
    const { id } = await getActiveBattleId()
    activeBattleId.value = id
  } catch {
    // 조회 실패는 조용히 무시 - 버튼을 안 보여주는 것 정도의 영향만 있음.
  }
})

function onContinueBattle() {
  markBattleEntryIntentional()
  router.push('/battle')
}

const toast = ref('')
let toastTimer: ReturnType<typeof setTimeout> | null = null
function showToast(message: string) {
  toast.value = message
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 1800)
}

const settingsOpen = ref(false)

async function onLogout() {
  settingsOpen.value = false
  await auth.logout()
  await router.push({ name: 'login' })
}

// 경매장/친구 관리/제빵소 - 아직 백엔드가 없는 기능들. 서버 붙는 대로 각자
// 실제 화면으로 교체. 용병소/연구소/대장간은 구현됨 - 페이지 이동이 아니라
// village 영역(일일 임무 자리)에 패널로 열린다(연구소/대장간은 같은
// CraftingPanel을 workshop만 다르게 줘서 재사용).
// iconX/iconY는 mz_project/img/system/IconSet.png(32px 그리드) 안에서의
// 좌상단 픽셀 좌표.
type PanelKey = 'mercenary' | 'lab' | 'smithy' | 'inventory'
const menuItems: Array<{ label: string; iconX: number; iconY: number; panel?: PanelKey }> = [
  { label: strings.home.menu.auctionHouse, iconX: 32, iconY: 384 },
  { label: strings.home.menu.friends, iconX: 224, iconY: 0 },
  // img/icons/274.png(IconSet.png 32px 그리드, 16열 기준 274번: col=274%16=2,
  // row=274/16=17 -> x=64,y=544).
  { label: strings.home.menu.inventory, iconX: 64, iconY: 544, panel: 'inventory' },
  { label: strings.home.menu.bakery, iconX: 320, iconY: 544 },
  { label: strings.home.menu.lab, iconX: 0, iconY: 352, panel: 'lab' },
  { label: strings.home.menu.blacksmith, iconX: 480, iconY: 416, panel: 'smithy' },
  { label: strings.home.menu.mercenary, iconX: 352, iconY: 480, panel: 'mercenary' },
]

const activePanel = ref<PanelKey | 'adventure' | null>(null)

function onMenuClick(item: (typeof menuItems)[number]) {
  if (item.panel) {
    activePanel.value = item.panel
    return
  }
  showToast(strings.home.toasts.itemPreparing(item.label))
}

// 명예점수=배지, 골드=동전, 피로도=유령(지친 상태 표현)
const currencyIcons = {
  honor: { x: 288, y: 160 },
  gold: { x: 64, y: 704 },
  fatigue: { x: 448, y: 0 },
}

// 공지사항=느낌표, 메시지=편지봉투, 설정=렌치+드라이버
const topIcons = {
  notice: { x: 320, y: 864 },
  message: { x: 0, y: 384 },
  settings: { x: 64, y: 480 },
}

// IconSet.png는 32px 그리드 - size는 화면에 표시할 최종 크기(px)이고,
// 그 비율(size/32)만큼 크롭 좌표와 시트 전체 크기를 같이 확대해야
// 아이콘 전체가 축소/확대 없이 깨끗하게 잘려 나온다.
const ICON_SHEET_SIZE = { width: 512, height: 5344 }
function iconStyle(x: number, y: number, size: number) {
  const scale = size / 32
  return {
    width: `${size}px`,
    height: `${size}px`,
    backgroundPosition: `-${x * scale}px -${y * scale}px`,
    backgroundSize: `${ICON_SHEET_SIZE.width * scale}px ${ICON_SHEET_SIZE.height * scale}px`,
  }
}

const expPercent = computed(() => {
  const u = auth.user
  if (!u || u.exp_to_next <= 0) return 0
  return Math.min(100, Math.round((u.exp / u.exp_to_next) * 100))
})

const partyInitial = computed(() => (auth.user?.name ?? '?').charAt(0).toUpperCase())
</script>

<template>
  <div class="screen-wrap" ref="screenWrapEl">
    <div class="main-screen" :style="{ transform: `scale(${homeScale})` }">
    <div class="top-bar">
      <div class="party-panel">
        <div class="party-icon">{{ partyInitial }}</div>
        <div class="party-info">
          <div class="party-name-row">
            <span class="party-level">LV{{ auth.user?.level }}</span>
            <span class="party-name">{{ auth.user?.name }}</span>
          </div>
          <div class="exp-bar">
            <div class="exp-bar-fill" :style="{ width: expPercent + '%' }"></div>
          </div>
        </div>
      </div>

      <div class="currency-row">
        <div class="currency" :title="strings.home.currencies.honor">
          <span class="sprite-icon" :style="iconStyle(currencyIcons.honor.x, currencyIcons.honor.y, 32)"></span>
          <span>{{ auth.user?.honor_points }}</span>
        </div>
        <div class="currency" :title="strings.home.currencies.gold">
          <span class="sprite-icon" :style="iconStyle(currencyIcons.gold.x, currencyIcons.gold.y, 32)"></span>
          <span>{{ auth.user?.gold }}</span>
        </div>
        <div class="currency" :title="strings.home.currencies.fatigue">
          <span class="sprite-icon" :style="iconStyle(currencyIcons.fatigue.x, currencyIcons.fatigue.y, 32)"></span>
          <span>{{ auth.user?.fatigue }}/{{ auth.user?.max_fatigue }}</span>
        </div>
      </div>

      <div class="icon-row">
        <button class="icon-button" @click="showToast(strings.home.toasts.noticePreparing)">
          <span class="sprite-icon" :style="iconStyle(topIcons.notice.x, topIcons.notice.y, 32)"></span>
        </button>
        <button class="icon-button" @click="showToast(strings.home.toasts.messagePreparing)">
          <span class="sprite-icon" :style="iconStyle(topIcons.message.x, topIcons.message.y, 32)"></span>
        </button>
        <div class="settings-wrap">
          <button class="icon-button" @click="settingsOpen = !settingsOpen">
            <span class="sprite-icon" :style="iconStyle(topIcons.settings.x, topIcons.settings.y, 32)"></span>
          </button>
          <div v-if="settingsOpen" class="settings-dropdown">
            <button class="settings-item" @click="onLogout">{{ strings.home.logout }}</button>
          </div>
        </div>
      </div>
    </div>

    <div class="village">
      <MercenaryPanel v-if="activePanel === 'mercenary'" @close="activePanel = null" />
      <InventoryPanel v-else-if="activePanel === 'inventory'" @close="activePanel = null" />
      <CraftingPanel
        v-else-if="activePanel === 'lab' || activePanel === 'smithy'"
        :key="activePanel"
        :workshop="(activePanel as Workshop)"
        @close="activePanel = null"
      />
      <AdventureMenu
        v-else-if="activePanel === 'adventure'"
        @close="activePanel = null"
        @preparing="showToast(strings.home.toasts.itemPreparing($event))"
        @toast="showToast($event)"
      />
      <div v-else class="quest-panel">
        <div class="quest-panel-title">{{ strings.home.quest.title }}</div>
        <p class="quest-empty">{{ strings.home.quest.empty }}</p>
      </div>
    </div>

    <div class="bottom-bar">
      <div class="menu-row">
        <button
          v-for="item in menuItems"
          :key="item.label"
          class="menu-button"
          @click="onMenuClick(item)"
        >
          <span class="sprite-icon" :style="iconStyle(item.iconX, item.iconY, 48)"></span>
          <span class="menu-label">{{ item.label }}</span>
        </button>
      </div>
      <button class="adventure-button" @click="activePanel = 'adventure'">{{ strings.home.adventureButton }}</button>
    </div>

    <Transition name="fade">
      <div v-if="toast" class="toast">{{ toast }}</div>
    </Transition>

    <!-- /battle 새로고침 시 자동 재개를 막은 대신, 여기서 눈에 띄게 이어할 수
         있게 한다(router/index.ts의 배틀 진입 가드 참고). -->
    <div v-if="activeBattleId" class="continue-battle-overlay">
      <div class="continue-battle-card">
        <p class="continue-battle-message">{{ strings.home.continueBattle.message }}</p>
        <button class="continue-battle-button" @click="onContinueBattle">
          {{ strings.home.continueBattle.button }}
        </button>
      </div>
    </div>
    </div>
  </div>
</template>

<style scoped>
/* AuthLayout.vue의 .auth-screen과 같은 패턴 - 이 뷰가 스스로 뷰포트를
   채우고 내용을 중앙 정렬한다 (#app 자체는 다른 뷰와 공유하므로 정렬을
   강제하지 않는다). */
.screen-wrap {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.main-screen {
  /* 배틀 화면(PixiJS 1280x720 고정 캔버스)과 동일하게, 항상 이 고정 크기로
     그린 뒤 <script setup>에서 계산한 배율만큼 transform: scale()로 통째로
     축소/확대한다(homeScale, ResizeObserver 기반) - 박스 크기에 따라 안의
     rem 단위 요소들이 다르게 줄바꿈되며 레이아웃이 흐트러지던 문제(사용자
     확인)를 없애기 위함. transform은 레이아웃 크기 자체는 안 바꾸므로
     .screen-wrap의 flex 중앙 정렬이 스케일된 결과에도 그대로 적용된다.
     flex-shrink/grow를 0으로 고정해서 flexbox가 별도로 크기를 건드리지
     않게 막는다(스케일 계산과 이중으로 충돌하지 않도록). */
  position: relative;
  width: 1280px;
  height: 720px;
  flex-shrink: 0;
  flex-grow: 0;
  transform-origin: center center;
  display: flex;
  flex-direction: column;
  background: url('/assets/backgrounds/main.png') center/cover no-repeat;
  color: #f1f5f9;
  /* 메인 화면만 우선 1.5배 스케일 - 아래 모든 치수는 원래 값 * 1.5 */
  font-size: 1.35rem;
  overflow: hidden;
  box-shadow: 0 18px 60px rgba(0, 0, 0, 0.4);
}

.top-bar {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1.125rem;
  padding: 1.125rem;
  flex-wrap: wrap;
}

.party-panel {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  background: rgba(0, 0, 0, 0.45);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 15px;
  padding: 0.75rem 1.125rem;
}

.party-icon {
  width: 66px;
  height: 66px;
  border-radius: 50%;
  background: #334155;
  border: 3px solid #38bdf8;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 1.8rem;
  flex-shrink: 0;
}

.party-info {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  min-width: 180px;
}

.party-name-row {
  display: flex;
  align-items: baseline;
  gap: 0.6rem;
  font-size: 1.275rem;
}

.party-level {
  color: #7dd3fc;
  font-weight: bold;
}

.exp-bar {
  width: 210px;
  height: 9px;
  border-radius: 4.5px;
  background: rgba(255, 255, 255, 0.15);
  overflow: hidden;
}

.exp-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #38bdf8, #0ea5e9);
  transition: width 0.3s;
}

.currency-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.currency {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  background: rgba(0, 0, 0, 0.45);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 12px;
  padding: 0.525rem 0.9rem;
  font-size: 1.275rem;
  white-space: nowrap;
}

.sprite-icon {
  background-image: url('/assets/icons/IconSet.png');
  background-repeat: no-repeat;
  image-rendering: pixelated;
  flex-shrink: 0;
}

.icon-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  position: relative;
}

.icon-button {
  width: 57px;
  height: 57px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(0, 0, 0, 0.45);
  color: #f1f5f9;
  cursor: pointer;
}

.icon-button:hover {
  background: rgba(0, 0, 0, 0.6);
}

.settings-wrap {
  position: relative;
}

.settings-dropdown {
  position: absolute;
  top: 66px;
  right: 0;
  background: #1e293b;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 12px;
  padding: 0.375rem;
  min-width: 150px;
  z-index: 10;
}

.settings-item {
  display: block;
  width: 100%;
  padding: 0.75rem 0.9rem;
  background: none;
  border: none;
  color: #f1f5f9;
  text-align: left;
  cursor: pointer;
  border-radius: 9px;
  font-size: 1.275rem;
  font-family: inherit;
}

.settings-item:hover {
  background: rgba(255, 255, 255, 0.1);
}

.village {
  flex: 1;
  /* flex 항목 기본값(min-height:auto)이 콘텐츠보다 안 줄어들게 막아서
     패널이 넘치면 이 밑의 bottom-bar를 통째로 밀어냈었다 - 0으로 풀어야
     자식의 overflow-y:auto가 실제로 스크롤을 만든다. */
  min-height: 0;
  position: relative;
  padding: 1.5rem;
  display: flex;
  justify-content: flex-end;
}

.quest-panel {
  align-self: flex-start;
  background: rgba(0, 0, 0, 0.45);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 15px;
  padding: 0.9rem 1.125rem;
  min-width: 240px;
}

.quest-panel-title {
  font-size: 1.275rem;
  font-weight: bold;
  margin-bottom: 0.6rem;
}

.quest-empty {
  font-size: 1.17rem;
  opacity: 0.7;
}

.bottom-bar {
  display: flex;
  align-items: center;
  gap: 1.125rem;
  padding: 1.125rem;
  background: rgba(0, 0, 0, 0.5);
  border-top: 1px solid rgba(255, 255, 255, 0.15);
  flex-wrap: wrap;
}

.menu-row {
  display: flex;
  gap: 0.75rem;
  flex: 1;
  flex-wrap: wrap;
}

.menu-button {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.225rem;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 12px;
  padding: 0.6rem 0.9rem;
  color: #f1f5f9;
  cursor: pointer;
  font-size: 1.05rem;
  min-width: 84px;
  font-family: inherit;
}

.menu-button:hover {
  background: rgba(255, 255, 255, 0.14);
}

.adventure-button {
  padding: 1.5rem 3.2rem;
  border: none;
  border-radius: 14px;
  background: linear-gradient(180deg, #fbbf24, #d97706);
  color: #241a00;
  font-weight: bold;
  font-size: 2rem;
  cursor: pointer;
  white-space: nowrap;
  font-family: inherit;
  box-shadow: 0 4px 14px rgba(217, 119, 6, 0.45);
}

.adventure-button:hover {
  filter: brightness(1.08);
}

.toast {
  position: absolute;
  bottom: 135px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(0, 0, 0, 0.85);
  color: #fff;
  padding: 0.9rem 1.5rem;
  border-radius: 12px;
  font-size: 1.275rem;
  z-index: 20;
  white-space: nowrap;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.continue-battle-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.55);
  z-index: 30;
}

.continue-battle-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.2rem;
  background: rgba(15, 15, 20, 0.92);
  border: 1px solid rgba(200, 162, 74, 0.6);
  border-radius: 18px;
  padding: 2.2rem 2.8rem;
  box-shadow: 0 18px 50px rgba(0, 0, 0, 0.5);
}

.continue-battle-message {
  font-size: 1.35rem;
  color: #f1f5f9;
}

.continue-battle-button {
  padding: 1.1rem 2.6rem;
  border: none;
  border-radius: 14px;
  background: linear-gradient(180deg, #fbbf24, #d97706);
  color: #241a00;
  font-weight: bold;
  font-size: 1.6rem;
  cursor: pointer;
  white-space: nowrap;
  font-family: inherit;
  box-shadow: 0 4px 14px rgba(217, 119, 6, 0.45);
}

.continue-battle-button:hover {
  filter: brightness(1.08);
}
</style>
