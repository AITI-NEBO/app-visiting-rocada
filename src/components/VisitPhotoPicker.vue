<template>
  <div class="visit-photo">
    <h4 class="vp-title">
      <span class="material-symbols-rounded" style="font-size:18px">storefront</span>
      Фото входной группы клиники
      <span v-if="required" class="vp-required">*обязательно</span>
      <span v-else class="vp-optional">(необязательно)</span>
    </h4>
    <p class="vp-hint">Название клиники на фото должно читаться.</p>

    <div v-if="modelValue" class="vp-preview">
      <img :src="modelValue.preview" alt="Фото входной группы" />
      <button class="vp-remove" type="button" @click="clear">
        <span class="material-symbols-rounded">close</span>
      </button>
    </div>

    <div v-else-if="reading" class="vp-reading">
      <span class="material-symbols-rounded spin">progress_activity</span>
      Определяем дату и место съёмки…
    </div>

    <div v-else class="vp-buttons" :class="{ 'vp-buttons-single': !(allowCamera && allowGallery) }">
      <label v-if="allowCamera" class="vp-btn vp-btn-main" @click="warmUpGeo">
        <span class="material-symbols-rounded">photo_camera</span>
        Сделать фото
        <input type="file" accept="image/*" capture="environment" hidden @change="e => onPick(e, 'camera')" />
      </label>
      <label v-if="allowGallery" class="vp-btn" :class="{ 'vp-btn-main': !allowCamera }">
        <span class="material-symbols-rounded">photo_library</span>
        Из галереи
        <input type="file" accept="image/*" hidden @change="e => onPick(e, 'gallery')" />
      </label>
    </div>

    <!-- Что удалось определить по фото — менеджер видит это до отправки -->
    <div v-if="modelValue" class="vp-meta">
      <div class="vp-meta-row" :class="modelValue.meta.taken_at ? 'ok' : 'bad'">
        <span class="material-symbols-rounded">{{ modelValue.meta.taken_at ? 'event_available' : 'event_busy' }}</span>
        <span v-if="modelValue.meta.taken_at">Дата съёмки: {{ formatDate(modelValue.meta.taken_at) }}</span>
        <span v-else>Дата съёмки не определена</span>
      </div>
      <div class="vp-meta-row" :class="hasGeo ? 'ok' : 'bad'">
        <span class="material-symbols-rounded">{{ hasGeo ? 'location_on' : 'location_off' }}</span>
        <span v-if="hasGeo">
          Место съёмки определено
          <template v-if="modelValue.meta.geo_source === 'exif'">по метаданным фото</template>
          <template v-else-if="modelValue.meta.geo_source === 'device'">по телефону<template v-if="modelValue.meta.accuracy"> (±{{ Math.round(modelValue.meta.accuracy) }} м)</template></template>
        </span>
        <span v-else>Место съёмки не определено{{ geoError ? ': ' + geoError : '' }}</span>
      </div>
      <GeoHelp v-if="!hasGeo && canRetryGeo" />
      <button
        v-if="!hasGeo && canRetryGeo"
        class="vp-retry"
        type="button"
        :disabled="locating"
        @click="retryGeo"
      >
        <span class="material-symbols-rounded" :class="{ spin: locating }">{{ locating ? 'progress_activity' : 'my_location' }}</span>
        {{ locating ? 'Определяем местоположение…' : 'Определить местоположение ещё раз' }}
      </button>
      <p v-if="!hasGeo && !canRetryGeo && allowCamera" class="vp-warn">
        Чтобы определить место съёмки, удалите фото крестиком и нажмите «Сделать фото».
      </p>
      <p v-if="!modelValue.meta.taken_at || !hasGeo" class="vp-warn">
        Визит можно сохранить и так, но в сделке будет отметка «Неверная геолокация».
      </p>
    </div>

    <template v-else>
      <!-- Доступа к геолокации нет — объясняем это до съёмки, а не после -->
      <div v-if="geoDenied" class="vp-meta">
        <div class="vp-meta-row bad">
          <span class="material-symbols-rounded">location_off</span>
          <span>Нет доступа к геолокации, место съёмки не определится</span>
        </div>
        <GeoHelp />
        <button class="vp-retry" type="button" :disabled="locating" @click="checkGeo">
          <span class="material-symbols-rounded" :class="{ spin: locating }">{{ locating ? 'progress_activity' : 'my_location' }}</span>
          {{ locating ? 'Определяем местоположение…' : 'Проверить геолокацию ещё раз' }}
        </button>
      </div>
      <p class="vp-note">
        <template v-if="required">Фото без даты и места съёмки не мешает сохранить визит, но в сделке будет отметка «Неверная геолокация».</template>
        <template v-else>Без фото, а также с фото без даты и места съёмки, в сделке будет отметка «Неверная геолокация».</template>
        <template v-if="allowCamera && allowGallery"> Надёжнее снимать через «Сделать фото».</template>
      </p>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import GeoHelp from './GeoHelp.vue'

