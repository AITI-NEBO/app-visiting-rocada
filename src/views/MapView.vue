<template>
  <div class="map-page">
    <!-- Full-screen map -->
    <div ref="mapEl" class="map-fullscreen"></div>

    <!-- Overlay: Header -->
    <div class="map-header">
      <div class="map-header-inner glass">
        <h2 class="map-title">Карта</h2>
        <div class="tabs">
          <button class="tab" :class="{ active: activeTab === 'today' }" @click="switchTab('today')">
            <span class="material-symbols-rounded" style="font-size:16px">today</span>
            Визиты
          </button>
          <button class="tab" :class="{ active: activeTab === 'clients' }" @click="switchTab('clients')">
            <span class="material-symbols-rounded" style="font-size:16px">people</span>
            Клиенты
          </button>
        </div>
      </div>
    </div>

    <!-- Overlay: Bottom sheet (list) -->
    <div
      class="sheet"
      :class="{ expanded: sheetExpanded }"
      @touchstart="onSheetTouchStart"
      @touchmove="onSheetTouchMove"
      @touchend="onSheetTouchEnd"
    >
      <div class="sheet-handle-area" @click="sheetExpanded = !sheetExpanded">
        <div class="sheet-handle"></div>
      </div>

      <!-- Today's visits -->
      <div v-if="activeTab === 'today'" class="sheet-content">
        <p class="sheet-subtitle">{{ todayVisits.length }} визитов сегодня</p>
        <div class="sheet-list">
          <div
            v-for="v in todayVisits"
            :key="v.id"
            class="sheet-item"
            @click="panTo(v.lat, v.lng, v)"
          >
            <div class="sheet-marker" :style="{ background: stageColor(v.stage_id) }">
              <span class="material-symbols-rounded" style="font-size:14px; color:white">event</span>
            </div>
            <div class="sheet-item-info">
              <span class="sheet-item-name">{{ v.title }}</span>
              <span class="sheet-item-addr">{{ v.stage_name || v.stage_id }} · {{ v.date || '' }}</span>
            </div>
            <div class="sheet-item-meta">
              <button class="sheet-plan-btn" @click.stop="planVisit(v)" title="Запланировать">
                <span class="material-symbols-rounded" style="font-size:16px">calendar_add_on</span>
              </button>
              <router-link :to="`/visits/${v.id}`" class="sheet-item-link" @click.stop>
                <span class="material-symbols-rounded" style="font-size:16px">open_in_new</span>
              </router-link>
            </div>
          </div>
        </div>
      </div>

      <!-- All clients -->
      <div v-if="activeTab === 'clients'" class="sheet-content">
        <p class="sheet-subtitle">{{ allClients.length }} клиентов</p>
        <div class="sheet-list">
          <div
            v-for="c in allClients"
            :key="c.deal_id || c.id"
            class="sheet-item"
            @click="panTo(c.lat, c.lng, c)"
          >
            <div class="sheet-marker" style="background: #94A3B8">
              <span class="material-symbols-rounded" style="font-size:14px; color:white">domain</span>
            </div>
            <div class="sheet-item-info">
              <span class="sheet-item-name">{{ c.company_name || c.deal_title || c.title || '—' }}</span>
              <span class="sheet-item-addr">{{ c.company_address || c.stage_name || '' }}</span>
            </div>
            <router-link :to="`/visits/${c.deal_id || c.id}`" class="sheet-item-link" @click.stop>
              <span class="material-symbols-rounded" style="font-size:18px; color: var(--color-primary)">location_on</span>
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- Overlay: My location fab -->
    <button class="fab-location glass" @click="goToMyLocation" :class="{ locating: isLocating }">
      <span class="material-symbols-rounded">{{ isLocating ? 'gps_fixed' : 'my_location' }}</span>
    </button>

    <!-- Plan visit modal -->
    <div v-if="showPlanModal" class="modal-overlay" @click.self="showPlanModal = false">
      <div class="modal glass animate-scale-in">
        <h3 class="modal-title">Запланировать визит</h3>
        <p class="modal-subtitle">{{ selectedClient?.company_name || selectedClient?.title || '' }}</p>
        <div class="modal-field">
          <label>Пункт разгрузки</label>
          <select v-model="planPointId" class="modal-input">
            <option value="" disabled>Выберите пункт</option>
            <option v-for="pt in unloadPoints" :key="pt.id" :value="pt.id">{{ pt.name }}</option>
          </select>
        </div>
        <div class="modal-field">
          <label>Дата</label>
          <input type="date" v-model="planDate" class="modal-input" />
        </div>
        <div class="modal-field">
          <label>Время</label>
          <input type="time" v-model="planTime" class="modal-input" />
        </div>
        <div class="modal-actions">
          <button class="btn-secondary" @click="showPlanModal = false" :disabled="isPlanning">Отмена</button>
          <button class="btn-primary" @click="confirmPlan" :disabled="isPlanning">
            <span class="material-symbols-rounded" style="font-size:18px" v-if="!isPlanning">check</span>
            {{ isPlanning ? 'Отправка...' : 'Запланировать' }}
          </button>
        </div>

      </div>
    </div>

    <!-- Toast -->
    <transition name="toast">
      <div v-if="toastMsg" class="toast">
        <span class="material-symbols-rounded" style="font-size:18px">check_circle</span>
        {{ toastMsg }}
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useVisits } from '../composables/useVisits'

