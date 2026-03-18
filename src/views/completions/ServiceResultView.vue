<template>
  <div class="service-result">
    <!-- Step 1: Geo -->
    <div v-if="step === 1" class="step-content animate-fade-in-up">
      <div class="geo-visual">
        <div class="geo-circle" :class="{ locating: isLocating, done: geoDone }">
          <span class="material-symbols-rounded geo-icon">
            {{ geoDone ? 'check_circle' : isLocating ? 'gps_fixed' : 'my_location' }}
          </span>
        </div>
        <p class="geo-text" v-if="!geoDone && !isLocating">Зафиксируйте прибытие на точку обслуживания</p>
        <p class="geo-text" v-else-if="isLocating">Определение местоположения…</p>
        <div v-else class="geo-success">
          <p class="geo-text geo-ok">Прибытие зафиксировано!</p>
          <span class="geo-coords">{{ geoLat }}, {{ geoLng }}</span>
        </div>
      </div>
      <button v-if="!geoDone" class="primary-btn" :disabled="isLocating" @click="confirmGeo">
        <span class="material-symbols-rounded">{{ isLocating ? 'hourglass_top' : 'location_on' }}</span>
        {{ isLocating ? 'Определение…' : 'Зафиксировать прибытие' }}
      </button>
      <button v-else class="primary-btn" @click="step = 2">
        <span class="material-symbols-rounded">arrow_forward</span>
        Далее
      </button>
    </div>

    <!-- Step 2: Work result -->
    <div v-if="step === 2" class="step-content animate-fade-in-up">
      <h3 class="step-title">Результат работы</h3>

      <!-- Статус (опционально) -->
      <div v-if="statuses.length" class="result-options">
        <p class="section-hint">Статус (необязательно):</p>
        <button
          v-for="st in statuses"
          :key="st.id"
          class="result-option"
          :class="{ selected: selectedId === st.id }"
          :style="selectedId === st.id ? { borderColor: st.color } : {}"
          @click="selectedId = selectedId === st.id ? null : st.id"
        >
          <span class="result-dot" :style="{ background: st.color }"></span>
          <span class="result-option-label">{{ st.name }}</span>
          <span v-if="selectedId === st.id" class="material-symbols-rounded result-check">check_circle</span>
        </button>
      </div>

      <!-- Фото (обязательно для сервиса) -->
      <div class="photo-section">
        <h4 class="note-title">
          <span class="material-symbols-rounded" style="font-size:18px">photo_camera</span>
          Фото выполненных работ
          <span style="color:var(--color-danger);font-size:12px;margin-left:4px">*обязательно</span>
        </h4>
        <div class="photo-grid">
          <div v-for="(p, i) in photos" :key="i" class="photo-thumb">
            <img :src="p.preview" alt="Фото" />
            <div class="photo-label">{{ p.fieldLabel }}</div>
            <button class="photo-remove" @click="photos.splice(i,1)">
              <span class="material-symbols-rounded">close</span>
            </button>
          </div>
          <!-- Кнопки по полям статуса или общая -->
          <template v-if="selectedStatusPhotoFields.length">
            <label v-for="field in selectedStatusPhotoFields" :key="field.code" class="photo-add">
              <span class="material-symbols-rounded">add_a_photo</span>
              <span>{{ field.label }}</span>
              <input type="file" accept="image/*" capture="environment" @change="e => handlePhoto(e, field)" hidden />
            </label>
          </template>
          <label v-else class="photo-add">
            <span class="material-symbols-rounded">add_a_photo</span>
            <span>Добавить фото</span>
            <input type="file" accept="image/*" capture="environment" @change="e => handlePhoto(e, null)" hidden />
          </label>
        </div>
        <p v-if="!photos.length" class="photo-warning">Необходимо добавить хотя бы одно фото</p>
      </div>

      <!-- Комментарий -->
      <div class="note-section">
        <h4 class="note-title">
          <span class="material-symbols-rounded" style="font-size:18px">edit_note</span>
          Описание выполненных работ
        </h4>
        <textarea v-model="comment" class="note-textarea" rows="4" placeholder="Опишите что было сделано…"></textarea>
      </div>

      <div v-if="errorMsg" class="error-banner">{{ errorMsg }}</div>

      <button class="primary-btn" :disabled="!photos.length || submitting" @click="submit">
        <span v-if="submitting" class="material-symbols-rounded spin">progress_activity</span>
        <span v-else class="material-symbols-rounded">task_alt</span>
        {{ submitting ? 'Сохранение…' : 'Завершить работу' }}
      </button>
    </div>

    <!-- Step 3: Done -->
    <div v-if="step === 3" class="step-content animate-fade-in-up" style="text-align:center">
      <div class="done-visual">
        <span class="material-symbols-rounded done-icon">task_alt</span>
        <h3 class="done-title">Работа завершена!</h3>
        <p class="done-text">Акт и фотоматериалы сохранены в CRM.</p>
      </div>
      <router-link :to="`/visits/${visitId}`" class="primary-btn" style="margin-top:var(--space-lg)">
        <span class="material-symbols-rounded">arrow_back</span>
        К заявке
      </router-link>
      <router-link to="/" class="secondary-btn" style="margin-top:var(--space-md)">
        <span class="material-symbols-rounded">home</span>
        На главную
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../../composables/useApi'
import { useDirections } from '../../composables/useDirections'

