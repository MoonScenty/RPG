import { createRouter, createWebHistory } from 'vue-router'
import { consumeBattleEntryIntent } from '@/battle/battleEntry'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('../views/HomeView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../views/RegisterView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/battle',
      name: 'battle',
      component: () => import('../views/BattleView.vue'),
      meta: { requiresAuth: true },
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.ensureLoaded()

  if (to.meta.requiresAuth && !auth.user) {
    return { name: 'login' }
  }
  if (to.meta.guestOnly && auth.user) {
    return { name: 'home' }
  }
  // /battle은 홈 화면의 "시험전투"/"이어서 전투" 버튼을 실제로 눌렀을 때만
  // 들어갈 수 있다 - 새로고침 등으로 직접 진입하면(플래그가 리셋돼있음)
  // 남아있는 배틀 세션을 조용히 자동 재개해버리는 걸 막기 위함(battleEntry.ts 참고).
  if (to.name === 'battle' && !consumeBattleEntryIntent()) {
    return { name: 'home' }
  }
})

export default router
