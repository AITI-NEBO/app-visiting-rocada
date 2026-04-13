<template>
  <div class="map-page">
    <!-- Full-screen map -->
    <div ref="mapEl" class="map-fullscreen"></div>

    <!-- Overlay: Header -->
    <div class="map-header">
      <div class="map-header-inner glass">
        <h2 class="map-title">Картика Компаний</h2>
      </div>
    </div>

    <!-- Build Route Button -->
    <button
      v-if="activeTab === 'today'"
      class="route-btn"
      :disabled="routeLoading"
      @click="onBuildVisitsRoute"
      title="Сформировать маршрут"
    >
      <span class="material-symbols-rounded">directions_car</span>
      {{ routeLoading ? 'Геолокация…' : 'Маршрут визитов' }}
    </button>

    <!-- Points Info Panel (Clusters) -->
    <transition name="slide-up">
      <div v-if="showPointsDialog" class="sheet expanded" style="z-index: 100;" @touchstart="onSheetTouchStart" @touchmove="onSheetTouchMove" @touchend="onSheetTouchEnd">
        <div class="sheet-handle-area" @click="showPointsDialog = false">
          <div class="sheet-handle"></div>
        </div>
        
        <div class="sheet-content" style="padding-top: 0;">
          <template v-for="(group, cid) in pointsDialogGroups" :key="cid">
            <div class="company-block" style="margin-bottom: 24px;">
              <h3 style="font-size: 16px; margin-bottom: 12px; font-weight: 600; line-height:1.3; color:var(--color-text-primary)">
                {{ group[0].company_name || group[0].deal_title || 'Без названия' }}
                <span v-if="group[0].company_address" style="display:block; font-size:12px; color:var(--color-text-secondary); font-weight:400; margin-top:4px;">
                  Сделка ID: {{ group[0].deal_id }} · {{ group[0].company_address }}
                </span>
                <span v-else style="display:block; font-size:12px; color:var(--color-text-secondary); font-weight:400; margin-top:4px;">
                  Сделка ID: {{ group[0].deal_id }}
                </span>
              </h3>
              
              <div v-if="companyVisitsLoading[cid]" class="loading-state" style="padding:20px 0;">
                <span class="material-symbols-rounded spin">progress_activity</span>
              </div>
              
              <template v-else>
                <div class="visits-list" style="display:flex; flex-direction:column; gap:8px;">
                  <div v-for="v in companyVisits[cid] || []" :key="v.id" class="visit-card" style="padding:12px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; background:#f1f5f9; border: 1px solid #e2e8f0;">
                    <div class="visit-info">
                      <span style="display:block; font-size:14px; font-weight:500; color:var(--color-text-primary)">{{ v.title || v.id }}</span>
                      <span style="display:block; font-size:12px; color:var(--color-text-secondary); margin-top:2px;">{{ v.stage_name || '' }}</span>
                      <span v-if="v.visit_date" style="display:inline-block; font-size:11px; font-weight:600; background:var(--color-success); color:#fff; padding:2px 6px; border-radius:4px; margin-top:6px;">План: {{ v.visit_date }}</span>
                    </div>
                    <router-link :to="`/visits/${v.id}`" class="btn-primary" style="padding:6px 12px; border-radius:8px; text-decoration:none; font-size:13px;">
                      Перейти
                    </router-link>
                  </div>
                  <div v-if="!(companyVisits[cid] && companyVisits[cid].length)" style="padding: 8px 12px; font-size:13px; color:var(--color-text-secondary); background:#f8fafc; border-radius:8px; border:1px dashed #cbd5e1;">
                    Нет активных визитов
                  </div>
                </div>

                <button class="btn-primary" style="width:100%; margin-top:12px; justify-content:center; padding:12px 0;" @click.stop="goToPlan(group[0])">
                  <span class="material-symbols-rounded" style="font-size:18px">calendar_add_on</span> Запланировать визит
                </button>
              </template>
            </div>
          </template>
        </div>
      </div>
    </transition>

    <!-- Route Builder Panel -->
    <div v-if="showRoutePanel" class="overlay-panel animate-scale-in route-builder">
      <div class="panel-header">
        <h3>Маршрут визитов</h3>
        <button class="panel-close" @click="showRoutePanel = false"><span class="material-symbols-rounded">close</span></button>
      </div>
      
      <div class="panel-body">
        <div v-if="Object.keys(groupedRouteVisits).length === 0" class="panel-empty">
          Нет визитов с координатами на сегодня
        </div>
        <template v-else>
          <div class="route-controls">
            <p>Выберите точки для объезда:</p>
            <div class="route-group" v-for="(group, cid) in groupedRouteVisits" :key="cid">
               <label class="route-checkbox">
                 <input type="checkbox" :checked="selectedRouteGroups[cid]" @change="toggleRouteGroup(cid)" />
                 <span>{{ group[0].company_name || group[0].title || 'Без названия ' + cid }}</span>
               </label>
            </div>
          </div>
        </template>
      </div>
      
      <div class="panel-footer" v-if="Object.keys(groupedRouteVisits).length > 0">
        <a :href="routeLink" target="_blank" class="btn-primary" style="text-decoration:none" :class="{ disabled: !routeLink }">
          <span class="material-symbols-rounded" style="font-size:18px">near_me</span> Открыть в Я.Картах
        </a>
      </div>
    </div>

    <!-- (Удалена старая панель-список "Все клиенты") -->

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
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useVisits } from '../composables/useVisits'
import { useApi } from '../composables/useApi'