const route = useRoute()

const { visitsToday, loadVisits, fetchUnloadPoints, planVisitApi } = useVisits()

const activeTab = ref('today')
const mapEl = ref(null)
const todayVisits = visitsToday
const allClients = visitsToday  // пока клиенты = визиты (API клиентов можно добавить позже)

function stageColor(stageId) {
  if (!stageId) return '#94A3B8'
  const short = stageId.includes(':') ? stageId.split(':')[1] : stageId
  const map = { NEW: '#4DA6FF', PREPARATION: '#FFB020', PREPAYMENT_INVOICE: '#A78BFA', EXECUTING: '#FF6B35', WON: '#00C48C', LOSE: '#FF4D6A' }
  return map[short] || '#4DA6FF'
}
const sheetExpanded = ref(false)
const isLocating = ref(false)

const showPlanModal = ref(false)
const selectedClient = ref(null)
const planDate = ref('')
const planTime = ref('10:00')
const planPointId = ref('')
const unloadPoints = ref([])
const isPlanning = ref(false)
const toastMsg = ref('')

let mapInstance = null

// Touch handling for sheet
let touchStartY = 0
function onSheetTouchStart(e) { touchStartY = e.touches[0].clientY }
function onSheetTouchMove(e) { /* handled by CSS scroll */ }
function onSheetTouchEnd(e) {
  const delta = touchStartY - e.changedTouches[0].clientY
  if (Math.abs(delta) > 60) {
    sheetExpanded.value = delta > 0
  }
}

function showToast(msg) {
  toastMsg.value = msg
  setTimeout(() => { toastMsg.value = '' }, 2500)
}

function switchTab(tab) {
  activeTab.value = tab
  updateMarkers()
}

function panTo(lat, lng, item) {
  if (mapInstance) {
    mapInstance.setCenter([lat, lng], 16, { duration: 400 })
  }
  sheetExpanded.value = false
}

