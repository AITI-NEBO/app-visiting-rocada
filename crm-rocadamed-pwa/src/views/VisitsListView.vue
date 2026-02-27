<template>
  <div class="visits-page">
    <AppHeader title="Визиты" />

    <div class="page-content">
      <!-- Tab switcher -->
      <div class="tab-bar animate-fade-in-down">
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'today' }"
          @click="activeTab = 'today'"
        >
          <span class="material-symbols-rounded tab-icon">today</span>
          Сегодня
          <span class="tab-count">{{ todayVisits.length }}</span>
        </button>
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'tomorrow' }"
          @click="activeTab = 'tomorrow'"
        >
          <span class="material-symbols-rounded tab-icon">event</span>
          Завтра
          <span class="tab-count">{{ tomorrowVisits.length }}</span>
        </button>
      </div>

      <!-- Stats mini bar -->
      <div class="mini-stats animate-fade-in-up" style="animation-delay: 80ms">
        <div class="mini-stat">
          <span class="mini-dot" style="background: var(--color-success)"></span>
          <span>{{ completedCount }} выполнено</span>
        </div>
        <div class="mini-stat">
          <span class="mini-dot" style="background: var(--color-warning)"></span>
          <span>{{ inProgressCount }} в работе</span>
        </div>
        <div class="mini-stat">
          <span class="mini-dot" style="background: var(--color-text-tertiary)"></span>
          <span>{{ pendingCount }} ожидает</span>
        </div>
      </div>

      <!-- Visit list -->
      <div class="visits-list stagger-children">
        <VisitCard
          v-for="visit in currentVisits"
          :key="visit.id"
          :visit="visit"
        />
      </div>

      <div v-if="!currentVisits.length" class="empty-state animate-fade-in-up">
        <span class="material-symbols-rounded empty-icon">event_busy</span>
        <p class="empty-text">Визитов нет</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import VisitCard from '../components/VisitCard.vue'
import { visits, visitsTomorrow } from '../data/mock'

const route = useRoute()
const activeTab = ref(route.query.tab === 'tomorrow' ? 'tomorrow' : 'today')

const todayVisits = visits
const tomorrowVisits = visitsTomorrow

const currentVisits = computed(() =>
  activeTab.value === 'today' ? todayVisits : tomorrowVisits
)

const completedCount = computed(() => currentVisits.value.filter(v => v.status === 'completed').length)
const inProgressCount = computed(() => currentVisits.value.filter(v => v.status === 'in_progress').length)
const pendingCount = computed(() => currentVisits.value.filter(v => v.status === 'pending').length)
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
