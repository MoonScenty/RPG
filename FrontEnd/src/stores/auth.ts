import { defineStore } from 'pinia'
import { apiRequest } from '@/lib/api'

export interface AuthUser {
  id: number
  name: string
  email: string
  level: number
  exp: number
  exp_to_next: number
  gold: number
  honor_points: number
  fatigue: number
  max_fatigue: number
  icon_path: string | null
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AuthUser | null,
    initialized: false,
  }),
  actions: {
    /** 앱 최초 진입 시 한 번, 남아있는 세션이 있는지 확인한다. */
    async ensureLoaded(): Promise<void> {
      if (this.initialized) return
      try {
        const { user } = await apiRequest<{ user: AuthUser }>('GET', '/api/me')
        this.user = user
      } catch {
        this.user = null
      } finally {
        this.initialized = true
      }
    },

    async login(email: string, password: string): Promise<void> {
      const { user } = await apiRequest<{ user: AuthUser }>('POST', '/api/login', { email, password })
      this.user = user
      this.initialized = true
    },

    async register(
      name: string,
      email: string,
      password: string,
      passwordConfirmation: string,
    ): Promise<void> {
      const { user } = await apiRequest<{ user: AuthUser }>('POST', '/api/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      })
      this.user = user
      this.initialized = true
    },

    async logout(): Promise<void> {
      await apiRequest('POST', '/api/logout')
      this.user = null
    },
  },
})
