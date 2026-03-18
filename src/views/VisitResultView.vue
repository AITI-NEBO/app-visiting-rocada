<template>
  <div class="result-page">
    <AppHeader title="Завершение визита" :showBack="true" />

    <div class="page-content">
      <!-- Step indicator -->
      <div class="steps animate-fade-in-up">
        <div class="step" :class="{ active: currentStep >= 1, done: currentStep > 1 }">
          <div class="step-dot">{{ currentStep > 1 ? '✓' : '1' }}</div>
          <span class="step-label">Геолокация</span>
        </div>
        <div class="step-line" :class="{ active: currentStep > 1 }"></div>
        <div class="step" :class="{ active: currentStep >= 2, done: currentStep > 2 }">
          <div class="step-dot">{{ currentStep > 2 ? '✓' : '2' }}</div>
          <span class="step-label">Результат</span>
        </div>
        <div class="step-line" :class="{ active: currentStep > 2 }"></div>
        <div class="step" :class="{ active: currentStep >= 3 }">
          <div class="step-dot">3</div>
          <span class="step-label">Готово</span>
        </div>
      </div>

      <!-- Диспетчер: загружаем нужный completion-компонент -->
      <Suspense>
        <component
          :is="completionComponent"
          v-if="completionComponent"
          :visitId="$route.params.id"
        />
        <template #fallback>
          <div class="loading-hint">
            <span class="material-symbols-rounded spin">progress_activity</span>
            Загрузка формы завершения…
          </div>
        </template>
      </Suspense>
    </div>
  </div>
</template>

<script setup>
import { ref, shallowRef, onMounted } from 'vue'
import AppHeader from '../components/AppHeader.vue'
import { useDirections } from '../composables/useDirections'
import { getCompletionComponent } from './completions/registry'

const { currentDirection, loadDirections } = useDirections()
const completionComponent = shallowRef(null)

// Текущий шаг пробрасывается через хранилище (для синхронизации индикатора)
const currentStep = ref(1)

onMounted(async () => {
  // Убеждаемся что направления загружены
  if (!currentDirection.value) {
    await loadDirections()
  }
  const type = currentDirection.value?.completion_type || 'sales'
  try {
    const mod = await getCompletionComponent(type)
    completionComponent.value = mod.default
  } catch (e) {
    console.error('[VisitResultView] cannot load completion component:', e)
    // fallback to sales
    const mod = await import('./completions/SalesResultView.vue')
    completionComponent.value = mod.default
  }
})
</script>

<style scoped>
.result-page { display: flex; flex-direction: column; min-height: 100dvh; }
.page-content { flex: 1; padding: var(--space-base); padding-bottom: calc(var(--bottom-nav-height) + var(--safe-area-bottom) + var(--space-xl)); display: flex; flex-direction: column; gap: var(--space-lg); }

/* Steps indicator */
.steps { display: flex; align-items: center; justify-content: center; padding: var(--space-base) 0; }
.step { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.step-dot { width: 32px; height: 32px; border-radius: 50%; background: var(--color-bg-elevated); display: flex; align-items: center; justify-content: center; font-size: var(--font-size-sm); font-weight: var(--font-weight-bold); color: var(--color-text-tertiary); transition: all var(--transition-base); }
.step.active .step-dot { background: linear-gradient(135deg, var(--color-accent), var(--color-primary)); color: white; }
.step.done .step-dot { background: var(--color-success); color: white; }
.step-label { font-size: var(--font-size-xs); color: var(--color-text-tertiary); }
.step.active .step-label { color: var(--color-text-primary); font-weight: var(--font-weight-medium); }
.step-line { width: 40px; height: 2px; background: var(--color-bg-elevated); margin: 0 var(--space-sm); margin-bottom: 20px; transition: background var(--transition-base); }
.step-line.active { background: var(--color-accent); }

.loading-hint { display: flex; align-items: center; gap: 8px; color: var(--color-text-secondary); font-size: var(--font-size-sm); }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
