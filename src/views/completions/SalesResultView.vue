<template>
  <div class="sales-result">
    <!-- Step 1: Geo -->
    <div v-if="step === 1" class="step-content animate-fade-in-up">
      <div class="geo-visual">
        <div style="position: relative; width: 100%;">
          <div class="geo-map-container" ref="mapEl" :class="{ loading: isLocating }"></div>
          <div v-if="isLocating" class="map-overlay" style="border-radius: var(--radius-lg);">
             <span class="material-symbols-rounded spin overlay-icon">my_location</span>
          </div>
        </div>
        <p class="geo-text" v-if="!geoDone && !isLocating && !geoError">Подтвердите местоположение для фиксации визита</p>
        <p class="geo-text" v-else-if="isLocating">Определение местоположения…</p>
        <div v-else-if="geoError" class="geo-error-block">
          <p class="geo-text geo-err">Не удалось определить местоположение</p>
          <p class="geo-hint">{{ geoError }}</p>
        </div>
        <div v-if="geoDone && geoLat" class="geo-success" style="margin-top: 8px;">
          <p class="geo-text geo-ok">Геолокация подтверждена!</p>
          <span class="geo-coords">{{ geoLat }}, {{ geoLng }}</span>
        </div>
      </div>

      <!-- Buttons: before geo -->
      <template v-if="!geoDone && !geoError">
        <button class="primary-btn" :disabled="isLocating" @click="confirmGeo">
          <span class="material-symbols-rounded">{{ isLocating ? 'hourglass_top' : 'location_on' }}</span>
          {{ isLocating ? 'Определение…' : 'Подтвердить геолокацию' }}
        </button>
        <button class="secondary-btn" @click="skipGeo" style="margin-top:var(--space-sm)">
          Пропустить
        </button>
      </template>

      <!-- Buttons: error -->
      <template v-else-if="geoError">
        <button class="primary-btn" @click="confirmGeo">
          <span class="material-symbols-rounded">refresh</span>
          Повторить
        </button>
        <button class="secondary-btn" @click="skipGeo" style="margin-top:var(--space-sm)">
          Пропустить и продолжить
        </button>
      </template>

      <!-- Button: success -->
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
        <textarea v-model="comment" class="note-textarea" rows="3" placeholder="Опишите итог визита…"></textarea>
      </div>

      <!-- Removed photos section based on user request (only shown in service view) -->

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
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../../composables/useApi'
import { useDirections } from '../../composables/useDirections'
import { useVisits } from '../../composables/useVisits'
import { nextTick } from 'vue'

const props = defineProps({ visitId: { type: [String, Number], required: true } })

const route       = useRoute()
const router      = useRouter()
const api         = useApi()
const { currentDirection } = useDirections()
const { findVisit, loadVisits } = useVisits()
const mapEl       = ref(null)

// Расчёт расстояния по формуле Haversine (возвращает метры)
function haversineDistance(lat1, lon1, lat2, lon2) {
  const R = 6371000 // радиус Земли в метрах
  const toRad = d => d * Math.PI / 180
  const dLat = toRad(lat2 - lat1)
  const dLon = toRad(lon2 - lon1)
  const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon/2)**2
  return Math.round(R * 2 * Math.asin(Math.sqrt(a)))
}

const step          = ref(1)
const isLocating    = ref(false)
const geoDone       = ref(false)
const geoLat        = ref('')
const geoLng        = ref('')
const geoError      = ref('')

const statuses      = ref([])
const loadingStatuses = ref(true)
const selectedId    = ref(null)
const comment       = ref('')
const submitting    = ref(false)
const errorMsg      = ref('')

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
  
  // Инициализируем карту
  setTimeout(() => {
    let center = [55.795, 49.1221] // Казань по дефолту
    const visit = findVisit(props.visitId)
    if (visit && (visit.lat || visit.geoLat)) {
       center = [visit.lat || visit.geoLat, visit.lng || visit.geoLng]
    }
    initMap(center[0], center[1], !!visit)
  }, 100)
})

let mapInstance = null
let myPlacemark = null

function confirmGeo() {
  isLocating.value = true
  geoError.value   = ''
  if (!navigator.geolocation) {
    isLocating.value = false
    geoError.value   = 'Геолокация не поддерживается браузером'
    return
  }
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      geoLat.value     = pos.coords.latitude.toFixed(6)
      geoLng.value     = pos.coords.longitude.toFixed(6)
      isLocating.value = false
      geoDone.value    = true
      geoError.value   = ''
      
      if (mapInstance && window.ymaps) {
         if (myPlacemark) { mapInstance.geoObjects.remove(myPlacemark) }
         myPlacemark = new window.ymaps.Placemark([pos.coords.latitude, pos.coords.longitude], { hintContent: 'Вы здесь' }, { preset: 'islands#redDotIcon' })
         mapInstance.geoObjects.add(myPlacemark)
         
         const bounds = mapInstance.geoObjects.getBounds()
         if (bounds) {
             mapInstance.setBounds(bounds, { checkZoomRange: true, zoomMargin: 20 })
         } else {
             mapInstance.setCenter([pos.coords.latitude, pos.coords.longitude])
         }
      } else {
         initMap(pos.coords.latitude, pos.coords.longitude, true)
      }
    },
    (err) => {
      isLocating.value = false
      geoError.value   = err.code === 1
        ? 'Нет разрешения на доступ к местоположению. Разрешите в браузера.'
        : err.code === 2
        ? 'Местоположение недоступно. Проверьте GPS или сеть.'
        : 'Превышено время ожидания. Попробуйте ещё раз.'
    },
    { timeout: 15000, enableHighAccuracy: false, maximumAge: 30000 }
  )
}