const props = defineProps({
  // { file, preview, meta: { source, picked, taken_at, lat, lng, accuracy, geo_source, exif_* } } | null
  modelValue: { type: Object, default: null },
  // Настройки направления: обязательность фото и разрешённые способы добавления
  required:     { type: Boolean, default: true },
  allowCamera:  { type: Boolean, default: true },
  allowGallery: { type: Boolean, default: true },
})
const emit = defineEmits(['update:modelValue'])

const reading  = ref(false)
const locating = ref(false)
const geoError = ref('')

// Снимок камерой считаем свежим, если файл создан не раньше этого срока
const CAMERA_FRESH_MS = 10 * 60 * 1000
// Позиция телефона годится для фото, если получена не раньше этого срока до/после съёмки
const POSITION_MAX_AGE_MS = 2 * 60 * 1000

const hasGeo = computed(() => {
  const m = props.modelValue?.meta
  return m && Number.isFinite(m.lat) && Number.isFinite(m.lng)
})

const geoDenied = computed(() => geoError.value.startsWith('нет доступа'))

// Повторно запросить геолокацию можно только для свежего снимка камерой
const canRetryGeo = computed(() => {
  const v = props.modelValue
  return v?.meta?.source === 'camera' && Date.now() - v.file.lastModified < CAMERA_FRESH_MS
})

// ── Геолокация: следим за позицией заранее, пока открыт блок фото ──
// Так к моменту съёмки координаты уже есть, и не надо ждать GPS после неё.
let watchId = null
let lastPos = null // { lat, lng, accuracy, ts }

function savePos(pos) {
  const p = {
    lat: pos.coords.latitude,
    lng: pos.coords.longitude,
    accuracy: pos.coords.accuracy,
    ts: pos.timestamp || Date.now(),
  }
  // Берём более свежую, а при равной свежести — более точную
  if (!lastPos || p.ts - lastPos.ts > 30000 || p.accuracy <= lastPos.accuracy) {
    lastPos = p
  }
  geoError.value = ''
}

function geoErrorText(err) {
  if (!navigator.geolocation) return 'телефон не поддерживает геолокацию'
  if (err?.code === 1) return 'нет доступа к геолокации, разрешите его в настройках браузера'
  if (err?.code === 3) return 'телефон не успел определить местоположение'
  return 'не удалось получить местоположение'
}

function warmUpGeo() {
  if (!navigator.geolocation || watchId !== null) return
  watchId = navigator.geolocation.watchPosition(
    savePos,
    err => { geoError.value = geoErrorText(err) },
    { enableHighAccuracy: true, maximumAge: 30000, timeout: 30000 }
  )
}

function stopWatch() {
  if (watchId !== null && navigator.geolocation) {
    navigator.geolocation.clearWatch(watchId)
  }
  watchId = null
}

function getPosition(options) {
  return new Promise(resolve => {
    if (!navigator.geolocation) {
      geoError.value = geoErrorText(null)
      return resolve(null)
    }
    navigator.geolocation.getCurrentPosition(
      pos => { savePos(pos); resolve(lastPos) },
      err => { geoError.value = geoErrorText(err); resolve(null) },
      options
    )
  })
}

