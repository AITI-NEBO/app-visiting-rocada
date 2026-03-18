<template>
  <div class="login-page">
    <!-- Background orbs -->
    <div class="bg-orb bg-orb-1"></div>
    <div class="bg-orb bg-orb-2"></div>
    <div class="bg-orb bg-orb-3"></div>

    <div class="login-content">
      <!-- Logo -->
      <div class="logo-section animate-fade-in-up">
        <div class="logo-wrapper">
          <div class="logo-ring"></div>
          <div class="logo-ring logo-ring-outer"></div>
          <div class="logo-icon">
            <img src="/logo.jpeg" alt="RocadaMed" class="logo-img" />
          </div>
        </div>
        <h1 class="brand-name">
          <span class="text-gradient">RocadaMed</span>
        </h1>
        <p class="brand-tagline">Мобильное приложение для<br/>выездных сотрудников</p>
      </div>

      <!-- Auth box -->
      <div class="auth-box animate-fade-in-up" style="animation-delay: 200ms">
        <!-- Error -->
        <div v-if="errorMsg" class="error-banner">
          <span class="material-symbols-rounded error-icon">error</span>
          <span>{{ errorMsg }}</span>
        </div>

        <!-- OAuth кнопка -->
        <button
          class="oauth-btn"
          :disabled="loading"
          @click="handleOAuth"
        >
          <span v-if="loading" class="btn-loading">
            <span class="material-symbols-rounded spinning">progress_activity</span>
            Выполняется вход...
          </span>
          <span v-else class="btn-content">
            <span class="b24-logo">
              <img src="/logo.jpeg" alt="" class="oauth-logo" />
            </span>
            Войти через Битрикс24
          </span>
        </button>

        <p class="portal-hint">
          Портал: <a href="https://office.rocadatech.ru" target="_blank" class="portal-link">office.rocadatech.ru</a>
        </p>
      </div>
    </div>

    <!-- Footer -->
    <div class="login-footer">
      <span class="material-symbols-rounded" style="font-size:16px">lock</span>
      Защищённое подключение
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const router = useRouter()
const route  = useRoute()
const auth   = useAuth()

const loading  = ref(false)
const errorMsg = ref('')

function handleOAuth() {
  errorMsg.value = ''
  loading.value  = true
  try {
    auth.loginOAuth()
    // loginOAuth делает window.location.href, дальше не выполняется
  } catch (err) {
    errorMsg.value = err.message
    loading.value  = false
  }
}
</script>

<style scoped>
/* Page */
.login-page {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--space-xl) var(--space-lg);
  position: relative;
  overflow: hidden;
  background: var(--color-bg-primary);
}

/* Background orbs */
.bg-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  pointer-events: none;
  animation: float 6s ease-in-out infinite;
}
.bg-orb-1 {
  width: 300px; height: 300px; top: -80px; right: -60px;
  background: radial-gradient(circle, rgba(99,102,241,.25), transparent 70%);
}
.bg-orb-2 {
  width: 250px; height: 250px; bottom: -60px; left: -40px;
  background: radial-gradient(circle, rgba(16,185,129,.15), transparent 70%);
  animation-delay: -3s;
}
.bg-orb-3 {
  width: 200px; height: 200px; top: 40%; left: 50%;
  transform: translateX(-50%);
  background: radial-gradient(circle, rgba(139,92,246,.1), transparent 70%);
  animation-delay: -1.5s;
}

/* Content */
.login-content {
  width: 100%;
  max-width: 380px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-xl);
  z-index: 1;
}

/* Logo */
.logo-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-md);
}

.logo-wrapper {
  position: relative;
  width: 96px; height: 96px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 2px solid transparent;
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary)) border-box;
  -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
  animation: spin 8s linear infinite;
}
.logo-ring-outer {
  inset: -8px; opacity: .4;
  animation: spin 12s linear infinite reverse;
}

.logo-icon {
  width: 72px; height: 72px;
  border-radius: 50%;
  overflow: hidden;
  position: relative;
  z-index: 1;
}
.logo-img {
  width: 100%; height: 100%;
  object-fit: cover;
  animation: float 4s ease-in-out infinite;
}

.brand-name {
  font-size: var(--font-size-3xl);
  font-weight: var(--font-weight-extrabold);
  letter-spacing: -0.5px;
}
.brand-tagline {
  font-size: var(--font-size-base);
  color: var(--color-text-secondary);
  text-align: center;
  line-height: var(--line-height-relaxed);
}

/* Auth box */
.auth-box {
  width: 100%;
  background: rgba(255,255,255,.04);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  padding: var(--space-xl);
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
  align-items: center;
}

/* Error */
.error-banner {
  width: 100%;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 12px 14px;
  background: rgba(239,68,68,.12);
  border: 1px solid rgba(239,68,68,.3);
  border-radius: var(--radius-md);
  color: #fca5a5;
  font-size: var(--font-size-sm);
  line-height: 1.4;
}
.error-icon { font-size: 18px; flex-shrink: 0; color: #ef4444; margin-top: 1px; }

/* OAuth button */
.oauth-btn {
  width: 100%;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  border-radius: var(--radius-lg);
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  color: white;
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-semibold);
  cursor: pointer;
  transition: transform .15s, box-shadow .15s, opacity .15s;
  box-shadow: var(--shadow-glow-accent);
  border: none;
}
.oauth-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 0 36px var(--color-accent-glow);
}
.oauth-btn:active:not(:disabled) { transform: scale(.97); }
.oauth-btn:disabled { opacity: .6; cursor: not-allowed; }

.btn-content, .btn-loading {
  display: flex; align-items: center; gap: 10px;
}

.b24-logo {
  width: 26px; height: 26px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid rgba(255,255,255,.4);
  flex-shrink: 0;
}
.oauth-logo { width: 100%; height: 100%; object-fit: cover; }

.spinning { animation: spin .8s linear infinite; font-size: 20px; }

/* Hint */
.portal-hint {
  font-size: var(--font-size-xs);
  color: var(--color-text-tertiary);
}
.portal-link { color: var(--color-accent); text-decoration: underline dotted; }

/* Footer */
.login-footer {
  position: absolute;
  bottom: var(--space-xl);
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: var(--font-size-xs);
  color: var(--color-text-tertiary);
}

/* Desktop */
@media (min-width: 768px) {
  .login-content { max-width: 420px; }
  .logo-wrapper { width: 110px; height: 110px; }
  .logo-icon { width: 80px; height: 80px; }
  .brand-name { font-size: 2.5rem; }
}
</style>
