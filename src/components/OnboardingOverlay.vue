<template>
  <transition name="onboarding-fade">
    <div v-if="show" class="onboarding-overlay">
      <div class="onboarding-bg">
        <div class="onboarding-orb onboarding-orb-1"></div>
        <div class="onboarding-orb onboarding-orb-2"></div>
      </div>

      <div class="onboarding-card">
        <!-- Step indicators -->
        <div class="onboarding-dots">
          <span
            v-for="(_, i) in steps"
            :key="i"
            class="dot"
            :class="{ active: currentStep === i, done: currentStep > i }"
          ></span>
        </div>

        <!-- Step content -->
        <transition :name="slideDirection" mode="out-in">
          <div class="onboarding-step" :key="currentStep">
            <div class="step-icon-wrap" :style="{ background: steps[currentStep].iconBg }">
              <img
                v-if="currentStep === 0"
                src="/logo.jpeg"
                alt="RocadaMed"
                class="step-logo-img"
              />
              <span v-else class="material-symbols-rounded step-icon" :style="{ color: steps[currentStep].iconColor }">
                {{ steps[currentStep].icon }}
              </span>
            </div>
            <h2 class="step-title">{{ steps[currentStep].title }}</h2>
            <p class="step-desc">{{ steps[currentStep].description }}</p>
          </div>
        </transition>

        <!-- Actions -->
        <div class="onboarding-actions">
          <button v-if="currentStep > 0" class="onboarding-btn-back" @click="prev">
            <span class="material-symbols-rounded">arrow_back</span>
          </button>
          <div v-else></div>
          <button class="onboarding-btn-next" @click="next">
            <span v-if="isLastStep">Начать работу</span>
            <span v-else>Далее</span>
            <span class="material-symbols-rounded onboarding-btn-arrow">
              {{ isLastStep ? 'check' : 'arrow_forward' }}
            </span>
          </button>
        </div>

        <!-- Skip -->
        <button class="onboarding-skip" @click="finish">
          Пропустить
        </button>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { ref, computed } from 'vue'

const show = ref(false)
const currentStep = ref(0)
const slideDirection = ref('slide-left')

const steps = [
  {
    icon: '',
    title: 'Добро пожаловать!',
    description: 'RocadaMed — ваш помощник для управления выездными визитами. Давайте познакомимся с основными функциями.',
    iconBg: 'linear-gradient(135deg, rgba(0, 212, 170, 0.15), rgba(0, 102, 255, 0.15))',
    iconColor: 'var(--color-accent)'
  },
  {
    icon: 'assignment',
    title: 'Визиты и расписание',
    description: 'На главной вы увидите визиты на сегодня и завтра. Нажмите на карточку визита, чтобы увидеть детали — адрес, время и описание.',
    iconBg: 'rgba(0, 212, 170, 0.12)',
    iconColor: 'var(--color-accent)'
  },
  {
    icon: 'location_on',
    title: 'Геолокация',
    description: 'При прибытии к пациенту отправьте свою геолокацию одним нажатием. Это подтвердит ваше присутствие на месте.',
    iconBg: 'rgba(0, 102, 255, 0.12)',
    iconColor: 'var(--color-primary)'
  },
  {
    icon: 'chat',
    title: 'Комментарии',
    description: 'После каждого визита оставляйте комментарии — это важно для ведения истории и отчётности. Готовы начать?',
    iconBg: 'rgba(124, 58, 237, 0.12)',
    iconColor: '#A78BFA'
  }
]

const isLastStep = computed(() => currentStep.value === steps.length - 1)

function next() {
  if (isLastStep.value) {
    finish()
  } else {
    slideDirection.value = 'slide-left'
    currentStep.value++
  }
}

function prev() {
  if (currentStep.value > 0) {
    slideDirection.value = 'slide-right'
    currentStep.value--
  }
}