// Позиция на момент съёмки: сохранённая из слежения → точный запрос → грубый запрос
async function positionForShot(shotTs) {
  if (lastPos && Math.abs(lastPos.ts - shotTs) < POSITION_MAX_AGE_MS) return lastPos
  const precise = await getPosition({ enableHighAccuracy: true, timeout: 10000, maximumAge: 0 })
  if (precise) return precise
  if (geoError.value && geoError.value.startsWith('нет доступа')) return null
  return getPosition({ enableHighAccuracy: false, timeout: 10000, maximumAge: POSITION_MAX_AGE_MS })
}

async function readExif(file) {
  try {
    const exifr = (await import('exifr')).default
    const data = await exifr.parse(file, { gps: true })
    if (!data) return {}
    const date = data.DateTimeOriginal || data.CreateDate || data.DateTimeDigitized
    const hasGps = Number.isFinite(data.latitude) && Number.isFinite(data.longitude)
      && !(data.latitude === 0 && data.longitude === 0)
    return {
      takenAt: date instanceof Date && !isNaN(date) ? date : null,
      lat: hasGps ? data.latitude : null,
      lng: hasGps ? data.longitude : null,
    }
  } catch {
    return {}
  }
}

async function onPick(e, picked) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return

  reading.value = true
  geoError.value = ''
  try {
    const exif = await readExif(file)

    // Кнопка «Сделать фото» на компьютере открывает обычный выбор файла,
    // поэтому снимком камеры считаем только что созданный файл
    const fresh  = file.lastModified && Date.now() - file.lastModified < CAMERA_FRESH_MS
    const source = picked === 'camera' && fresh ? 'camera' : 'gallery'

    const meta = {
      source,
      // Какой кнопкой добавлено фото — сервер проверит, что способ разрешён в настройках
      picked,
      taken_at: exif.takenAt ? exif.takenAt.toISOString() : null,
      lat: exif.lat ?? null,
      lng: exif.lng ?? null,
      accuracy: null,
      geo_source: exif.lat != null ? 'exif' : null,
      // Метаданные фото отдельно — сервер сверит их с данными телефона
      exif_taken_at: exif.takenAt ? exif.takenAt.toISOString() : null,
      exif_lat: exif.lat ?? null,
      exif_lng: exif.lng ?? null,
      device_taken_at: null,
      device_lat: null,
      device_lng: null,
    }

    // Снимок камерой: время создания файла и геолокация телефона в момент съёмки
    if (source === 'camera') {
      meta.device_taken_at = new Date(file.lastModified).toISOString()
      if (!meta.taken_at) meta.taken_at = meta.device_taken_at
      const pos = await positionForShot(file.lastModified)
      if (pos) {
        meta.device_lat = pos.lat
        meta.device_lng = pos.lng
        meta.accuracy   = pos.accuracy
      }
      if (meta.lat === null && pos) {
        meta.lat = pos.lat; meta.lng = pos.lng; meta.geo_source = 'device'
      }
    }

    if (meta.lat === null && geoError.value) meta.geo_error = geoError.value

    // Фото с камеры весит 3–10 МБ — уменьшаем перед отправкой, чтобы завершение
    // визита не упиралось в медленный мобильный интернет. Дата и место съёмки
    // уже прочитаны выше и уходят на сервер в meta.
    const upload = await compressPhoto(file)

    emit('update:modelValue', { file: upload, preview: URL.createObjectURL(upload), meta })
  } finally {
    reading.value = false
  }
}

const MAX_SIDE = 1920
const JPEG_QUALITY = 0.85

// Уменьшает фото до MAX_SIDE по большей стороне и пережимает в JPEG.
// Если браузер не может открыть файл (например HEIC на Android) — отправляем как есть.
async function compressPhoto(file) {
  if (file.size < 1024 * 1024) return file
  try {
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
    const scale  = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height))
    const canvas = document.createElement('canvas')
    canvas.width  = Math.round(bitmap.width * scale)
    canvas.height = Math.round(bitmap.height * scale)
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height)
    bitmap.close?.()
    const blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', JPEG_QUALITY))
    if (!blob || blob.size >= file.size) return file
    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg'
    return new File([blob], name, { type: 'image/jpeg', lastModified: file.lastModified })
  } catch {
    return file
  }
}

