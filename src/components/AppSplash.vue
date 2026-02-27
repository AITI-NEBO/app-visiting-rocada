<template>
  <transition name="splash-fade">
    <div v-if="visible" class="splash-screen">
      <div class="splash-bg">
        <div class="splash-orb splash-orb-1"></div>
        <div class="splash-orb splash-orb-2"></div>
        <div class="splash-orb splash-orb-3"></div>
      </div>

      <div class="splash-content">
        <div class="splash-logo-container">
          <div class="splash-ring"></div>
          <div class="splash-ring splash-ring-2"></div>
          <img src="/logo.jpeg" alt="RocadaMed" class="splash-logo" />
        </div>
        <h1 class="splash-brand">
          <span class="text-gradient">RocadaMed</span>
        </h1>
        <div class="splash-loader">
          <div class="splash-loader-bar"></div>
        </div>
        <p class="splash-subtitle">Загрузка приложения...</p>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const visible = ref(true)

onMounted(() => {
  setTimeout(() => {
    visible.value = false
  }, 1800)
})
</script>

<style scoped>
.splash-screen {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-bg-primary);
  overflow: hidden;
}

.splash-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.splash-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.3;
}

.splash-orb-1 {
  width: 300px;
  height: 300px;
  background: var(--color-primary);
  top: -80px;
  right: -80px;
  animation: float 6s ease-in-out infinite;
}

.splash-orb-2 {
  width: 250px;
  height: 250px;
  background: var(--color-accent);
  bottom: -60px;
  left: -60px;
  animation: float 8s ease-in-out infinite reverse;
}

.splash-orb-3 {
  width: 150px;
  height: 150px;
  background: #7C3AED;
  top: 50%;
  left: 30%;
  animation: float 10s ease-in-out infinite;
}

.splash-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-xl);
  position: relative;
  z-index: 1;
  animation: scaleIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.splash-logo-container {
  position: relative;
  width: 120px;
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.splash-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 2px solid transparent;
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary)) border-box;
  -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
  animation: spin 4s linear infinite;
}

.splash-ring-2 {
  inset: -8px;
  opacity: 0.4;
  animation: spin 6s linear infinite reverse;
}

.splash-logo {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  object-fit: cover;
  position: relative;
  z-index: 1;
  animation: splash-pulse 1.5s ease-in-out infinite;
}

@keyframes splash-pulse {
  0%, 100% {
    transform: scale(1);
    box-shadow: 0 0 0 0 rgba(0, 212, 170, 0.3);
  }
  50% {
    transform: scale(1.05);
    box-shadow: 0 0 30px 10px rgba(0, 212, 170, 0.15);
  }
}

.splash-brand {
  font-size: var(--font-size-3xl);
  font-weight: var(--font-weight-extrabold);
  letter-spacing: -0.5px;
}

.splash-loader {
  width: 120px;
  height: 3px;
  background: rgba(255, 255, 255, 0.08);
  border-radius: var(--radius-full);
  overflow: hidden;
}

.splash-loader-bar {
  width: 0%;
  height: 100%;
  background: linear-gradient(90deg, var(--color-accent), var(--color-primary));
  border-radius: var(--radius-full);
  animation: splash-load 1.6s ease-in-out forwards;
}

@keyframes splash-load {
  0% { width: 0%; }
  30% { width: 40%; }
  60% { width: 70%; }
  100% { width: 100%; }
}

.splash-subtitle {
  font-size: var(--font-size-sm);
  color: var(--color-text-tertiary);
  font-weight: var(--font-weight-medium);
  letter-spacing: 0.3px;
}

/* Fade out transition */
.splash-fade-leave-active {
  transition: opacity 0.5s ease;
}

.splash-fade-leave-to {
  opacity: 0;
}
</style>
