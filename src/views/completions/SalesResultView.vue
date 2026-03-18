<template>
  <div class="sales-result">
    <!-- Step 1: Geo -->
    <div v-if="step === 1" class="step-content animate-fade-in-up">
      <div class="geo-visual">
        <div class="geo-circle" :class="{ locating: isLocating, done: geoDone }">
          <span class="material-symbols-rounded geo-icon">
            {{ geoDone ? 'check_circle' : isLocating ? 'gps_fixed' : 'my_location' }}
          </span>
        </div>
        <p class="geo-text" v-if="!geoDone && !isLocating">Подтвердите местоположение для фиксации визита</p>
        <p class="geo-text" v-else-if="isLocating">Определение местоположения…</p>
        <div v-else class="geo-success">
          <p class="geo-text geo-ok">Геолокация подтверждена!</p>
          <span class="geo-coords">{{ geoLat }}, {{ geoLng }}</span>
        </div>
      </div>
      <button v-if="!geoDone" class="primary-btn" :disabled="isLocating" @click="confirmGeo">
        <span class="material-symbols-rounded">{{ isLocating ? 'hourglass_top' : 'location_on' }}</span>
        {{ isLocating ? 'Определение…' : 'Подтвердить геолокацию' }}
      </button>
      <button v-else class="primary-btn" @click="step = 2">
        <span class="material-symbols-rounded">arrow_forward</span>
        Далее
      </button>
    </div>

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
          :class="{ selected: selectedId === st.id }"
          :style="selectedId === st.id ? { borderColor: st.color } : {}"
          @click="selectedId = st.id"
        >
          <span class="result-dot" :style="{ background: st.color }"></span>
          <span class="result-option-label">{{ st.name }}</span>
          <span v-if="selectedId === st.id" class="material-symbols-rounded result-check">check_circle</span>
        </button>
      </div>

      <!-- Comment -->
      <div class="note-section">
        <h4 class="note-title">
          <span class="material-symbols-rounded" style="font-size:18px">edit_note</span>
          Комментарий
        </h4>
        <textarea v-model="comment" class="note-textarea" rows="3" placeholder="Опишите итог визита…"></textarea>
      </div>

      <!-- Photos -->
      <div class="photo-section">
        <h4 class="note-title">
          <span class="material-symbols-rounded" style="font-size:18px">photo_camera</span>
          Фото (опционально)
        </h4>
        <div class="photo-grid">
          <div v-for="(p, i) in photos" :key="i" class="photo-thumb">
            <img :src="p.preview" alt="Фото" />
            <button class="photo-remove" @click="photos.splice(i,1)">
              <span class="material-symbols-rounded">close</span>
            </button>
          </div>
          <label class="photo-add">
            <span class="material-symbols-rounded">add_a_photo</span>
            <span>Добавить</span>
            <input type="file" accept="image/*" capture="environment" @change="handlePhoto" hidden />
          </label>
        </div>
      </div>

      <button class="primary-btn" :disabled="!selectedId || submitting" @click="submit">
        <span v-if="submitting" class="material-symbols-rounded spin">progress_activity</span>
        <span v-else class="material-symbols-rounded">check</span>
        {{ submitting ? 'Сохранение…' : 'Сохранить итог' }}
      </button>
    </div>

    <!-- Step 3: Done -->
    <div v-if="step === 3" class="step-content animate-fade-in-up" style="text-align:center">
      <div class="done-visual">
        <span class="material-symbols-rounded done-icon">verified</span>
        <h3 class="done-title">Визит завершён!</h3>
        <p class="done-text">Итог сохранён в CRM.</p>
      </div>
      <router-link :to="`/visits/${visitId}`" class="primary-btn" style="margin-top:var(--space-lg)">
        <span class="material-symbols-rounded">arrow_back</span>
        Вернуться к визиту
      </router-link>
      <router-link to="/" class="secondary-btn" style="margin-top:var(--space-md)">
        <span class="material-symbols-rounded">home</span>
        На главную
      </router-link>
    </div>

    <!-- Error -->
    <div v-if="errorMsg" class="error-banner">{{ errorMsg }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '../../composables/useApi'
import { useDirections } from '../../composables/useDirections'

const props = defineProps({ visitId: { type: [String, Number], required: true } })

const route       = useRoute()
const api         = useApi()
const { currentDirection } = useDirections()

const step          = ref(1)
const isLocating    = ref(false)
const geoDone       = ref(false)
const geoLat        = ref('')
const geoLng        = ref('')

const statuses      = ref([])
const loadingStatuses = ref(true)
const selectedId    = ref(null)
const comment       = ref('')
const photos        = ref([])
const submitting    = ref(false)
const errorMsg      = ref('')

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

function confirmGeo() {
  isLocating.value = true
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        geoLat.value = pos.coords.latitude.toFixed(6)
        geoLng.value = pos.coords.longitude.toFixed(6)
        isLocating.value = false
        geoDone.value = true
      },
      () => { isLocating.value = false; geoDone.value = true },
      { timeout: 6000 }
    )
  } else {
    isLocating.value = false
    geoDone.value = true
  }
}

