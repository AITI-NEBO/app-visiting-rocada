<template>
  <div class="login-page">
    <!-- Background effects -->
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

      <!-- Features -->
      <div class="features-section animate-fade-in-up" style="animation-delay: 200ms">
        <div class="feature-item">
          <span class="material-symbols-rounded feature-icon">assignment</span>
          <span class="feature-text">Визиты на сегодня и завтра</span>
        </div>
        <div class="feature-item">
          <span class="material-symbols-rounded feature-icon">location_on</span>
          <span class="feature-text">Отправка геолокации</span>
        </div>
        <div class="feature-item">
          <span class="material-symbols-rounded feature-icon">chat</span>
          <span class="feature-text">Комментарии к визитам</span>
        </div>
      </div>

      <!-- Login button -->
      <div class="login-actions animate-fade-in-up" style="animation-delay: 400ms">
        <button class="login-btn" @click="handleLogin" :disabled="loading">
          <span v-if="!loading" class="btn-content">
            <span class="material-symbols-rounded btn-icon">login</span>
            Войти в систему
          </span>
          <span v-else class="btn-loading">
            <img src="/logo.jpeg" alt="" class="btn-logo-spinner" />
            Авторизация...
          </span>
        </button>
        <p class="login-hint">Демо-режим • Вход без пароля</p>
      </div>
    </div>

    <!-- Version -->
    <div class="login-footer animate-fade-in" style="animation-delay: 600ms">
      <span>RocadaMed PWA v1.0</span>
      <span>•</span>
      <span>IT Небо</span>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const loading = ref(false)

function handleLogin() {
  loading.value = true
  setTimeout(() => {
    localStorage.setItem('rocadamed_auth', 'true')
    router.push('/')
  }, 1200)
}
</script>

<style scoped>
.login-page {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--space-xl);
  position: relative;
  overflow: hidden;
  background: var(--color-bg-primary);
}

/* Background orbs */
.bg-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.3;
  pointer-events: none;
}

.bg-orb-1 {
  width: 300px;
  height: 300px;
  background: var(--color-primary);
  top: -80px;
  right: -80px;
  animation: float 8s ease-in-out infinite;
}

.bg-orb-2 {
  width: 250px;
  height: 250px;
  background: var(--color-accent);
  bottom: -60px;
  left: -60px;
  animation: float 6s ease-in-out infinite reverse;
}

.bg-orb-3 {
  width: 150px;
  height: 150px;
  background: #7C3AED;
  top: 40%;
  left: 50%;
  animation: float 10s ease-in-out infinite;
}

.login-content {
  width: 100%;
  max-width: 380px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2xl);
  position: relative;
  z-index: 1;
}

/* Logo */
.logo-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-lg);
}

.logo-wrapper {
  position: relative;
  width: 120px;
  height: 120px;
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
  inset: -8px;
  opacity: 0.4;
  animation: spin 12s linear infinite reverse;
}

.logo-icon {
  width: 88px;
  height: 88px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  overflow: hidden;
  position: relative;
  z-index: 1;
}

.logo-img {
  width: 100%;
  height: 100%;
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

/* Features */
.features-section {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  width: 100%;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-md) var(--space-base);
  background: rgba(255, 255, 255, 0.04);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  transition: background var(--transition-fast), transform var(--transition-fast);
}

.feature-item:hover {
  background: rgba(255, 255, 255, 0.07);
  transform: translateX(4px);
}

.feature-icon {
  font-size: 22px;
  color: var(--color-accent);
}

.feature-text {
  font-size: var(--font-size-base);
  color: var(--color-text-secondary);
  font-weight: var(--font-weight-medium);
}

/* Login actions */
.login-actions {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-base);
}

.login-btn {
  width: 100%;
  height: 52px;
  border-radius: var(--radius-lg);
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  color: white;
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-semibold);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform var(--transition-fast), box-shadow var(--transition-fast);
  box-shadow: var(--shadow-glow-accent);
}

.login-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 0 30px var(--color-accent-glow);
}

.login-btn:active:not(:disabled) {
  transform: scale(0.97);
}

.login-btn:disabled {
  opacity: 0.8;
}

.btn-content, .btn-loading {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
}

.btn-icon {
  font-size: 22px;
}

.btn-logo-spinner {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  object-fit: cover;
  animation: spin 1s linear infinite;
}

.login-hint {
  font-size: var(--font-size-sm);
  color: var(--color-text-tertiary);
}

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
  .login-content {
    max-width: 420px;
  }

  .logo-wrapper {
    width: 140px;
    height: 140px;
  }

  .logo-icon {
    width: 100px;
    height: 100px;
  }

  .brand-name {
    font-size: 2.5rem;
  }
}
</style>