// Проверка после того, как сотрудник включил геолокацию в настройках
async function checkGeo() {
  if (locating.value) return
  locating.value = true
  try {
    await getPosition({ enableHighAccuracy: false, timeout: 15000, maximumAge: 0 })
  } finally {
    locating.value = false
  }
}

async function retryGeo() {
  const v = props.modelValue
  if (!v || locating.value) return
  locating.value = true
  try {
    const pos = await getPosition({ enableHighAccuracy: true, timeout: 15000, maximumAge: 0 })
      || await getPosition({ enableHighAccuracy: false, timeout: 15000, maximumAge: 0 })
    if (pos) {
      const { geo_error, ...rest } = v.meta
      emit('update:modelValue', {
        ...v,
        meta: {
          ...rest,
          lat: pos.lat, lng: pos.lng, accuracy: pos.accuracy, geo_source: 'device',
          device_lat: pos.lat, device_lng: pos.lng,
        },
      })
    }
  } finally {
    locating.value = false
  }
}

function formatDate(iso) {
  const d = new Date(iso)
  return isNaN(d) ? '' : d.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function clear() {
  if (props.modelValue?.preview) URL.revokeObjectURL(props.modelValue.preview)
  emit('update:modelValue', null)
}

onMounted(warmUpGeo)

onBeforeUnmount(() => {
  stopWatch()
  if (props.modelValue?.preview) URL.revokeObjectURL(props.modelValue.preview)
})
</script>

<style scoped>
.visit-photo { display: flex; flex-direction: column; gap: var(--space-sm); }
.vp-title { display: flex; align-items: center; gap: var(--space-sm); font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); }
.vp-required { color: var(--color-danger); font-size: 12px; margin-left: 4px; font-weight: normal; }
.vp-hint, .vp-note { font-size: var(--font-size-xs); color: var(--color-text-tertiary); }
.vp-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-sm); }
.vp-buttons-single { grid-template-columns: 1fr; }
.vp-optional { color: var(--color-text-tertiary); font-size: 12px; margin-left: 4px; font-weight: normal; }
.vp-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; min-height: 96px; border-radius: var(--radius-md); border: 2px dashed var(--color-border); cursor: pointer; color: var(--color-text-secondary); font-size: var(--font-size-sm); font-weight: var(--font-weight-medium); text-align: center; padding: var(--space-sm); }
.vp-btn .material-symbols-rounded { font-size: 28px; }
.vp-btn-main { border-color: var(--color-primary); color: var(--color-primary); }
.vp-reading { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 96px; color: var(--color-text-secondary); font-size: var(--font-size-sm); }
.vp-preview { position: relative; width: 100%; max-height: 280px; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--color-border); }
.vp-preview img { width: 100%; max-height: 280px; object-fit: cover; display: block; }
.vp-remove { position: absolute; top: 8px; right: 8px; width: 32px; height: 32px; border-radius: 50%; background: rgba(0,0,0,0.6); color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; border: none; }
.vp-remove .material-symbols-rounded { font-size: 18px; }
.vp-meta { display: flex; flex-direction: column; gap: 6px; }
.vp-meta-row { display: flex; align-items: center; gap: 6px; font-size: var(--font-size-xs); }
.vp-meta-row .material-symbols-rounded { font-size: 16px; }
.vp-meta-row.ok { color: var(--color-success, #2e7d32); }
.vp-meta-row.bad { color: var(--color-danger); }
.vp-retry { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 8px; border-radius: var(--radius-md); border: 1px solid var(--color-primary); background: transparent; color: var(--color-primary); font-size: var(--font-size-sm); cursor: pointer; }
.vp-retry:disabled { opacity: 0.6; cursor: default; }
.vp-retry .material-symbols-rounded { font-size: 18px; }
.vp-warn { font-size: var(--font-size-xs); color: var(--color-danger); }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