const props = defineProps({ visitId: { type: [String, Number], required: true } })

const api  = useApi()
const { currentDirection } = useDirections()

const step       = ref(1)
const isLocating = ref(false)
const geoDone    = ref(false)
const geoLat     = ref('')
const geoLng     = ref('')

const statuses   = ref([])
const selectedId = ref(null)
const comment    = ref('')
const photos     = ref([])
const submitting = ref(false)
const errorMsg   = ref('')

onMounted(async () => {
  const dir = currentDirection.value
  const rs  = dir?.result_statuses || []
  statuses.value = rs.filter(s => s.name)
})

// Поля фото для выбранного статуса
const selectedStatusPhotoFields = computed(() => {
  if (!selectedId.value) return []
  const st = statuses.value.find(s => s.id === selectedId.value)
  return (st?.photo_fields || []).map(code => ({ code, label: code }))
})

function confirmGeo() {
  isLocating.value = true
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      pos => { geoLat.value = pos.coords.latitude.toFixed(6); geoLng.value = pos.coords.longitude.toFixed(6); isLocating.value = false; geoDone.value = true },
      () => { isLocating.value = false; geoDone.value = true },
      { timeout: 6000 }
    )
  } else {
    isLocating.value = false; geoDone.value = true
  }
}

function handlePhoto(e, field) {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = ev => photos.value.push({ file, preview: ev.target.result, fieldCode: field?.code || '', fieldLabel: field?.label || 'Фото' })
  reader.readAsDataURL(file)
  e.target.value = ''
}