function finish() {
  localStorage.setItem('rocadamed_onboarding_done', 'true')
  show.value = false
}

// Check if onboarding needed
function checkAndShow() {
  const done = localStorage.getItem('rocadamed_onboarding_done')
  if (!done) {
    setTimeout(() => {
      show.value = true
    }, 600)
  }
}

defineExpose({ checkAndShow })
</script>

<style scoped>
.onboarding-overlay {
  position: fixed;
  inset: 0;
  z-index: var(--z-overlay);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-xl);
  background: var(--color-bg-overlay);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
}

.onboarding-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
}

.onboarding-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(100px);
  opacity: 0.25;
}

.onboarding-orb-1 {
  width: 350px;
  height: 350px;
  background: var(--color-accent);
  top: -100px;
  right: -100px;
  animation: float 8s ease-in-out infinite;
}

.onboarding-orb-2 {
  width: 300px;
  height: 300px;
  background: var(--color-primary);
  bottom: -80px;
  left: -80px;
  animation: float 6s ease-in-out infinite reverse;
}

.onboarding-card {
  position: relative;
  width: 100%;
  max-width: 400px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-xl);
  z-index: 1;
}

/* Dots */
.onboarding-dots {
  display: flex;
  gap: 8px;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: var(--radius-full);
  background: rgba(255, 255, 255, 0.15);
  transition: all var(--transition-base);
}

.dot.active {
  width: 24px;
  background: linear-gradient(90deg, var(--color-accent), var(--color-primary));
}

.dot.done {
  background: var(--color-accent);
}

/* Step */
.onboarding-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: var(--space-lg);
  min-height: 260px;
  justify-content: center;
}

.step-icon-wrap {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: scaleIn 0.4s ease;
}

.step-logo-img {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  object-fit: cover;
  animation: float 3s ease-in-out infinite;
}

.step-icon {
  font-size: 44px;
}

.step-title {
  font-size: var(--font-size-2xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text-primary);
  line-height: var(--line-height-tight);
}

.step-desc {
  font-size: var(--font-size-base);
  color: var(--color-text-secondary);
  line-height: var(--line-height-relaxed);
  max-width: 320px;
}

/* Actions */
.onboarding-actions {
  display: flex;
  align-items: center;
  gap: var(--space-base);
  width: 100%;
}

.onboarding-btn-back {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-full);
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text-secondary);
  transition: all var(--transition-fast);
}

.onboarding-btn-back:active {
  transform: scale(0.95);
  background: rgba(255, 255, 255, 0.1);
}

.onboarding-btn-next {
  flex: 1;
  height: 52px;
  border-radius: var(--radius-lg);
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  color: white;
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-semibold);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  transition: transform var(--transition-fast);
  box-shadow: var(--shadow-glow-accent);
}

.onboarding-btn-next:active {
  transform: scale(0.97);
}

.onboarding-btn-arrow {
  font-size: 20px;
}

.onboarding-skip {
  font-size: var(--font-size-sm);
  color: var(--color-text-tertiary);
  font-weight: var(--font-weight-medium);
  transition: color var(--transition-fast);
  padding: var(--space-sm);
}

.onboarding-skip:hover {
  color: var(--color-text-secondary);
}

/* Transitions */
.onboarding-fade-enter-active {
  animation: fadeIn 0.4s ease;
}
.onboarding-fade-leave-active {
  animation: fadeIn 0.3s ease reverse;
}

.slide-left-enter-active,
.slide-left-leave-active,
.slide-right-enter-active,
.slide-right-leave-active {
  transition: all 0.3s ease;
}

.slide-left-enter-from {
  opacity: 0;
  transform: translateX(40px);
}
.slide-left-leave-to {
  opacity: 0;
  transform: translateX(-40px);
}

.slide-right-enter-from {
  opacity: 0;
  transform: translateX(-40px);
}
.slide-right-leave-to {
  opacity: 0;
  transform: translateX(40px);
}
</style>