function goToMyLocation() {
  if (!navigator.geolocation) {
    showToast('Геолокация не поддерживается браузером')
    return
  }
  isLocating.value = true
  showToast('Определяем местоположение…')

  const applyPos = (pos) => {
    isLocating.value = false
    if (mapInstance) {
      mapInstance.setCenter([pos.coords.latitude, pos.coords.longitude], 15, { duration: 500 })
    }    
  }

  const onError = (err) => {
    // Фаза 2: попробовать без кэша и с высокой точностью
    if (err.code === 3) {
      navigator.geolocation.getCurrentPosition(
        applyPos,
        (err2) => {
          isLocating.value = false
          const msg = err2.code === 1
            ? 'Нет разрешения на геолокацию (разрешите в браузере)'
            : err2.code === 2
            ? 'Местоположение недоступно — включите GPS или Wi-Fi'
            : 'Не удалось определить местоположение'
          showToast(msg)
          console.warn('[MapView] geo error phase2:', err2.code, err2.message)
        },
        { timeout: 30000, enableHighAccuracy: true, maximumAge: 0 }
      )
    } else {
      isLocating.value = false
      const msg = err.code === 1
        ? 'Нет разрешения на геолокацию (разрешите в браузере)'
        : 'Местоположение недоступно — включите GPS или Wi-Fi'
      showToast(msg)
      console.warn('[MapView] geo error:', err.code, err.message)
    }
  }

  // Фаза 1: взять закэшированную позицию (мгновенно если есть)
  navigator.geolocation.getCurrentPosition(
    applyPos,
    onError,
    { timeout: 5000, enableHighAccuracy: false, maximumAge: 300000 }
  )
}

async function planVisit(client) {
  // Планировать можно любой визит — нет ограничения по hasPlannedVisit
  selectedClient.value = client
  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  planDate.value = tomorrow.toISOString().split('T')[0]
  planTime.value = '10:00'
  planPointId.value = ''
  
  showToast('Загрузка пунктов разгрузки...')
  try {
    const dealId = client.deal_id || client.id
    const res = await fetchUnloadPoints(dealId)
    unloadPoints.value = res.points || []
    if (unloadPoints.value.length > 0) {
        planPointId.value = unloadPoints.value[0].id
    } else {
        showToast('Точки разгрузки не найдены для этой компании')
    }
  } catch (e) {
    console.error(e)
    showToast('Ошибка загрузки пунктов разгрузки')
    unloadPoints.value = []
  }

  showPlanModal.value = true
}

async function confirmPlan() {
  if (!planDate.value || !planTime.value || !planPointId.value) {
    showToast('Заполните все поля')
    return
  }

  isPlanning.value = true
  try {
    const dealId = selectedClient.value.deal_id || selectedClient.value.id
    await planVisitApi(dealId, {
      point_id: planPointId.value,
      visit_date: planDate.value,
      visit_time: planTime.value
    })
    
    showPlanModal.value = false
    if (selectedClient.value) {
      selectedClient.value.hasPlannedVisit = true
    }
    showToast('Визит запланирован!')
    updateMarkers()
  } catch (e) {
    console.error(e)
    showToast(e.message || 'Ошибка планирования визита')
  } finally {
    isPlanning.value = false
  }
}

function handleMessage(event) {
    if (event.data && event.data.type === 'scheduleVisit') {
        const dealId = event.data.dealId
        if (dealId) {
            const client = allClients.value.find(c => (c.deal_id == dealId || c.id == dealId))
            if (client) {
                planVisit(client)
            } else {
                planVisit({ id: dealId, title: `Сделка #${dealId}`, company_name: `Сделка #${dealId}` })
            }
        }
    }
}

function updateMarkers() {
  if (!mapInstance || !window.ymaps) return
  mapInstance.geoObjects.removeAll()

  const items = activeTab.value === 'today' ? todayVisits.value : allClients.value

  items.forEach((item) => {
    const lat = item.geoLat || item.lat
    const lng = item.geoLng || item.lng
    if (!lat || !lng) return

  const isVisit = activeTab.value === 'today'
    const color = isVisit
      ? (item.stage_id?.includes('WON') ? '#00C48C' : item.stage_id?.includes('LOSE') ? '#FF4D6A' : '#4DA6FF')
      : '#94A3B8'

    const placemark = new ymaps.Placemark(
      [lat, lng],
      {
        hintContent: item.title,
        balloonContentHeader: item.title,
        balloonContentBody: `<b>${item.stage_name || item.stage_id || ''}</b><br/>${item.date || ''}<br/>${item.comments ? item.comments.substring(0, 100) : ''}`
      },
      {
        preset: 'islands#dotIcon',
        iconColor: color
      }
    )

    mapInstance.geoObjects.add(placemark)
  })
}

