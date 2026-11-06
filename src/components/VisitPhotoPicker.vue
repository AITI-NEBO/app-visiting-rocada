<template>
  <div class="visit-photo">
    <h4 class="vp-title">
      <span class="material-symbols-rounded" style="font-size:18px">storefront</span>
      Фото входной группы клиники
      <span class="vp-required">*обязательно</span>
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

    <div v-else class="vp-buttons">
      <label class="vp-btn vp-btn-main">
        <span class="material-symbols-rounded">photo_camera</span>
        Сделать фото
        <input type="file" accept="image/*" capture="environment" hidden @change="e => onPick(e, 'camera')" />
      </label>
      <label class="vp-btn">
        <span class="material-symbols-rounded">photo_library</span>
        Из галереи
        <input type="file" accept="image/*" hidden @change="e => onPick(e, 'gallery')" />
      </label>
    </div>

    <p class="vp-note">
      Фото из галереи без даты и геолокации съёмки отмечаются как «Неверная геолокация».
      Надёжнее снимать через «Сделать фото».
    </p>
  </div>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue'

const props = defineProps({
  // { file, preview, meta: { source, taken_at, lat, lng } } | null
  modelValue: { type: Object, default: null },
  // Координаты с шага геолокации — запасной вариант, если телефон не отдаст их повторно
  fallbackLat: { type: [String, Number], default: '' },
  fallbackLng: { type: [String, Number], default: '' },
})
const emit = defineEmits(['update:modelValue'])

const reading = ref(false)

// Снимок камерой считаем свежим, если файл создан не раньше этого срока
const CAMERA_FRESH_MS = 10 * 60 * 1000

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

function currentPosition() {
  return new Promise(resolve => {
    if (!navigator.geolocation) return resolve(null)
    navigator.geolocation.getCurrentPosition(
      pos => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
      () => resolve(null),
      { timeout: 15000, enableHighAccuracy: true, maximumAge: 60000 }
    )
  })
}

async function onPick(e, picked) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return

  reading.value = true
  try {
    const exif = await readExif(file)

    // Кнопка «Сделать фото» на компьютере открывает обычный выбор файла,
    // поэтому снимком камеры считаем только что созданный файл
    const fresh  = file.lastModified && Date.now() - file.lastModified < CAMERA_FRESH_MS
    const source = picked === 'camera' && fresh ? 'camera' : 'gallery'

    let takenAt = exif.takenAt || null
    let lat     = exif.lat ?? null
    let lng     = exif.lng ?? null

    // Для снимка камерой без метаданных — время файла и текущая геолокация телефона
    if (source === 'camera') {
      if (!takenAt) takenAt = new Date(file.lastModified)
      if (lat === null) {
        const pos = await currentPosition()
        if (pos) {
          lat = pos.lat; lng = pos.lng
        } else if (props.fallbackLat && props.fallbackLng) {
          lat = parseFloat(props.fallbackLat); lng = parseFloat(props.fallbackLng)
        }
      }
    }

    emit('update:modelValue', {
      file,
      preview: URL.createObjectURL(file),
      meta: {
        source,
        taken_at: takenAt ? takenAt.toISOString() : null,
        lat,
        lng,
      },
    })
  } finally {
    reading.value = false
  }
}

function clear() {
  if (props.modelValue?.preview) URL.revokeObjectURL(props.modelValue.preview)
  emit('update:modelValue', null)
}

onBeforeUnmount(() => {
  if (props.modelValue?.preview) URL.revokeObjectURL(props.modelValue.preview)
})
</script>

<style scoped>
.visit-photo { display: flex; flex-direction: column; gap: var(--space-sm); }
.vp-title { display: flex; align-items: center; gap: var(--space-sm); font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); }
.vp-required { color: var(--color-danger); font-size: 12px; margin-left: 4px; font-weight: normal; }
.vp-hint, .vp-note { font-size: var(--font-size-xs); color: var(--color-text-tertiary); }
.vp-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-sm); }
.vp-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; min-height: 96px; border-radius: var(--radius-md); border: 2px dashed var(--color-border); cursor: pointer; color: var(--color-text-secondary); font-size: var(--font-size-sm); font-weight: var(--font-weight-medium); text-align: center; padding: var(--space-sm); }
.vp-btn .material-symbols-rounded { font-size: 28px; }
.vp-btn-main { border-color: var(--color-primary); color: var(--color-primary); }
.vp-reading { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 96px; color: var(--color-text-secondary); font-size: var(--font-size-sm); }
.vp-preview { position: relative; width: 100%; max-height: 280px; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--color-border); }
.vp-preview img { width: 100%; max-height: 280px; object-fit: cover; display: block; }
.vp-remove { position: absolute; top: 8px; right: 8px; width: 32px; height: 32px; border-radius: 50%; background: rgba(0,0,0,0.6); color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; border: none; }
.vp-remove .material-symbols-rounded { font-size: 18px; }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
