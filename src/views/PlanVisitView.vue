<template>
  <div class="plan-page">
    <AppHeader title="Запланировать визит" />

    <div class="page-content">

      <!-- Поиск сделки -->
      <div v-if="step === 'search'" class="search-section animate-fade-in-up">
        <div class="search-card glass">
          <h3 class="section-title">
            <span class="material-symbols-rounded">search</span>
            Найти сделку
          </h3>
          <p class="section-desc">Введите ID сделки или название компании</p>

          <div class="search-row">
            <input
              v-model="query"
              class="search-input"
              type="text"
              placeholder="ID сделки или название компании..."
              inputmode="search"
              @keydown.enter="search"
            />
            <button class="search-btn" :disabled="searching || !query.trim()" @click="search">
              <span v-if="searching" class="material-symbols-rounded spin">progress_activity</span>
              <span v-else class="material-symbols-rounded">search</span>
            </button>
          </div>

          <div v-if="searchError" class="error-msg">
            <span class="material-symbols-rounded">error</span>
            {{ searchError }}
          </div>
        </div>

        <!-- Результаты поиска -->
        <div v-if="results.length" class="results-list animate-fade-in-up">
          <h4 class="results-title">Найдено сделок: {{ results.length }}</h4>
          <button
            v-for="deal in results"
            :key="deal.ID"
            class="deal-item glass"
            @click="selectDeal(deal)"
          >
            <div class="deal-icon">
              <span class="material-symbols-rounded">handshake</span>
            </div>
            <div class="deal-info">
              <span class="deal-title">{{ deal.TITLE }}</span>
              <span class="deal-id">ID: {{ deal.ID }}</span>
            </div>
            <span class="material-symbols-rounded" style="color: var(--color-primary)">chevron_right</span>
          </button>
        </div>
      </div>

      <!-- Форма планирования -->
      <div v-else-if="step === 'plan'" class="plan-form animate-fade-in-up">
        <div class="deal-card glass">
          <div class="deal-card-row">
            <span class="material-symbols-rounded" style="color: var(--color-primary)">handshake</span>
            <div>
              <p class="deal-card-title">{{ selectedDeal.TITLE }}</p>
              <p class="deal-card-id">ID: {{ selectedDeal.ID }}</p>
            </div>
          </div>
          <button class="change-deal-btn" @click="step = 'search'">
            <span class="material-symbols-rounded" style="font-size:16px">edit</span>
            Изменить сделку
          </button>
        </div>

        <div class="field-card glass">
          <label class="field-label">
            <span class="material-symbols-rounded">local_shipping</span>
            Пункт разгрузки
          </label>
          <div v-if="loadingPoints" class="loading-hint">
            <span class="material-symbols-rounded spin">progress_activity</span>
            Загрузка адресов...
          </div>
          <div v-else-if="!unloadPoints.length" class="no-points-hint">
            <span class="material-symbols-rounded">warning</span>
            Адреса не найдены для компании этой сделки
          </div>
          <select v-else v-model="planPointId" class="field-select">
            <option value="" disabled>Выберите пункт разгрузки</option>
            <option v-for="pt in unloadPoints" :key="pt.id" :value="pt.id">{{ pt.name }}</option>
          </select>
        </div>

        <div class="field-card glass">
          <label class="field-label">
            <span class="material-symbols-rounded">calendar_today</span>
            Дата визита
          </label>
          <input type="date" v-model="planDate" class="field-input" />
        </div>

        <div class="field-card glass">
          <label class="field-label">
            <span class="material-symbols-rounded">schedule</span>
            Время визита
          </label>
          <input type="time" v-model="planTime" class="field-input" />
        </div>

        <button
          class="primary-btn"
          :disabled="isPlanning || !planPointId || !planDate || !planTime"
          @click="confirmPlan"
        >
          <span v-if="isPlanning" class="material-symbols-rounded spin">progress_activity</span>
          <span v-else class="material-symbols-rounded">check_circle</span>
          {{ isPlanning ? 'Сохранение...' : 'Запланировать визит' }}
        </button>

        <div v-if="planError" class="error-msg mt">
          <span class="material-symbols-rounded">error</span>
          {{ planError }}
        </div>
      </div>

      <!-- Успех -->
      <div v-else-if="step === 'done'" class="done-screen animate-fade-in-up">
        <div class="done-icon-wrap">
          <span class="material-symbols-rounded done-icon">check_circle</span>
        </div>
        <h2 class="done-title">Визит запланирован!</h2>
        <p class="done-text">Сделка <strong>{{ selectedDeal.TITLE }}</strong> обновлена</p>
        <button class="primary-btn mt" @click="reset">Запланировать ещё</button>
        <router-link to="/visits" class="secondary-btn mt-sm">Перейти к визитам</router-link>
      </div>

    </div>

    <!-- Toast внутри корневого div — один root node -->
    <transition name="toast">
      <div v-if="toastMsg" class="toast">
        <span class="material-symbols-rounded" style="font-size:18px">info</span>
        {{ toastMsg }}
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import AppHeader from '../components/AppHeader.vue'
import { useVisits } from '../composables/useVisits'
import { useApi } from '../composables/useApi'