onMounted(async () => {
  window.addEventListener('message', handleMessage)
  await loadVisits()
  
  if (route.query.planDealId) {
    planVisit({ 
      id: route.query.planDealId, 
      deal_id: route.query.planDealId,
      title: route.query.title || `Сделка #${route.query.planDealId}`,
      hasPlannedVisit: false
    })
  }

  // Load Yandex Maps 2.1 (works without API key)
  await new Promise((resolve) => {
    if (window.ymaps) { resolve(); return }
    const script = document.createElement('script')
    script.src = 'https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=99a2f189-c861-4b8a-90e7-34fa2c7add0e'
    script.onload = resolve
    document.head.appendChild(script)
  })

  ymaps.ready(() => {
    mapInstance = new ymaps.Map(mapEl.value, {
      center: [55.7950, 49.1221],
      zoom: 12,
      controls: ['zoomControl', 'geolocationControl']
    }, {
      suppressMapOpenBlock: true
    })

    updateMarkers()
  })
})

onUnmounted(() => {
  window.removeEventListener('message', handleMessage)
})

watch(activeTab, () => {
  nextTick(() => updateMarkers())
})
</script>

<style scoped>
.map-page {
  position: relative;
  width: 100%;
  height: 100dvh;
  overflow: hidden;
}

/* Full-screen map */
.map-fullscreen {
  position: absolute;
  inset: 0;
  z-index: 0;
}

/* ─── Overlay: Header ─── */
.map-header {
  position: absolute;
  top: 0; left: 0; right: 0;
  z-index: 10;
  padding: var(--space-sm) var(--space-base);
  padding-top: calc(var(--space-sm) + env(safe-area-inset-top, 0px));
  pointer-events: none;
}

.map-header-inner {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-sm) var(--space-md);
  border-radius: var(--radius-xl);
  pointer-events: auto;
}

.map-title {
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-bold);
  white-space: nowrap;
}

.tabs { display: flex; gap: var(--space-xs); flex: 1; }

.tab {
  flex: 1;
  display: flex; align-items: center; justify-content: center; gap: 4px;
  padding: 8px 10px; border-radius: var(--radius-lg);
  font-size: var(--font-size-xs); font-weight: var(--font-weight-semibold);
  color: var(--color-text-secondary); cursor: pointer;
  background: transparent; border: none;
  transition: all var(--transition-fast);
}

.tab.active {
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  color: white;
}

/* ─── Overlay: Bottom sheet ─── */
.sheet {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  z-index: 10;
  max-height: 20dvh;
  background: var(--color-bg-card);
  border-radius: var(--radius-xl) var(--radius-xl) 0 0;
  box-shadow: 0 -4px 24px rgba(0,0,0,0.12);
  transition: max-height 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  flex-direction: column;
  padding-bottom: calc(var(--bottom-nav-height) + var(--safe-area-bottom));
}

.sheet.expanded {
  max-height: 70dvh;
}

@media (min-width: 768px) {
  .sheet {
    left: var(--sidebar-width);
    max-height: 18dvh;
    padding-bottom: 0;
  }
  .sheet.expanded { max-height: 65dvh; }
  .map-header { left: var(--sidebar-width); }
  .fab-location { left: calc(var(--sidebar-width) + var(--space-base)); }
}

.sheet-handle-area {
  display: flex; justify-content: center;
  padding: var(--space-sm) 0;
  cursor: pointer; flex-shrink: 0;
}

.sheet-handle {
  width: 36px; height: 4px; border-radius: 4px;
  background: var(--color-text-tertiary); opacity: 0.5;
}

.sheet-content {
  flex: 1; overflow-y: auto;
  padding: 0 var(--space-base);
  -webkit-overflow-scrolling: touch;
}

.sheet-subtitle {
  font-size: var(--font-size-xs);
  color: var(--color-text-tertiary);
  font-weight: var(--font-weight-semibold);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: var(--space-sm);
  padding: 0 var(--space-xs);
}

.sheet-list { display: flex; flex-direction: column; gap: var(--space-xs); }

