<template>
  <div class="geo-access">
    <div v-if="state === 'checking'" class="ga-checking">
      <span class="material-symbols-rounded spin ga-icon">my_location</span>
      <p class="ga-text">Проверяем доступ к геолокации…</p>
    </div>

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
import { ref, computed, onMounted } from 'vue'
import GeoHelp from './GeoHelp.vue'
import { checkGeoAccess } from '../composables/useGeolocation'

const emit = defineEmits(['ready'])

const state = ref('checking')

const title = computed(() => ({
  denied: 'Нет доступа к геолокации',
  unavailable: 'Геолокация на телефоне выключена',
  unsupported: 'Браузер не поддерживает геолокацию. Откройте приложение в Safari или Chrome',
}[state.value] || ''))

async function check() {
  state.value = 'checking'
  const res = await checkGeoAccess()
  if (res === 'granted') {
    emit('ready')
  } else {
    state.value = res
  }
}

onMounted(check)
</script>

<style scoped>
.geo-access { display: flex; flex-direction: column; align-items: center; gap: var(--space-md); padding: var(--space-xl) 0; text-align: center; }
.ga-checking { display: flex; flex-direction: column; align-items: center; gap: var(--space-sm); }
.ga-icon { font-size: 48px; color: var(--color-primary); }
.ga-icon-bad { color: var(--color-danger); }
.ga-title { font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); }
.ga-text { font-size: var(--font-size-sm); color: var(--color-text-secondary); max-width: 320px; }
.ga-btn { width: 100%; height: 52px; border-radius: var(--radius-lg); background: linear-gradient(135deg, var(--color-accent), var(--color-primary)); color: white; font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); display: flex; align-items: center; justify-content: center; gap: var(--space-sm); border: none; cursor: pointer; }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
