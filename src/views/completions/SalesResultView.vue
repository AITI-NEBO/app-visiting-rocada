<template>
  <div class="sales-result">
    <!-- Step 1: проверка доступа к геолокации -->
    <GeoAccessStep v-if="step === 1" @ready="step = 2" />

    <!-- Step 2: Result -->
    <div v-if="step === 2" class="step-content animate-fade-in-up">
      <h3 class="step-title">Выберите итог визита</h3>

      <div v-if="loadingStatuses" class="loading-hint">
        <span class="material-symbols-rounded spin">progress_activity</span> Загрузка…
      </div>

      <div v-else-if="!statuses.length" class="empty-hint">
        <span class="material-symbols-rounded" style="color:var(--color-text-tertiary)">warning</span>
        Статусы завершения не настроены. Обратитесь к администратору.
      </div>

      <div v-else class="result-options">
        <button
          v-for="st in statuses"
          :key="st.id"
          class="result-option"
          :class="{ selected: selectedId === st.id, 'is-success-status': st.is_successful }"
          :style="selectedId === st.id ? { borderColor: st.color } : {}"
          @click="selectedId = st.id"
        >
          <span class="result-dot" :style="{ background: st.color }"></span>
          <span class="result-option-label">{{ st.name }}</span>
          <span v-if="st.is_successful" class="success-badge" title="После сохранения откроется инфоповод">
            <span class="material-symbols-rounded" style="font-size:14px">playlist_add_check</span>
          </span>
          <span v-if="selectedId === st.id" class="material-symbols-rounded result-check">check_circle</span>
        </button>
      </div>

      <!-- Подсказка если выбран успешный статус -->
      <div v-if="selectedIsSuccessful" class="success-hint">
        <span class="material-symbols-rounded">info</span>
        После сохранения откроется форма заполнения инфоповодов
      </div>

      <!-- Comment -->
      <div class="note-section">
        <h4 class="note-title">
          <span class="material-symbols-rounded" style="font-size:18px">edit_note</span>
          Комментарий
        </h4>
        <textarea v-model="comment" class="note-textarea" rows="3" placeholder="Комментарий в таймлайн сделки"></textarea>
      </div>

      <!-- Фото входной группы (обязательность и способы — в настройках направления) -->
      <VisitPhotoPicker
        v-model="entrancePhoto"
        :required="photoRequired"
        :allow-camera="allowCamera"
        :allow-gallery="allowGallery"
      />

      <p v-if="saveHint" class="save-hint">{{ saveHint }}</p>

      <button class="primary-btn" :disabled="!canSave || submitting" @click="submit">
        <span v-if="submitting" class="material-symbols-rounded spin">progress_activity</span>
        <span v-else class="material-symbols-rounded">check</span>
        {{ savingStage === 'geo' ? 'Определяем местоположение…' : submitting ? 'Сохранение…' : 'Сохранить итог' }}
      </button>
    </div>

    <!-- Step 3: Done -->
    <div v-if="step === 3" class="step-content animate-fade-in-up" style="text-align:center">
      <div class="done-visual">
        <span class="material-symbols-rounded done-icon">verified</span>
        <h3 class="done-title">Визит завершён!</h3>
        <p class="done-text">Итог сохранён в CRM.</p>
      </div>

      <router-link :to="`/visits/${visitId}/infopovod`" class="primary-btn" style="margin-top:var(--space-lg); background: linear-gradient(135deg, var(--color-success), #00a06a);">
        <span class="material-symbols-rounded">playlist_add_check</span>
        Заполнить инфоповод
      </router-link>

      <router-link :to="`/visits/${visitId}`" class="secondary-btn" style="margin-top:var(--space-md)">
        <span class="material-symbols-rounded">arrow_back</span>
        Вернуться к визиту
      </router-link>
      <router-link to="/" class="secondary-btn" style="margin-top:var(--space-md); border:none; background:transparent; color:var(--color-text-secondary)">
        На главную
      </router-link>
    </div>

    <!-- Error -->
    <div v-if="errorMsg" class="error-banner">{{ errorMsg }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../composables/useApi'
import { useDirections } from '../../composables/useDirections'
import { useVisits } from '../../composables/useVisits'
import { getSaveLocation } from '../../composables/useGeolocation'
import VisitPhotoPicker from '../../components/VisitPhotoPicker.vue'
import GeoAccessStep from '../../components/GeoAccessStep.vue'

const props = defineProps({ visitId: { type: [String, Number], required: true } })

const router      = useRouter()
const api         = useApi()
const { currentDirection } = useDirections()
const { loadVisits, submitResult } = useVisits()

const step          = ref(1)

const statuses      = ref([])
const loadingStatuses = ref(true)
const selectedId    = ref(null)
const comment       = ref('')
const entrancePhoto = ref(null)
const submitting    = ref(false)
const savingStage   = ref('') // 'geo' | 'save'
const errorMsg      = ref('')

// Настройки фото из направления (по умолчанию: обязательно, камера и галерея)
// Обязательность фото: есть итоги — по выбранному итогу (пока итог не выбран — null, «ещё неизвестно»),
// итогов нет — общая галочка направления
const photoRequired = computed(() => {
  if (statuses.value.length) {
    const st = statuses.value.find(s => s.id === selectedId.value)
    return st ? !!st.photo_required : null
  }
  return currentDirection.value?.photo_required !== false
})
const allowCamera   = computed(() => currentDirection.value?.photo_allow_camera !== false)
const allowGallery  = computed(() => currentDirection.value?.photo_allow_gallery !== false)

const canSave = computed(() => !!selectedId.value && (!photoRequired.value || !!entrancePhoto.value))

// Что ещё нужно, чтобы кнопка сохранения стала активной
const saveHint = computed(() => {
  const missing = []
  if (!selectedId.value) missing.push('выберите итог визита вверху экрана')
  if (photoRequired.value && !entrancePhoto.value) missing.push('добавьте фото входной группы')
  return missing.length ? 'Чтобы сохранить: ' + missing.join(' и ') + '.' : ''
})

// Вычисляем — выбранный статус успешный?
const selectedIsSuccessful = computed(() => {
  if (!selectedId.value) return false
  const st = statuses.value.find(s => s.id === selectedId.value)
  return !!(st?.is_successful || st?.is_successful === true
    || (st?.name || '').toLowerCase().includes('успеш')
    || (st?.name || '').toLowerCase().includes('состоялся'))
})

// Загружаем статусы из конфига направления
onMounted(async () => {
  try {
    const dir = currentDirection.value
    if (dir?.result_statuses?.length) {
      statuses.value = dir.result_statuses
    } else {
      // fallback: запрос конфига
      const cfg = await api.apiGet('api/config')
      const dirCfg = (cfg.directions || []).find(d => d.id === dir?.id)
      statuses.value = dirCfg?.result_statuses || []
    }
  } catch (e) {
    console.error('[SalesResultView] load statuses error:', e)
  } finally {
    loadingStatuses.value = false
  }
})

async function submit() {
  if (!canSave.value) return
  submitting.value = true
  errorMsg.value   = ''
  try {
    // 1. Местоположение и время — в момент сохранения итога
    savingStage.value = 'geo'
    const loc = await getSaveLocation()

    // 2. Результат + геолокация + фото входной группы
    savingStage.value = 'save'
    await submitResult(props.visitId, {
      status_id:  selectedId.value,
      comment:    comment.value,
      direction:  currentDirection.value?.id,
      lat:        loc.ok ? loc.lat : null,
      lng:        loc.ok ? loc.lng : null,
      accuracy:   loc.ok ? Math.round(loc.accuracy) : null,
      geo_error:  loc.ok ? null : loc.error,
    }, entrancePhoto.value)
    // 3. Обновляем список визитов в фоне (без ожидания)
    loadVisits(true).catch(() => {})

    if (selectedIsSuccessful.value) {
      router.push(`/visits/${props.visitId}/infopovod`)
    } else {
      step.value = 3
    }
  } catch (e) {
    errorMsg.value = e.message || 'Ошибка при сохранении'
  } finally {
    submitting.value = false
    savingStage.value = ''
  }
}
</script>

<style scoped>
.step-content { display: flex; flex-direction: column; gap: var(--space-lg); }
.primary-btn { width: 100%; height: 52px; border-radius: var(--radius-lg); background: linear-gradient(135deg, var(--color-accent), var(--color-primary)); color: white; font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); display: flex; align-items: center; justify-content: center; gap: var(--space-sm); transition: transform var(--transition-fast), opacity var(--transition-fast); box-shadow: var(--shadow-glow-accent); text-decoration: none; border: none; cursor: pointer; }
.primary-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.save-hint { font-size: var(--font-size-sm); color: var(--color-danger); text-align: center; }
.secondary-btn { width: 100%; height: 48px; border-radius: var(--radius-lg); background: var(--color-bg-card); color: var(--color-text-primary); font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); display: flex; align-items: center; justify-content: center; gap: var(--space-sm); border: 1px solid var(--color-border); text-decoration: none; transition: transform var(--transition-fast); }