async function submit() {
  if (!photos.value.length) return
  submitting.value = true; errorMsg.value = ''
  try {
    if (geoDone.value && geoLat.value) {
      await api.apiPost(`api/visits/${props.visitId}/geo`, { lat: geoLat.value, lng: geoLng.value, direction: currentDirection.value?.id })
    }
    for (const p of photos.value) {
      const fd = new FormData()
      fd.append('file', p.file)
      if (p.fieldCode) fd.append('field', p.fieldCode)
      fd.append('direction', currentDirection.value?.id || '')
      await api.apiPostForm(`api/visits/${props.visitId}/files`, fd)
    }
    await api.apiPost(`api/visits/${props.visitId}/result`, {
      status_id: selectedId.value || null,
      comment:   comment.value,
      direction: currentDirection.value?.id,
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
.geo-circle { width: 100px; height: 100px; border-radius: 50%; background: var(--color-bg-card); border: 3px solid var(--color-border); display: flex; align-items: center; justify-content: center; transition: all var(--transition-slow); }
.geo-circle.locating { border-color: var(--color-primary); animation: pulse 1.5s ease-in-out infinite; }
.geo-circle.done { border-color: var(--color-success); background: rgba(0,196,140,0.1); }
.geo-icon { font-size: 40px; color: var(--color-text-tertiary); }
.geo-circle.locating .geo-icon { color: var(--color-primary); }
.geo-circle.done .geo-icon { color: var(--color-success); }
.geo-text { font-size: var(--font-size-sm); color: var(--color-text-secondary); text-align: center; max-width: 280px; }
.geo-ok { color: var(--color-success); font-weight: var(--font-weight-semibold); }
.geo-success { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.geo-coords { font-size: var(--font-size-xs); color: var(--color-text-tertiary); }

.primary-btn { width: 100%; height: 52px; border-radius: var(--radius-lg); background: linear-gradient(135deg, var(--color-accent), var(--color-primary)); color: white; font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); display: flex; align-items: center; justify-content: center; gap: var(--space-sm); border: none; cursor: pointer; text-decoration: none; transition: opacity var(--transition-fast); }
.primary-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.secondary-btn { width: 100%; height: 48px; border-radius: var(--radius-lg); background: var(--color-bg-card); color: var(--color-text-primary); font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); display: flex; align-items: center; justify-content: center; gap: var(--space-sm); border: 1px solid var(--color-border); text-decoration: none; }

.step-title { font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); }
.section-hint { font-size: var(--font-size-sm); color: var(--color-text-secondary); margin-bottom: 4px; }
.result-options { display: flex; flex-direction: column; gap: var(--space-sm); }
.result-option { display: flex; align-items: center; gap: var(--space-md); padding: var(--space-base); background: var(--color-bg-card); border-radius: var(--radius-lg); border: 2px solid var(--color-border); cursor: pointer; transition: all var(--transition-fast); }
.result-option.selected { background: rgba(0,212,170,0.05); }
.result-dot { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; }
.result-option-label { flex: 1; font-size: var(--font-size-base); font-weight: var(--font-weight-medium); }
.result-check { font-size: 22px; color: var(--color-accent); }

.note-title { display: flex; align-items: center; gap: var(--space-sm); font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); margin-bottom: var(--space-sm); }
.note-textarea { width: 100%; padding: var(--space-base); border-radius: var(--radius-md); background: var(--color-bg-input); border: 1px solid var(--color-border); color: var(--color-text-primary); font-size: var(--font-size-base); resize: vertical; font-family: var(--font-family); }
.note-textarea:focus { border-color: var(--color-primary); }
.note-textarea::placeholder { color: var(--color-text-tertiary); }

.photo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-sm); }
.photo-thumb { position: relative; aspect-ratio: 1; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--color-border); }
.photo-thumb img { width: 100%; height: 100%; object-fit: cover; }
.photo-label { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.5); color: white; font-size: 10px; padding: 2px 4px; text-align: center; }
.photo-remove { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; border-radius: 50%; background: rgba(0,0,0,0.6); color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.photo-remove .material-symbols-rounded { font-size: 16px; }
.photo-add { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; aspect-ratio: 1; border-radius: var(--radius-md); border: 2px dashed var(--color-border); cursor: pointer; color: var(--color-text-tertiary); font-size: var(--font-size-xs); }
.photo-add .material-symbols-rounded { font-size: 28px; }
.photo-warning { font-size: var(--font-size-xs); color: var(--color-danger); margin-top: 4px; }

.done-visual { display: flex; flex-direction: column; align-items: center; gap: var(--space-md); padding: var(--space-xl) 0; }
.done-icon { font-size: 64px; color: var(--color-success); }
.done-title { font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); }
.done-text { font-size: var(--font-size-sm); color: var(--color-text-secondary); }

.error-banner { padding: var(--space-base); background: rgba(255,68,68,0.1); color: var(--color-danger); border-radius: var(--radius-md); font-size: var(--font-size-sm); }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:0.5; } }
</style>
