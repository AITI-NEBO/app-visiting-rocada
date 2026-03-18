// src/composables/useDirections.js
// Управление направлениями: загрузка, выбор, персистирование

import { ref, computed } from 'vue'
import { useApi } from './useApi'

const STORAGE_KEY = 'rocadamed_direction'

const directions  = ref([])        // массив {id, name, icon} из API
const currentId   = ref(localStorage.getItem(STORAGE_KEY) || null)
const loaded      = ref(false)
const loading     = ref(false)

export function useDirections() {
  const api = useApi()

  // Текущее направление (объект)
  const currentDirection = computed(() => {
    if (!directions.value.length) return null
    return directions.value.find(d => d.id === currentId.value)
      || directions.value[0]
  })

  // Есть ли смысл показывать переключатель (если >1 направления)
  const hasMultiple = computed(() => directions.value.length > 1)

  // Загрузка списка направлений с API
  async function loadDirections() {
    if (loaded.value) return
    loading.value = true
    try {
      const data = await api.apiGet('api/directions')
      // API возвращает массив напрямую или {items: []}
      directions.value = Array.isArray(data) ? data : (data.items ?? [])
      loaded.value = true

      // Если сохранённый id больше не существует — берём первый
      if (currentId.value && !directions.value.find(d => d.id === currentId.value)) {
        setDirection(directions.value[0]?.id || null)
      }
      // Если ничего не выбрано — выбираем первое
      if (!currentId.value && directions.value.length) {
        setDirection(directions.value[0].id)
      }
    } catch (e) {
      console.error('[useDirections] loadDirections error:', e)
    } finally {
      loading.value = false
    }
  }

  function setDirection(id) {
    currentId.value = id
    if (id) {
      localStorage.setItem(STORAGE_KEY, id)
    } else {
      localStorage.removeItem(STORAGE_KEY)
    }
  }

  // Сброс при логауте
  function reset() {
    directions.value = []
    currentId.value  = null
    loaded.value     = false
    localStorage.removeItem(STORAGE_KEY)
  }

  return {
    directions,
    currentDirection,
    currentId,
    hasMultiple,
    loading,
    loaded,
    loadDirections,
    setDirection,
    reset,
  }
}