function handlePhoto(e) {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = ev => photos.value.push({ file, preview: ev.target.result })
  reader.readAsDataURL(file)
  e.target.value = ''
}

async function submit() {
  if (!selectedId.value) return
  submitting.value = true
  errorMsg.value   = ''
  try {
    // 1. Геолокация если есть
    if (geoDone.value && geoLat.value) {
      await api.apiPost(`api/visits/${props.visitId}/geo`, {
        lat: geoLat.value, lng: geoLng.value,
        direction: currentDirection.value?.id,
      })
    }
    // 2. Загрузка фото
    for (const p of photos.value) {
      const fd = new FormData()
      fd.append('file', p.file)
      fd.append('direction', currentDirection.value?.id || '')
      await api.apiPostForm(`api/visits/${props.visitId}/files`, fd)
    }
    // 3. Результат
    await api.apiPost(`api/visits/${props.visitId}/result`, {
      status_id:  selectedId.value,
      comment:    comment.value,
      direction:  currentDirection.value?.id,
    })
    step.value = 3
  } catch (e) {
    errorMsg.value = e.message || 'Ошибка при сохранении'
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.step-content { display: flex; flex-direction: column; gap: var(--space-lg); }
.geo-visual { display: flex; flex-direction: column; align-items: center; gap: var(--space-base); padding: var(--space-xl) 0; }
.geo-circle { width: 100px; height: 100px; border-radius: 50%; background: var(--color-bg-card); border: 3px solid var(--color-border); display: flex; align-items: center; justify-content: center; transition: all var(--transition-slow); box-shadow: var(--shadow-md); }
.geo-circle.locating { border-color: var(--color-primary); animation: pulse 1.5s ease-in-out infinite; }
.geo-circle.done { border-color: var(--color-success); background: rgba(0,196,140,0.1); }
.geo-icon { font-size: 40px; color: var(--color-text-tertiary); transition: color var(--transition-base); }
.geo-circle.locating .geo-icon { color: var(--color-primary); }
.geo-circle.done .geo-icon { color: var(--color-success); }
.geo-text { font-size: var(--font-size-sm); color: var(--color-text-secondary); text-align: center; max-width: 280px; }
.geo-ok { color: var(--color-success); font-weight: var(--font-weight-semibold); }
.geo-success { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.geo-coords { font-size: var(--font-size-xs); color: var(--color-text-tertiary); }

.primary-btn { width: 100%; height: 52px; border-radius: var(--radius-lg); background: linear-gradient(135deg, var(--color-accent), var(--color-primary)); color: white; font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); display: flex; align-items: center; justify-content: center; gap: var(--space-sm); transition: transform var(--transition-fast), opacity var(--transition-fast); box-shadow: var(--shadow-glow-accent); text-decoration: none; border: none; cursor: pointer; }
.primary-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.secondary-btn { width: 100%; height: 48px; border-radius: var(--radius-lg); background: var(--color-bg-card); color: var(--color-text-primary); font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); display: flex; align-items: center; justify-content: center; gap: var(--space-sm); border: 1px solid var(--color-border); text-decoration: none; transition: transform var(--transition-fast); }

.step-title { font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); }
.result-options { display: flex; flex-direction: column; gap: var(--space-sm); }
.result-option { display: flex; align-items: center; gap: var(--space-md); padding: var(--space-base); background: var(--color-bg-card); border-radius: var(--radius-lg); border: 2px solid var(--color-border); cursor: pointer; transition: all var(--transition-fast); }
.result-option.selected { background: rgba(0,212,170,0.05); }
.result-dot { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; }
.result-option-label { flex: 1; font-size: var(--font-size-base); font-weight: var(--font-weight-medium); }
.result-check { font-size: 22px; color: var(--color-accent); }

.note-title { display: flex; align-items: center; gap: var(--space-sm); font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin-bottom: var(--space-sm); }
.note-textarea { width: 100%; padding: var(--space-base); border-radius: var(--radius-md); background: var(--color-bg-input); border: 1px solid var(--color-border); color: var(--color-text-primary); font-size: var(--font-size-base); resize: vertical; font-family: var(--font-family); transition: border-color var(--transition-fast); }
.note-textarea:focus { border-color: var(--color-primary); }
.note-textarea::placeholder { color: var(--color-text-tertiary); }

.photo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-sm); }
.photo-thumb { position: relative; aspect-ratio: 1; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--color-border); }
.photo-thumb img { width: 100%; height: 100%; object-fit: cover; }
.photo-remove { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; border-radius: 50%; background: rgba(0,0,0,0.6); color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.photo-remove .material-symbols-rounded { font-size: 16px; }
.photo-add { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; aspect-ratio: 1; border-radius: var(--radius-md); border: 2px dashed var(--color-border); cursor: pointer; color: var(--color-text-tertiary); font-size: var(--font-size-xs); }
.photo-add .material-symbols-rounded { font-size: 28px; }

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