const api = useApi()
const { fetchUnloadPoints, planVisitApi } = useVisits()

const step = ref('search')
const query = ref('')
const searching = ref(false)
const searchError = ref('')
const results = ref([])
const selectedDeal = ref(null)

const loadingPoints = ref(false)
const unloadPoints = ref([])
const planPointId = ref('')
const planDate = ref('')
const planTime = ref('10:00')
const isPlanning = ref(false)
const planError = ref('')
const toastMsg = ref('')

function showToast(msg) {
  toastMsg.value = msg
  setTimeout(() => { toastMsg.value = '' }, 3000)
}

// Дата по умолчанию — завтра
const tomorrow = new Date()
tomorrow.setDate(tomorrow.getDate() + 1)
planDate.value = tomorrow.toISOString().split('T')[0]

async function search() {
  if (!query.value.trim()) return
  searching.value = true
  searchError.value = ''
  results.value = []

  try {
    const res = await api.apiGet('api/deals/search', { q: query.value.trim() })
    results.value = res.deals || []
    if (!results.value.length) {
      searchError.value = 'Сделки не найдены. Попробуйте другой запрос.'
    }
  } catch (e) {
    console.error('[PlanView] search error:', e)
    searchError.value = 'Ошибка поиска: ' + (e.message || 'попробуйте снова')
  } finally {
    searching.value = false
  }
}

async function selectDeal(deal) {
  selectedDeal.value = deal
  step.value = 'plan'
  loadingPoints.value = true
  unloadPoints.value = []
  planPointId.value = ''

  try {
    const res = await fetchUnloadPoints(deal.ID)
    unloadPoints.value = res.points || []
    if (unloadPoints.value.length) {
      planPointId.value = unloadPoints.value[0].id
    }
  } catch (e) {
    console.error('[PlanView] unload points error:', e)
    showToast('Ошибка загрузки адресов: ' + e.message)
  } finally {
    loadingPoints.value = false
  }
}

async function confirmPlan() {
  if (!planPointId.value || !planDate.value || !planTime.value) {
    showToast('Заполните все поля')
    return
  }

  isPlanning.value = true
  planError.value = ''

  try {
    await planVisitApi(selectedDeal.value.ID, {
      point_id: planPointId.value,
      visit_date: planDate.value,
      visit_time: planTime.value,
    })
    step.value = 'done'
  } catch (e) {
    console.error('[PlanView] plan error:', e)
    planError.value = e.message || 'Ошибка при планировании визита'
  } finally {
    isPlanning.value = false
  }
}

function reset() {
  step.value = 'search'
  query.value = ''
  results.value = []
  selectedDeal.value = null
  planPointId.value = ''
  planError.value = ''
  searchError.value = ''
}
</script>

