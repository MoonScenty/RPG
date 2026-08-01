<script setup lang="ts">
defineProps<{ title?: string }>()
</script>

<template>
  <div class="auth-screen">
    <div class="auth-card">
      <h1 class="auth-title">{{ title ?? 'RPG 만들기 좋은 날' }}</h1>
      <slot />
    </div>
  </div>
</template>

<style scoped>
.auth-screen {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.auth-card {
  width: 100%;
  max-width: 360px;
  background: var(--color-background-soft);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  padding: 2rem;
}

.auth-title {
  text-align: center;
  margin-bottom: 1.5rem;
  font-size: 1.5rem;
  color: var(--color-heading);
}
</style>