.sheet-item {
  display: flex; align-items: center; gap: var(--space-md);
  padding: var(--space-md); border-radius: var(--radius-md);
  cursor: pointer;
  transition: background var(--transition-fast);
}

.sheet-item:active { background: var(--color-bg-card-hover); }

.sheet-marker {
  width: 32px; height: 32px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

.sheet-item-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.sheet-item-name { font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sheet-item-addr { font-size: var(--font-size-xs); color: var(--color-text-tertiary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.sheet-item-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.sheet-item-time { font-size: var(--font-size-xs); font-weight: var(--font-weight-bold); color: var(--color-primary); }

.sheet-item-link {
  width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;
  border-radius: 50%; background: var(--color-bg-elevated);
  color: var(--color-text-secondary); text-decoration: none;
}

.sheet-plan-btn {
  width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: var(--color-bg-elevated); border: none; cursor: pointer;
  flex-shrink: 0;
}

.sheet-plan-btn .material-symbols-rounded { font-size: 20px; }

/* ─── FAB ─── */
.fab-location {
  position: absolute;
  z-index: 10;
  bottom: calc(45dvh + var(--space-sm));
  right: var(--space-base);
  width: 44px; height: 44px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; border: none;
  color: var(--color-primary);
  transition: all var(--transition-fast);
}

.fab-location.locating { color: var(--color-accent); }
.fab-location:active { transform: scale(0.9); }
.fab-location .material-symbols-rounded { font-size: 24px; }

/* ─── Modal ─── */
.modal-overlay {
  position: fixed; inset: 0; z-index: 100;
  background: var(--color-bg-overlay);
  display: flex; align-items: center; justify-content: center;
  padding: var(--space-base);
}

.modal {
  width: 100%; max-width: 340px;
  border-radius: var(--radius-xl);
  padding: var(--space-lg);
  display: flex; flex-direction: column; gap: var(--space-md);
}

.modal-title { font-size: var(--font-size-lg); font-weight: var(--font-weight-bold); }
.modal-subtitle { font-size: var(--font-size-sm); color: var(--color-text-secondary); }

.modal-field { display: flex; flex-direction: column; gap: 4px; }
.modal-field label { font-size: var(--font-size-xs); font-weight: var(--font-weight-semibold); color: var(--color-text-tertiary); text-transform: uppercase; letter-spacing: 0.5px; }

.modal-input {
  padding: var(--space-md); border-radius: var(--radius-md);
  background: var(--color-bg-input); border: 1px solid var(--color-border);
  color: var(--color-text-primary); font-size: var(--font-size-base);
}

.modal-input:focus { border-color: var(--color-primary); outline: none; }

.modal-actions { display: flex; gap: var(--space-sm); margin-top: var(--space-xs); }

.btn-primary {
  flex: 1; height: 44px; border-radius: var(--radius-lg);
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  color: white; font-weight: var(--font-weight-semibold); font-size: var(--font-size-sm);
  display: flex; align-items: center; justify-content: center; gap: 6px;
  border: none; cursor: pointer;
}

.btn-secondary {
  flex: 1; height: 44px; border-radius: var(--radius-lg);
  background: var(--color-bg-elevated); color: var(--color-text-primary);
  font-weight: var(--font-weight-semibold); font-size: var(--font-size-sm);
  border: none; cursor: pointer;
}

.demo-hint {
  display: flex; align-items: center; gap: 6px;
  font-size: var(--font-size-xs); color: var(--color-warning);
  padding: var(--space-sm) var(--space-md);
  background: rgba(255,176,32,0.08); border-radius: var(--radius-md);
}

/* ─── Toast ─── */
.toast {
  position: fixed;
  bottom: calc(45dvh + var(--space-xl));
  left: 50%; transform: translateX(-50%);
  display: flex; align-items: center; gap: var(--space-sm);
  padding: var(--space-md) var(--space-lg);
  border-radius: var(--radius-full);
  background: var(--color-success); color: white;
  font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold);
  box-shadow: var(--shadow-lg); z-index: 200;
  white-space: nowrap;
}

.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(12px); }
</style>