.step-title { font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); }
.result-options { display: flex; flex-direction: column; gap: 12px; width: 100%; }
.result-option { display: flex; align-items: center; text-align: left; gap: var(--space-md); padding: 16px var(--space-base); background: var(--color-bg-card); border-radius: var(--radius-lg); border: 2px solid var(--color-border); cursor: pointer; transition: all var(--transition-fast); width: 100%; }
.result-option.selected { background: rgba(0,212,170,0.05); }
.result-option.is-success-status { border-color: rgba(0,196,140,0.3); }
.result-dot { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; }
.result-option-label { flex: 1; font-size: var(--font-size-base); font-weight: var(--font-weight-medium); line-height: 1.3; }
.result-check { font-size: 22px; color: var(--color-accent); }
.success-badge { display: flex; align-items: center; background: rgba(0,196,140,0.15); color: var(--color-success); border-radius: 6px; padding: 2px 4px; flex-shrink: 0; }
.success-hint { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: rgba(0,196,140,0.1); border: 1px solid rgba(0,196,140,0.3); border-radius: var(--radius-md); font-size: var(--font-size-sm); color: var(--color-success); }

.note-title { display: flex; align-items: center; gap: var(--space-sm); font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin-bottom: var(--space-sm); }
.note-textarea { width: 100%; padding: var(--space-base); border-radius: var(--radius-md); background: var(--color-bg-input); border: 1px solid var(--color-border); color: var(--color-text-primary); font-size: var(--font-size-base); resize: vertical; font-family: var(--font-family); transition: border-color var(--transition-fast); }
.note-textarea:focus { border-color: var(--color-primary); }
.note-textarea::placeholder { color: var(--color-text-tertiary); }



.done-visual { display: flex; flex-direction: column; align-items: center; gap: var(--space-md); padding: var(--space-xl) 0; }
.done-icon { font-size: 64px; color: var(--color-success); }
.done-title { font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); }
.done-text { font-size: var(--font-size-sm); color: var(--color-text-secondary); }

.loading-hint, .empty-hint { display: flex; align-items: center; gap: 8px; color: var(--color-text-secondary); font-size: var(--font-size-sm); }
.error-banner { padding: var(--space-base); background: rgba(255,68,68,0.1); color: var(--color-danger); border-radius: var(--radius-md); font-size: var(--font-size-sm); }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:0.5; } }
</style>