<style scoped>
.plan-page {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
}
.page-content {
  flex: 1;
  padding: var(--space-base);
  padding-bottom: calc(env(safe-area-inset-bottom, 20px) + 90px);
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

/* Search */
.search-card, .field-card, .deal-card {
  padding: var(--space-lg);
  border-radius: var(--radius-xl);
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-bold);
  margin-bottom: 4px;
}
.section-desc {
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  margin-bottom: var(--space-md);
}

.search-row {
  display: flex;
  gap: var(--space-sm);
}
.search-input {
  flex: 1;
  padding: 12px 16px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
  background: var(--color-bg-input);
  color: var(--color-text-primary);
  font-size: var(--font-size-base);
}
.search-input:focus {
  outline: none;
  border-color: var(--color-primary);
}
.search-btn {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-lg);
  background: var(--color-primary);
  color: white;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: opacity var(--transition-fast);
}
.search-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.error-msg {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 12px;
  padding: 10px 14px;
  background: rgba(255, 68, 68, 0.1);
  border-radius: var(--radius-md);
  color: var(--color-danger);
  font-size: var(--font-size-sm);
}

/* Results */
.results-title {
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  font-weight: var(--font-weight-medium);
  margin-bottom: 8px;
}
.results-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}
.deal-item {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-base) var(--space-lg);
  border-radius: var(--radius-xl);
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  cursor: pointer;
  transition: all var(--transition-fast);
  text-align: left;
  width: 100%;
}
.deal-item:active { transform: scale(0.98); }
.deal-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(0, 102, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: var(--color-primary);
}
.deal-info { flex: 1; min-width: 0; }
.deal-title { display: block; font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.deal-id { display: block; font-size: var(--font-size-xs); color: var(--color-text-tertiary); }

/* Plan form */
.deal-card-row {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  margin-bottom: var(--space-sm);
}
.deal-card-title { font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); }
.deal-card-id { font-size: var(--font-size-xs); color: var(--color-text-tertiary); }
.change-deal-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: var(--font-size-sm);
  color: var(--color-primary);
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}

.field-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: var(--space-sm);
}
.field-select, .field-input {
  width: 100%;
  padding: 12px 16px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
  background: var(--color-bg-input);
  color: var(--color-text-primary);
  font-size: var(--font-size-base);
  -webkit-appearance: none;
  appearance: none;
  box-sizing: border-box;
}
.field-select:focus, .field-input:focus {
  outline: none;
  border-color: var(--color-primary);
}

.loading-hint, .no-points-hint {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  padding: 8px 0;
}
.no-points-hint { color: var(--color-danger); }

/* Buttons */
.primary-btn {
  width: 100%;
  height: 52px;
  border-radius: var(--radius-xl);
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  color: white;
  border: none;
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-semibold);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  box-shadow: var(--shadow-glow-accent);
  transition: opacity var(--transition-fast);
}
.primary-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.secondary-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 48px;
  border-radius: var(--radius-xl);
  border: 1px solid var(--color-border);
  background: var(--color-bg-card);
  color: var(--color-text-primary);
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-medium);
  text-decoration: none;
  cursor: pointer;
}

/* Done screen */
.done-screen {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: var(--space-2xl) 0;
  text-align: center;
  gap: var(--space-md);
}
.done-icon-wrap {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: rgba(0, 196, 140, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
}
.done-icon { font-size: 48px; color: var(--color-success); }
.done-title { font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); }
.done-text { font-size: var(--font-size-sm); color: var(--color-text-secondary); }

.mt { margin-top: var(--space-md); width: 100%; }
.mt-sm { margin-top: var(--space-sm); width: 100%; }

/* Toast */
.toast {
  position: fixed;
  bottom: calc(var(--bottom-nav-height) + 16px);
  left: 50%;
  transform: translateX(-50%);
  background: var(--color-text-primary);
  color: var(--color-bg-base);
  padding: 12px 20px;
  border-radius: 30px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 500;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  z-index: 200;
  white-space: nowrap;
}
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(10px); }
.toast-enter-active, .toast-leave-active { transition: all 0.25s; }

@keyframes spin { to { transform: rotate(360deg); } }
.spin { animation: spin 1s linear infinite; }
</style>
