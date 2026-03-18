<template>
  <div class="callback-page">
    <div class="callback-content">
      <div class="logo-spinner">
        <img src="/logo.jpeg" alt="RocadaMed" class="spin-logo" />
      </div>

      <template v-if="error">
        <p class="callback-title error-title">Ошибка входа</p>
        <p class="callback-msg">{{ error }}</p>
        <button class="retry-btn" @click="goLogin">← Вернуться</button>
      </template>
      <template v-else>
        <p class="callback-title">Завершаем вход...</p>
        <p class="callback-msg">Пожалуйста, подождите</p>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuth } from '../composables/useAuth.js'

const router = useRouter()
const route  = useRoute()
const { handleOAuthCallback } = useAuth()

const error = ref(null)

onMounted(async () => {
  const code  = route.query.code
  const state = route.query.state
  const errParam = route.query.error

  // Bitrix24 вернул ошибку
  if (errParam) {
    error.value = `Авторизация отменена: ${errParam}`
    return
  }

  if (!code) {
    error.value = 'Отсутствует код авторизации. Попробуйте снова.'
    return
  }

  try {
    await handleOAuthCallback(code)
    // Если был redirect до входа — возвращаемся туда
    const redirect = route.query.redirect || route.query.state || '/'
    router.replace(decodeURIComponent(redirect))
  } catch (e) {
    error.value = e.message || 'Не удалось завершить авторизацию'
  }
})

function goLogin() {
  router.replace('/login')
}
</script>

<style scoped>
.callback-page {
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-bg-primary);
}

.callback-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
  padding: 40px;
  text-align: center;
}

.logo-spinner {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  overflow: hidden;
  border: 3px solid var(--color-accent);
  padding: 4px;
}

.spin-logo {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
  animation: spin 1.2s linear infinite;
}

.callback-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--color-text-primary);
}

.error-title {
  color: var(--color-danger, #ef4444);
}

.callback-msg {
  font-size: 0.9rem;
  color: var(--color-text-secondary);
}

.retry-btn {
  margin-top: 8px;
  padding: 10px 24px;
  border-radius: var(--radius-md);
  background: var(--color-bg-secondary);
  color: var(--color-text-primary);
  border: 1px solid var(--color-border);
  font-size: 0.9rem;
  cursor: pointer;
  transition: background 0.2s;
}

.retry-btn:hover {
  background: var(--color-bg-tertiary);
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