const route = useRoute()
const api = useApi()

const { visitsToday, loadVisits, fetchUnloadPoints, planVisitApi } = useVisits()
import { useRouter } from 'vue-router'

const router = useRouter()
const activeTab = ref('clients')
const mapEl = ref(null)
const todayVisits = visitsToday
const allClients = ref([])  // все клиенты из API /api/clients
const clientsLoading = ref(false)

function stageColor(stageId) {
  if (!stageId) return '#94A3B8'
  const short = stageId.includes(':') ? stageId.split(':')[1] : stageId
  const map = { NEW: '#4DA6FF', PREPARATION: '#FFB020', PREPAYMENT_INVOICE: '#A78BFA', EXECUTING: '#FF6B35', WON: '#00C48C', LOSE: '#FF4D6A' }
  return map[short] || '#4DA6FF'
}
const isLocating = ref(false)

const showPlanModal = ref(false)
const selectedClient = ref(null)
const planDate = ref('')
const planTime = ref('10:00')
const planPointId = ref('')
const unloadPoints = ref([])
const isPlanning = ref(false)
const toastMsg = ref('')

// Cluster and Route states
let mapInstance = null
let clusterer = null
let userPosForRoute = null

const showPointsDialog = ref(false)
const selectedPointsRaw = ref([])
const expandedPointsGroups = ref({})
const companyVisits = ref({})
const companyVisitsLoading = ref({})

const routeLoading = ref(false)
const showRoutePanel = ref(false)
const routeLink = ref('')
const selectedRouteGroups = ref({})

const pointsDialogGroups = computed(() => {
  const groups = {}
  selectedPointsRaw.value.forEach(p => {
    const cid = p.company_id || 'undefined'
    if (!groups[cid]) groups[cid] = []
    groups[cid].push(p)
  })
  return groups
})

const groupedRouteVisits = computed(() => {
  const groups = {}
  visitsToday.value.forEach(v => {
    if (!v.geoLat && !v.lat) return // skip items without coords
    const cid = v.company_id || 'undefined'
    if (!groups[cid]) groups[cid] = []
    groups[cid].push(v)
  })
  return groups
})

