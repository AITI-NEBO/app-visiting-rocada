<template>
  <div class="geo-access">
    <!-- Местоположение определено: карта, «Определить заново» и «Далее» -->
    <template v-if="pos">
      <div class="ga-map-wrap">
        <div ref="mapEl" class="ga-map"></div>
        <div v-if="busy" class="ga-map-overlay">
          <span class="material-symbols-rounded spin ga-icon">my_location</span>
        </div>
      </div>
      <p class="ga-ok">Местоположение определено</p>
      <p class="ga-hint">
        {{ pos.lat.toFixed(6) }}, {{ pos.lng.toFixed(6) }}<template v-if="pos.accuracy"> · точность ±{{ Math.round(pos.accuracy) }} м</template>
      </p>
      <p class="ga-hint">
        Красная метка — вы<template v-if="hasPoint">, синяя — точка визита</template>.
        Если метка стоит не там, нажмите «Определить заново».
      </p>
      <p v-if="relocateError" class="ga-err">Не удалось обновить: {{ relocateError }}</p>

      <button class="ga-btn" type="button" :disabled="busy" @click="emit('ready')">
        <span class="material-symbols-rounded">arrow_forward</span>
        Далее
      </button>
      <button class="ga-btn-secondary" type="button" :disabled="busy" @click="locate">
        <span class="material-symbols-rounded" :class="{ spin: busy }">{{ busy ? 'progress_activity' : 'my_location' }}</span>
        {{ busy ? 'Определяем…' : 'Определить заново' }}
      </button>
    </template>

    <div v-else-if="state === 'checking' || busy" class="ga-checking">
      <span class="material-symbols-rounded spin ga-icon">my_location</span>
      <p class="ga-text">{{ busy ? 'Определяем местоположение…' : 'Проверяем доступ к геолокации…' }}</p>
    </div>

    <!-- Не удалось: что и где включить -->
    <template v-else>
      <span class="material-symbols-rounded ga-icon ga-icon-bad">location_off</span>
      <h3 class="ga-title">{{ title }}</h3>
      <p class="ga-text">
        Без геолокации завершить визит нельзя: место и время фиксируются в момент сохранения итога.
        Включите геолокацию по инструкции и нажмите «Проверить снова».
      </p>
      <GeoHelp v-if="state !== 'unsupported'" />
      <button class="ga-btn" type="button" @click="check">
        <span class="material-symbols-rounded">refresh</span>
        Проверить снова
      </button>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import GeoHelp from './GeoHelp.vue'
import { checkGeoAccess, getSaveLocation } from '../composables/useGeolocation'

const props = defineProps({
  // Координаты точки визита — синяя метка на карте (если известны)
  pointLat: { type: Number, default: null },
  pointLng: { type: Number, default: null },
})
const emit = defineEmits(['ready'])

const state = ref('checking') // checking | denied | unavailable | unsupported | failed
const busy  = ref(false)
const pos   = ref(null)       // { lat, lng, accuracy }
const relocateError = ref('')
const mapEl = ref(null)

const hasPoint = computed(() => Number.isFinite(props.pointLat) && Number.isFinite(props.pointLng))

const title = computed(() => ({
  denied: 'Нет доступа к геолокации',
  unavailable: 'Геолокация на телефоне выключена',
  unsupported: 'Браузер не поддерживает геолокацию. Откройте приложение в Safari или Chrome',
  failed: 'Не удалось определить местоположение',
}[state.value] || ''))

async function check() {
  state.value = 'checking'
  const res = await checkGeoAccess()
  if (res !== 'granted') {
    state.value = res
    return
  }
  await locate()
}

async function locate() {
  busy.value = true
  relocateError.value = ''
  try {
    const loc = await getSaveLocation()
    if (loc.ok) {
      pos.value = { lat: loc.lat, lng: loc.lng, accuracy: loc.accuracy }
      await nextTick()
      renderMap()
    } else if (pos.value) {
      // Уже есть точка на карте — оставляем её и пишем, почему не обновилось
      relocateError.value = loc.error
    } else {
      state.value = loc.code === 1 ? 'denied' : loc.code === 2 ? 'unavailable' : 'failed'
    }
  } finally {
    busy.value = false
  }
}

// ── Карта (Яндекс) ──
let map = null
let meMark = null

function loadYmaps() {
  return new Promise(resolve => {
    if (window.ymaps) return window.ymaps.ready(resolve)
    const script = document.createElement('script')
    script.src = 'https://api-maps.yandex.ru/2.1/?lang=ru_RU'
    script.onload = () => window.ymaps.ready(resolve)
    script.onerror = () => resolve() // без карты шаг всё равно работает
    document.head.appendChild(script)
  })
}

async function renderMap() {
  await loadYmaps()
  if (!window.ymaps || !mapEl.value || !pos.value) return
  const me = [pos.value.lat, pos.value.lng]

  if (!map) {
    map = new window.ymaps.Map(mapEl.value, { center: me, zoom: 16, controls: ['zoomControl'] }, { suppressMapOpenBlock: true })
    if (hasPoint.value) {
      map.geoObjects.add(new window.ymaps.Placemark([props.pointLat, props.pointLng], { hintContent: 'Точка визита' }, { preset: 'islands#blueDotIcon' }))
    }
  }
  if (meMark) map.geoObjects.remove(meMark)
  meMark = new window.ymaps.Placemark(me, { hintContent: 'Вы здесь' }, { preset: 'islands#redDotIcon' })
  map.geoObjects.add(meMark)

  const bounds = hasPoint.value ? map.geoObjects.getBounds() : null
  if (bounds) {
    map.setBounds(bounds, { checkZoomRange: true, zoomMargin: 30 })
  } else {
    map.setCenter(me, 16)
  }
}

onMounted(check)
</script>

<style scoped>
.geo-access { display: flex; flex-direction: column; align-items: center; gap: var(--space-md); padding: var(--space-lg) 0; text-align: center; }
.ga-checking { display: flex; flex-direction: column; align-items: center; gap: var(--space-sm); padding: var(--space-xl) 0; }
.ga-icon { font-size: 48px; color: var(--color-primary); }
.ga-icon-bad { color: var(--color-danger); }
.ga-title { font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); }
.ga-text { font-size: var(--font-size-sm); color: var(--color-text-secondary); max-width: 320px; }
.ga-ok { font-size: var(--font-size-sm); color: var(--color-success); font-weight: var(--font-weight-semibold); }
.ga-hint { font-size: var(--font-size-xs); color: var(--color-text-tertiary); max-width: 320px; }
.ga-err { font-size: var(--font-size-xs); color: var(--color-danger); }
.ga-map-wrap { position: relative; width: 100%; }
.ga-map { width: 100%; height: 260px; border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); background: var(--color-bg-card); }
.ga-map-overlay { position: absolute; inset: 0; background: rgba(255,255,255,0.7); display: flex; align-items: center; justify-content: center; border-radius: var(--radius-lg); z-index: 10; }
.ga-btn { width: 100%; height: 52px; border-radius: var(--radius-lg); background: linear-gradient(135deg, var(--color-accent), var(--color-primary)); color: white; font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); display: flex; align-items: center; justify-content: center; gap: var(--space-sm); border: none; cursor: pointer; }
.ga-btn:disabled, .ga-btn-secondary:disabled { opacity: 0.5; cursor: not-allowed; }
.ga-btn-secondary { width: 100%; height: 48px; border-radius: var(--radius-lg); background: var(--color-bg-card); color: var(--color-text-primary); font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); display: flex; align-items: center; justify-content: center; gap: var(--space-sm); border: 1px solid var(--color-border); cursor: pointer; }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
