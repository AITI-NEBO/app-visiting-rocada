<template>
  <div class="visits-page">
    <AppHeader title="Визиты" />

    <div class="page-content">
      <!-- Переключатель направлений -->
      <div v-if="hasMultiple" class="direction-bar animate-fade-in-down">
        <button
          v-for="dir in directions"
          :key="dir.id"
          class="dir-btn"
          :class="{ active: currentDirection?.id === dir.id }"
          @click="switchDirection(dir.id)"
        >
          {{ dir.name }}
        </button>
      </div>

      <!-- Tab switcher -->
      <div class="tab-bar animate-fade-in-down">
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'today' }"
          @click="activeTab = 'today'"
        >
          <span class="material-symbols-rounded tab-icon">today</span>
          Сегодня
          <span class="tab-count">{{ todayTotal }}</span>
        </button>
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'tomorrow' }"
          @click="activeTab = 'tomorrow'"
        >
          <span class="material-symbols-rounded tab-icon">event</span>
          Завтра
          <span class="tab-count">{{ tomorrowTotal }}</span>
        </button>
      </div>

      <!-- Stats mini bar -->
      <div class="mini-stats animate-fade-in-up" style="animation-delay: 80ms">
        <div class="mini-stat">
          <span class="mini-dot" style="background: var(--color-primary)"></span>
          <span>{{ currentVisits.length }} загружено</span>
        </div>
        <div class="mini-stat">
          <span class="mini-dot" style="background: var(--color-text-tertiary)"></span>
          <span>{{ currentTotal }} всего</span>
        </div>
      </div>

      <!-- Visit list -->
      <div class="visits-list stagger-children" ref="listRef">
        <VisitCard
          v-for="visit in currentVisits"
          :key="visit.id"
          :visit="visit"
        />

        <!-- Кнопка "Загрузить ещё" -->
        <button
          v-if="currentHasMore"
          class="load-more-btn"
          :disabled="loadingMore"
          @click="loadMore"
        >
          <span v-if="loadingMore" class="material-symbols-rounded spin">progress_activity</span>
          <span v-else>Загрузить ещё</span>
        </button>
      </div>

      <div v-if="loading && !currentVisits.length" class="empty-state animate-fade-in-up">
        <span class="material-symbols-rounded spin" style="font-size:32px; color: var(--color-primary)">progress_activity</span>
        <p class="empty-text">Загрузка…</p>
      </div>

      <div v-if="!loading && !currentVisits.length" class="empty-state animate-fade-in-up">
        <span class="material-symbols-rounded empty-icon">event_busy</span>
        <p class="empty-text">Визитов нет</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import VisitCard from '../components/VisitCard.vue'
import { useVisits } from '../composables/useVisits'
import { useDirections } from '../composables/useDirections'

const route = useRoute()
const {
  visitsToday, visitsTomorrow,
  todayTotal, tomorrowTotal,
  loadVisits, loadNextPage, hasMore,
  loading,
} = useVisits()

const {
  directions, currentDirection, hasMultiple,
  loadDirections, setDirection,
} = useDirections()

onMounted(async () => {
  await loadDirections()
  loadVisits()
})

async function switchDirection(id) {
  setDirection(id)
  await loadVisits(true)
}

const activeTab = ref(route.query.tab === 'tomorrow' ? 'tomorrow' : 'today')
const loadingMore = ref(false)

const currentVisits = computed(() =>
  activeTab.value === 'today' ? visitsToday.value : visitsTomorrow.value
)

const currentTotal = computed(() =>
  activeTab.value === 'today' ? todayTotal.value : tomorrowTotal.value
)

const currentHasMore = computed(() => hasMore(activeTab.value))

async function loadMore() {
  loadingMore.value = true
  try {
    await loadNextPage(activeTab.value)
  } finally {
    loadingMore.value = false
  }
}

// При переключении таба — если ещё не загружено, подгрузить первую страницу
watch(activeTab, async (tab) => {
  if (!currentVisits.value.length && hasMore(tab)) {
    loadingMore.value = true
    try { await loadNextPage(tab) }
    finally { loadingMore.value = false }
  }
})
</script>

<style scoped>
.visits-page {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
}

.page-content {
  flex: 1;
  padding: var(--space-base);
  padding-bottom: calc(var(--bottom-nav-height) + var(--safe-area-bottom) + var(--space-xl));
  display: flex;
  flex-direction: column;
  gap: var(--space-base);
}

@media (min-width: 768px) {
  .page-content {
    padding: var(--space-xl);
    padding-bottom: var(--space-xl);
  }
}

/* Direction switcher */
.direction-bar {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
  padding-bottom: 2px;
}

.direction-bar::-webkit-scrollbar { display: none; }

.dir-btn {
  flex-shrink: 0;
  padding: 7px 18px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  color: #555;
  background: #f0f0f0;
  border: none;
  transition: background 0.2s, color 0.2s, box-shadow 0.2s;
  cursor: pointer;
  white-space: nowrap;
}

.dir-btn:hover:not(.active) {
  background: #e0e0e0;
  color: #222;
}

.dir-btn.active {
  background: #0066ff;
  color: #fff;
  box-shadow: 0 2px 10px rgba(0, 102, 255, 0.35);
  font-weight: 700;
}

/* Tabs */
.tab-bar {
  display: flex;
  gap: var(--space-sm);
  background: var(--color-bg-card);
  padding: 4px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
}

.tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: var(--space-md) var(--space-base);
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-medium);
  color: var(--color-text-secondary);
  transition: all var(--transition-base);
}

.tab-btn.active {
  background: linear-gradient(135deg, rgba(0, 212, 170, 0.15), rgba(0, 102, 255, 0.15));
  color: var(--color-text-primary);
  box-shadow: var(--shadow-sm);
}

.tab-icon {
  font-size: 20px;
}

.tab-count {
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  border-radius: var(--radius-full);
  background: var(--color-bg-elevated);
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-bold);
  display: flex;
  align-items: center;
  justify-content: center;
}

.tab-btn.active .tab-count {
  background: var(--color-primary);
  color: white;
}

/* Mini stats */
.mini-stats {
  display: flex;
  justify-content: center;
  gap: var(--space-base);
}

.mini-stat {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: var(--font-size-xs);
  color: var(--color-text-tertiary);
}

.mini-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

/* List */
.visits-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

/* Load more button */
.load-more-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  padding: var(--space-base);
  border-radius: var(--radius-lg);
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  color: var(--color-primary);
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-semibold);
  cursor: pointer;
  transition: all var(--transition-base);
}

.load-more-btn:hover:not(:disabled) {
  background: linear-gradient(135deg, rgba(0, 212, 170, 0.1), rgba(0, 102, 255, 0.1));
}

.load-more-btn:disabled {
  opacity: 0.5;
  cursor: wait;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
.spin {
  animation: spin 1s linear infinite;
}

/* Empty state */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-base);
  padding: var(--space-2xl);
}

.empty-icon {
  font-size: 56px;
  color: var(--color-text-tertiary);
}

.empty-text {
  font-size: var(--font-size-md);
  color: var(--color-text-secondary);
}
</style>
