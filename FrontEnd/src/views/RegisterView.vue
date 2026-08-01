<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthLayout from '@/components/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { firstErrorMessage } from '@/lib/api'
import strings from '@/locales/ko'

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref('')
const submitting = ref(false)

const auth = useAuthStore()
const router = useRouter()

async function onSubmit() {
  error.value = ''

  if (password.value !== passwordConfirmation.value) {
    error.value = strings.auth.register.passwordMismatch
    return
  }

  submitting.value = true
  try {
    await auth.register(name.value, email.value, password.value, passwordConfirmation.value)
    await router.push({ name: 'home' })
  } catch (e) {
    error.value = firstErrorMessage(e, strings.auth.register.registerFailed)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AuthLayout :title="strings.auth.register.title">
    <form @submit.prevent="onSubmit">
      <label class="field">
        {{ strings.auth.register.nameLabel }}
        <input v-model="name" type="text" required maxlength="50" autocomplete="nickname" />
      </label>
      <label class="field">
        {{ strings.auth.register.emailLabel }}
        <input v-model="email" type="email" required autocomplete="username" />
      </label>
      <label class="field">
        {{ strings.auth.register.passwordLabel }}
        <input v-model="password" type="password" required minlength="8" autocomplete="new-password" />
      </label>
      <label class="field">
        {{ strings.auth.register.passwordConfirmLabel }}
        <input
          v-model="passwordConfirmation"
          type="password"
          required
          minlength="8"
          autocomplete="new-password"
        />
      </label>
      <p v-if="error" class="form-error">{{ error }}</p>
      <button type="submit" class="btn-primary" :disabled="submitting">
        {{ submitting ? strings.auth.register.submitting : strings.auth.register.submit }}
      </button>
    </form>
    <RouterLink class="auth-switch" to="/login">{{ strings.auth.register.switchToLogin }}</RouterLink>
  </AuthLayout>
</template>
