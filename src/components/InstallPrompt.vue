<template>
  <transition name="install-slide">
    <div v-if="showPrompt" class="install-prompt glass">
      <button class="install-close" @click="dismiss" aria-label="Закрыть">
        <span class="material-symbols-rounded">close</span>
      </button>
      <div class="install-content">
        <div class="install-logo-wrap">
          <img src="/logo.jpeg" alt="RocadaMed" class="install-logo" />
          <div class="install-logo-ring"></div>
        </div>
        <div class="install-text">
          <h3 class="install-title">Установите приложение</h3>
          <p class="install-desc">Быстрый доступ к визитам прямо с главного экрана</p>
        </div>
      </div>
      <button class="install-btn" @click="install">
        <span class="material-symbols-rounded install-btn-icon">download</span>
        Установить
      </button>
    </div>
  </transition>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const showPrompt = ref(false)
let deferredPrompt = null

function handleBeforeInstall(e) {
  e.preventDefault()
  deferredPrompt = e

  const dismissed = localStorage.getItem('rocadamed_install_dismissed')
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone

  if (!dismissed && !isStandalone) {
    setTimeout(() => {
      showPrompt.value = true
    }, 3000)
  }
}

async function install() {
  if (!deferredPrompt) return
  deferredPrompt.prompt()
  const { outcome } = await deferredPrompt.userChoice
  if (outcome === 'accepted') {
    showPrompt.value = false
  }
  deferredPrompt = null
}

function dismiss() {
  showPrompt.value = false
  localStorage.setItem('rocadamed_install_dismissed', 'true')
}

onMounted(() => {
  window.addEventListener('beforeinstallprompt', handleBeforeInstall)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeinstallprompt', handleBeforeInstall)
})
</script>

<style scoped>
.install-prompt {
  position: fixed;
  bottom: calc(var(--bottom-nav-height) + var(--safe-area-bottom) + var(--space-base));
  left: var(--space-base);
  right: var(--space-base);
  max-width: 420px;
  margin: 0 auto;
  padding: var(--space-lg);
  border-radius: var(--radius-xl);
  background: var(--color-bg-card);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border: 1px solid var(--color-border-light);
  box-shadow: var(--shadow-xl);
  z-index: var(--z-modal);
  display: flex;
  flex-direction: column;
  gap: var(--space-base);
}

@media (min-width: 768px) {
  .install-prompt {
    bottom: var(--space-xl);
    left: auto;
    right: var(--space-xl);
    max-width: 360px;
  }
}

.install-close {
  position: absolute;
  top: var(--space-sm);
  right: var(--space-sm);
  width: 32px;
  height: 32px;
  border-radius: var(--radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text-tertiary);
  transition: background var(--transition-fast), color var(--transition-fast);
}

.install-close:hover {
  background: rgba(255, 255, 255, 0.08);
  color: var(--color-text-primary);
}

.install-content {
  display: flex;
  align-items: center;
  gap: var(--space-base);
}

.install-logo-wrap {
  position: relative;
  width: 52px;
  height: 52px;
  flex-shrink: 0;
}

.install-logo {
  width: 52px;
  height: 52px;
  border-radius: var(--radius-md);
  object-fit: cover;
  position: relative;
  z-index: 1;
}

.install-logo-ring {
  position: absolute;
  inset: -3px;
  border-radius: calc(var(--radius-md) + 3px);
  border: 2px solid transparent;
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary)) border-box;
  -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
  animation: spin 6s linear infinite;
}

.install-title {
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-bold);
  color: var(--color-text-primary);
  margin-bottom: 2px;
}

.install-desc {
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  line-height: var(--line-height-normal);
}

.install-btn {
  width: 100%;
  height: 44px;
  border-radius: var(--radius-md);
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  color: white;
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-semibold);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  transition: transform var(--transition-fast), box-shadow var(--transition-fast);
  box-shadow: var(--shadow-glow-accent);
}

.install-btn:active {
  transform: scale(0.97);
}

.install-btn-icon {
  font-size: 20px;
}

/* Transition */
.install-slide-enter-active {
  animation: slideInUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.install-slide-leave-active {
  animation: slideOutDown 0.3s ease forwards;
}

@keyframes slideInUp {
  from {
    opacity: 0;
    transform: translateY(100%);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideOutDown {
  from {
    opacity: 1;
    transform: translateY(0);
  }
  to {
    opacity: 0;
    transform: translateY(100%);
  }
}
</style>