// Touch handling for sheet
let touchStartY = 0
function onSheetTouchStart(e) { touchStartY = e.touches[0].clientY }
function onSheetTouchMove(e) { /* handled by CSS scroll */ }
function onSheetTouchEnd(e) {
  const delta = touchStartY - e.changedTouches[0].clientY
  if (Math.abs(delta) > 60) {
    if (delta < 0) {
       showPointsDialog.value = false
    }
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

function goToPlan(client) {
  const name = client.company_name || client.title || client.deal_title || '';
  if (name) {
    showPointsDialog.value = false;
    router.push(`/plan?q=${encodeURIComponent(name)}`);
  } else {
    router.push('/plan');
  }
}

function updateMarkers() {
  if (!mapInstance || !window.ymaps) return
  
  // Инициализация одного кластеризатора
  if (!clusterer) {
    clusterer = new window.ymaps.Clusterer({
      preset: 'islands#invertedBlueClusterIcons',
      groupByCoordinates: false,
      clusterDisableClickZoom: true,
      hasBalloon: false, // Отключаем стандартные баллуны
      hasHint: false
    })

    clusterer.events.add('click', (e) => {
      e.preventDefault();
      const target = e.get('target');
      let geoObjects = [];
      
      if (target.options.getName() === 'cluster') {
        geoObjects = target.getGeoObjects();
      } else {
        geoObjects = [target];
      }
      
      const items = geoObjects.map(obj => obj.properties.get('itemData'));
      openPointsDialog(items);
    });
    
    mapInstance.geoObjects.add(clusterer)
  }

  // Очистка старых данных
  clusterer.removeAll()

  const items = activeTab.value === 'today' ? todayVisits.value : allClients.value
  const placemarks = []

  items.forEach((item) => {
    const lat = item.geoLat || item.lat
    const lng = item.geoLng || item.lng
    if (!lat || !lng) return

    const isVisit = activeTab.value === 'today'
    const color = isVisit
      ? (item.stage_id?.includes('WON') ? '#00C48C' : item.stage_id?.includes('LOSE') ? '#FF4D6A' : '#4DA6FF')
      : '#94A3B8'

    const placemark = new window.ymaps.Placemark(
      [lat, lng],
      {
        itemData: item // Прокидываем сырые данные объекта туда
      },
      {
        preset: activeTab.value === 'today' ? 'islands#redIcon' : 'islands#dotIcon',
        iconColor: color,
        hasBalloon: false, // Обязательно выключить внутри
        hasHint: false
      }
    )

    placemarks.push(placemark)
  })

  clusterer.add(placemarks)
}

async function openPointsDialog(items) {
  selectedPointsRaw.value = items
  expandedPointsGroups.value = {}
  showPointsDialog.value = true
  showRoutePanel.value = false

  Object.keys(pointsDialogGroups.value).forEach(async cid => {
    expandedPointsGroups.value[cid] = true
    if (cid === 'undefined') {
      companyVisits.value[cid] = pointsDialogGroups.value[cid].map(p => ({
        id: p.deal_id || p.id,
        title: p.deal_title || p.title,
        stage_id: p.stage_id,
        stage_name: p.stage_name,
        visit_date: p.visit_date
      }))
      return
    }
    
    if (!companyVisits.value[cid]) {
      companyVisitsLoading.value[cid] = true
      try {
        const data = await api.apiGet('api/visits', { period: 'all', company_id: cid, per_page: 50 })
        companyVisits.value[cid] = data.items || []
      } catch (e) {
         companyVisits.value[cid] = []
      } finally {
         companyVisitsLoading.value[cid] = false
      }
    }
  })
}

async function onBuildVisitsRoute() {
  if (routeLoading.value) return
  routeLoading.value = true
  
  try {
    const start = await getUserLocationAsync()
    userPosForRoute = start || [55.7950, 49.1221] // fallback to Kazan
    
    // Select all by default
    const sel = {}
    Object.keys(groupedRouteVisits.value).forEach(cid => {
      sel[cid] = true
    })
    selectedRouteGroups.value = sel

    recalcRouteLink()
    showRoutePanel.value = true
    showPointsDialog.value = false
  } finally {
    routeLoading.value = false
  }
}

function getUserLocationAsync() {
  return new Promise(resolve => {
    if (!navigator.geolocation) return resolve(null)
    navigator.geolocation.getCurrentPosition(
      pos => resolve([pos.coords.latitude, pos.coords.longitude]),
      err => resolve(null),
      { enableHighAccuracy: true, timeout: 5000, maximumAge: 60000 }
    )
  })
}

function toggleRouteGroup(cid) {
  selectedRouteGroups.value[cid] = !selectedRouteGroups.value[cid]
  recalcRouteLink()
}

function recalcRouteLink() {
  let activeVisits = []
  
  // Extract ONE unique visit per companyId
  Object.keys(groupedRouteVisits.value).forEach(cid => {
    if (selectedRouteGroups.value[cid] && groupedRouteVisits.value[cid].length) {
      activeVisits.push(groupedRouteVisits.value[cid][0])
    }
  })

  if (activeVisits.length === 0) {
    routeLink.value = ''
    return
  }

  const order = optimizeOrder(userPosForRoute, activeVisits)
  const orderedVisits = order.map(i => activeVisits[i])
  
  const toLatLon = (lat, lng) => `${lat},${lng}`
  const chain = [toLatLon(userPosForRoute[0], userPosForRoute[1])]
  orderedVisits.forEach(v => {
    const lat = v.geoLat || v.lat
    const lng = v.geoLng || v.lng
    chain.push(toLatLon(lat, lng))
  })
  
  const rtext = encodeURIComponent(chain.join('~'))
  routeLink.value = `https://yandex.ru/maps/?mode=routes&rtt=auto&rtext=${rtext}`
}

function optimizeOrder(start, pts) {
  const remaining = pts.map((_, i) => i)
  let current = start
  const order = []

  while (remaining.length) {
    let bestIdx = -1
    let bestDist = Infinity
    for (const i of remaining) {
      const v = pts[i]
      const lat = parseFloat(v.geoLat || v.lat)
      const lng = parseFloat(v.geoLng || v.lng)
      
      const d = (lat - current[0]) ** 2 + (lng - current[1]) ** 2
      if (d < bestDist) {
        bestDist = d
        bestIdx = i
      }
    }
    order.push(bestIdx)
    current = [ parseFloat(pts[bestIdx].geoLat || pts[bestIdx].lat), parseFloat(pts[bestIdx].geoLng || pts[bestIdx].lng) ]
    remaining.splice(remaining.indexOf(bestIdx), 1)
  }
  return order
}

async function loadAllClients() {
  clientsLoading.value = true
  
  // 1. Попытка загрузить из кэша для быстрого старта
  try {
    const cached = localStorage.getItem('rocada_map_clients')
    if (cached) {
      allClients.value = JSON.parse(cached)
      if (activeTab.value === 'clients') {
        updateMarkers()
      }
    }
  } catch(e) {
    console.warn('[MapView] Cache read error:', e)
  }

  // 2. Фоновая загрузка актуальных данных
  try {
    let currentPage = 1
    let loadedItems = []
    
    while (true) {
      const data = await api.apiGet('api/clients', { per_page: 100, page: currentPage })
      if (!data.items || data.items.length === 0) break
      loadedItems = [...loadedItems, ...data.items]
      
      // Обновляем реактивно только если кэша нет, либо обновляем в конце
      if (!allClients.value.length) {
          allClients.value = loadedItems
          if (activeTab.value === 'clients') updateMarkers()
      }
      
      if (data.total < 100) break
      currentPage++
    }

    // Итоговое обновление
    allClients.value = loadedItems
    if (activeTab.value === 'clients') updateMarkers()

    // Сохраняем в кэш
    try {
      localStorage.setItem('rocada_map_clients', JSON.stringify(loadedItems))
    } catch(e) {
      console.warn('[MapView] Cache write error:', e)
    }

  } catch (e) {
    console.error('[MapView] loadAllClients error:', e)
  } finally {
    clientsLoading.value = false
  }
}

onMounted(async () => {
  window.addEventListener('message', handleMessage)
  await loadVisits()
  
  // Загружаем клиентов для вкладки «Клиенты»
  loadAllClients()

  if (route.query.planDealId) {
    planVisit({ 
      id: route.query.planDealId, 
      deal_id: route.query.planDealId,
      title: route.query.title || `Сделка #${route.query.planDealId}`,
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

    ymaps.geolocation.get({ provider: 'yandex', autoReverseGeocode: false })
      .then((result) => {
        const coords = result.geoObjects.get(0).geometry.getCoordinates()
        mapInstance.setCenter(coords, 12)
      }).catch(() => {})

    updateMarkers()
  })
})

onUnmounted(() => {
  window.removeEventListener('message', handleMessage)
})

watch(activeTab, () => {
  showPointsDialog.value = false
  showRoutePanel.value = false
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

.sheet-planned-badge {
  width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

@keyframes spin { to { transform: rotate(360deg); } }
.spin { animation: spin 1s linear infinite; }

/* ─── Route Marker Overlays ─── */
.route-btn {
  position: absolute;
  top: calc(var(--space-md) + 60px + env(safe-area-inset-top, 0px));
  left: 50%;
  transform: translateX(-50%);
  z-index: 20;
  background: var(--color-bg-card);
  color: var(--color-primary);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  padding: 10px 16px;
  font-weight: var(--font-weight-semibold);
  font-size: var(--font-size-sm);
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  display: flex; align-items: center; gap: 8px;
  transition: all var(--transition-fast);
}
.route-btn:active { transform: translateX(-50%) scale(0.96); }
.route-btn:disabled { opacity: 0.7; pointer-events: none; }

/* ─── Panels (Dialog/Route) ─── */
.overlay-panel {
  position: absolute;
  top: calc(var(--space-md) + env(safe-area-inset-top, 0px));
  right: var(--space-md);
  z-index: 100;
  width: 320px;
  max-height: calc(100dvh - 200px);
  background: var(--color-bg-base);
  border-radius: var(--radius-xl);
  box-shadow: 0 12px 32px rgba(0,0,0,0.25);
  display: flex; flex-direction: column;
}

@media (max-width: 480px) {
  .overlay-panel {
    top: auto;
    bottom: calc(var(--bottom-nav-height) + var(--space-md) + env(safe-area-bottom));
    right: var(--space-md);
    left: var(--space-md);
    width: auto;
    max-height: 55dvh;
  }
}

.panel-header {
  padding: 14px 16px;
  border-bottom: 1px solid var(--color-border);
  display: flex; justify-content: space-between; align-items: center;
  flex-shrink: 0;
}
.panel-header h3 { font-size: var(--font-size-md); font-weight: var(--font-weight-bold); }
.panel-close { background: none; border: none; cursor: pointer; color: var(--color-text-secondary); display:flex; align-items:center; }

.panel-body {
  padding: 12px;
  overflow-y: auto;
  display: flex; flex-direction: column; gap: 8px;
}

.info-group {
  background: var(--color-bg-card);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  overflow: hidden;
}
.info-group-header {
  padding: 10px 12px;
  background: var(--color-bg-elevated);
  display: flex; justify-content: space-between; align-items: center;
  cursor: pointer;
  user-select: none;
}
.info-group-title { font-weight: var(--font-weight-semibold); font-size: var(--font-size-sm); }
.info-group-list { display: flex; flex-direction: column; padding: 4px; gap: 4px;}
.info-item {
  padding: 8px; border-bottom: 1px solid var(--color-border);
  display: flex; justify-content: space-between; align-items: center; gap:8px;
}
.info-item:last-child { border-bottom: none; }
.info-item-text { display: flex; flex-direction: column; gap: 2px; flex:1; min-width: 0; }
.info-title { font-size: var(--font-size-sm); font-weight: var(--font-weight-medium); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.info-subtitle { font-size: 11px; color: var(--color-text-tertiary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.info-planned { font-size: 10px; font-weight: var(--font-weight-bold); }

.btn-plan-sm {
  background: var(--color-primary); color: white;
  border: none; border-radius: 4px;
  padding: 6px 10px; font-size: 11px; font-weight: var(--font-weight-semibold);
  cursor: pointer; flex-shrink: 0; transition: opacity 0.2s;
}
.btn-plan-sm:active { opacity: 0.8; }
.planned-icon { flex-shrink:0; display:flex; padding:4px;}

.panel-footer { padding: 12px; border-top: 1px solid var(--color-border); flex-shrink: 0; }
.btn-primary.disabled { pointer-events: none; opacity: 0.5; }
.route-controls p { font-size: var(--font-size-sm); margin-bottom: 8px; font-weight: var(--font-weight-medium); color: var(--color-text-secondary); }
.route-group { padding: 6px 0; }
.route-checkbox { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: var(--font-size-sm); }
.panel-empty { text-align:center; padding: 20px; color: var(--color-text-tertiary); font-size: var(--font-size-sm); }

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