async function initMap(lat, lng, hasVisitPoint = true) {
  await new Promise(resolve => {
    if (window.ymaps) { resolve(); return }
    const script = document.createElement('script')
    script.src = 'https://api-maps.yandex.ru/2.1/?lang=ru_RU'
    script.onload = resolve
    document.head.appendChild(script)
  })

  window.ymaps.ready(() => {
    if (!mapEl.value) return
    mapEl.value.innerHTML = ''
    mapInstance = new window.ymaps.Map(mapEl.value, {
      center: [lat, lng],
      zoom: 16,
      controls: ['zoomControl']
    }, { suppressMapOpenBlock: true })
    
    if (hasVisitPoint && lat && lng) {
      const placemark = new window.ymaps.Placemark([lat, lng], { hintContent: 'Точка визита' }, { preset: 'islands#blueDotIcon' })
      mapInstance.geoObjects.add(placemark)
    } else {
      window.ymaps.geolocation.get({ provider: 'yandex', autoReverseGeocode: false })
        .then((result) => {
          const coords = result.geoObjects.get(0).geometry.getCoordinates()
          mapInstance.setCenter(coords, 14)
        }).catch(() => {})
    }
  })
}

function skipGeo() {
  isLocating.value = false
  geoDone.value    = true  // пропуск — идём дальше без координат
  step.value       = 2
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
    // 3. Рассчитываем расстояние от точки визита (если у визита есть координаты)
    let distanceM = undefined
    if (geoDone.value && geoLat.value) {
      const visit = findVisit(props.visitId)
      if (visit && visit.lat != null && visit.lng != null) {
        distanceM = haversineDistance(
          parseFloat(geoLat.value), parseFloat(geoLng.value),
          visit.lat, visit.lng
        )
      }
    }
    // 4. Результат
    await api.apiPost(`api/visits/${props.visitId}/result`, {
      status_id:  selectedId.value,
      comment:    comment.value,
      direction:  currentDirection.value?.id,
      ...(distanceM !== undefined ? { distance_m: distanceM } : {}),
    })
    // 5. Обновляем список визитов в фоне (без ожидания)
    loadVisits(true).catch(() => {})

    const selectedStatus = statuses.value.find(s => s.id === selectedId.value)
    
    // Resilient check - is_successful is now correctly returned from API
    const isSuccess = selectedIsSuccessful.value
    
    if (isSuccess) {
      router.push(`/visits/${props.visitId}/infopovod`)
    } else {
      step.value = 3
    }
  } catch (e) {
    errorMsg.value = e.message || 'Ошибка при сохранении'
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.step-content { display: flex; flex-direction: column; gap: var(--space-lg); }
.geo-visual { display: flex; flex-direction: column; align-items: center; gap: var(--space-sm); padding: var(--space-xl) 0; width: 100%; }
.geo-map-container { position: relative; width: 100%; height: 260px; border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); border: 2px solid transparent; transition: border-color var(--transition-fast); }
.geo-map-container.loading { border-color: var(--color-primary); }
.map-overlay { position: absolute; inset: 0; background: rgba(255,255,255,0.7); display: flex; align-items: center; justify-content: center; z-index: 10; backdrop-filter: blur(2px); }
.overlay-icon { font-size: 40px; color: var(--color-primary); }

.geo-text { font-size: var(--font-size-sm); color: var(--color-text-secondary); text-align: center; max-width: 280px; }
.geo-ok { color: var(--color-success); font-weight: var(--font-weight-semibold); }
.geo-err { color: var(--color-danger); font-weight: var(--font-weight-semibold); }
.geo-hint { font-size: var(--font-size-xs); color: var(--color-text-tertiary); text-align: center; max-width: 280px; margin-top: 4px; }
.geo-error-block { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.geo-success { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.geo-coords { font-size: var(--font-size-xs); color: var(--color-text-tertiary); }

.primary-btn { width: 100%; height: 52px; border-radius: var(--radius-lg); background: linear-gradient(135deg, var(--color-accent), var(--color-primary)); color: white; font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); display: flex; align-items: center; justify-content: center; gap: var(--space-sm); transition: transform var(--transition-fast), opacity var(--transition-fast); box-shadow: var(--shadow-glow-accent); text-decoration: none; border: none; cursor: pointer; }
.primary-btn:disabled { opacity: 0.5; cursor: not-allowed; }
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
