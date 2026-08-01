<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthLayout from '@/components/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { firstErrorMessage } from '@/lib/api'
import strings from '@/locales/ko'

const email = ref('')
const password = ref('')
const error = ref('')
const submitting = ref(false)

const auth = useAuthStore()
const router = useRouter()

async function onSubmit() {
  error.value = ''
  submitting.value = true
  try {
    await auth.login(email.value, password.value)
    await router.push({ name: 'home' })
  } catch (e) {
    error.value = firstErrorMessage(e, strings.auth.login.loginFailed)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <form @submit.prevent="onSubmit">
      <label class="field">
        {{ strings.auth.login.emailLabel }}
        <input v-model="email" type="email" required autocomplete="username" />
      </label>
      <label class="field">
        {{ strings.auth.login.passwordLabel }}
        <input v-model="password" type="password" required autocomplete="current-password" />
      </label>
      <p v-if="error" class="form-error">{{ error }}</p>
      <button type="submit" class="btn-primary" :disabled="submitting">
        {{ submitting ? strings.auth.login.submitting : strings.auth.login.submit }}
      </button>
    </form>
    <RouterLink class="auth-switch" to="/register">{{ strings.auth.login.switchToRegister }}</RouterLink>
  </AuthLayout>
</template>
