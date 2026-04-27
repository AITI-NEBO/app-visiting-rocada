<template>
  <div class="dashboard-page">
    <AppHeader title="Главная" />

    <div class="page-content">
      <!-- Greeting -->
      <section class="greeting-section animate-fade-in-up">
        <div class="greeting-text">
          <p class="greeting-label">Добрый день 👋</p>
          <h2 class="greeting-name">{{ user.name }}</h2>
          <p class="greeting-role">{{ user.role }} • {{ user.region }}</p>
        </div>
      </section>

      <!-- Today stats -->
      <section class="stats-section animate-fade-in-up" style="animation-delay: 100ms">
        <div class="stats-card">
          <div class="stats-header">
            <h3 class="stats-title">Сегодня</h3>
            <span class="stats-date">{{ formattedDate }}</span>
          </div>
          <div class="stats-progress">
            <div class="progress-bar">
              <div class="progress-fill" :style="{ width: progressPercent + '%' }"></div>
            </div>
            <div class="progress-labels">
              <span class="progress-done">{{ completedCount }} выполнено</span>
              <span class="progress-total">из {{ todayTotal }}</span>
            </div>
          </div>
          <div class="stats-grid">
            <div class="stat-item">
              <span class="stat-value text-gradient">{{ successCount }}</span>
              <span class="stat-label">Завершено</span>
            </div>
            <div class="stat-item">
              <span class="stat-value" style="color: var(--color-danger)">{{ failCount }}</span>
              <span class="stat-label">Провалено</span>
            </div>
            <!-- TODO: продажи — временно скрыто
            <div class="stat-item">
              <span class="stat-value" style="color: var(--color-accent)">{{ formatRevenue }}</span>
              <span class="stat-label">Выручка</span>
            </div>
            -->
          </div>
        </div>
      </section>

      <!-- Quick actions -->
      <section class="actions-section animate-fade-in-up" style="animation-delay: 200ms">
        <h3 class="section-title">Быстрые действия</h3>
        <div class="actions-grid">
          <router-link to="/visits" class="action-card">
            <div class="action-icon-wrap" style="background: rgba(0, 212, 170, 0.12)">
              <span class="material-symbols-rounded action-icon" style="color: var(--color-accent)">today</span>
            </div>
            <span class="action-label">Визиты<br/>сегодня</span>
            <span class="action-count">{{ todayTotal }}</span>
          </router-link>
          <router-link to="/visits?tab=tomorrow" class="action-card">
            <div class="action-icon-wrap" style="background: rgba(0, 102, 255, 0.12)">
              <span class="material-symbols-rounded action-icon" style="color: var(--color-primary)">event</span>
            </div>
            <span class="action-label">Визиты<br/>завтра</span>
            <span class="action-count">{{ tomorrowTotal }}</span>
          </router-link>
          <router-link to="/map" class="action-card">
            <div class="action-icon-wrap" style="background: rgba(124, 58, 237, 0.12)">
              <span class="material-symbols-rounded action-icon" style="color: #A78BFA">map</span>
            </div>
            <span class="action-label">Карта<br/>клиентов</span>
            <span class="action-count">→</span>
          </router-link>
        </div>
      </section>

      <!-- Next visit -->
      <section v-if="nextVisit" class="next-visit-section animate-fade-in-up" style="animation-delay: 300ms">
        <h3 class="section-title">Следующий визит</h3>
        <VisitCard :visit="nextVisit" />
      </section>

      <!-- Recent completed -->
      <section v-if="recentCompleted.length" class="recent-section animate-fade-in-up" style="animation-delay: 400ms">
        <h3 class="section-title">Последние завершённые</h3>
        <div class="recent-list stagger-children">
          <VisitCard
            v-for="visit in recentCompleted"
            :key="visit.id"
            :visit="visit"
          />
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import AppHeader from '../components/AppHeader.vue'
import VisitCard from '../components/VisitCard.vue'
import { useAuth } from '../composables/useAuth'
import { useVisits } from '../composables/useVisits'

const auth = useAuth()
const { visitsToday, visitsTomorrow, todayCount, tomorrowCount, todayTotal, tomorrowTotal, successCount, failCount, loadVisits } = useVisits()

onMounted(() => loadVisits())

const user = computed(() => {
  const u = auth.user.value || {}
  return {
    name:   u.fullName || 'Пользователь',
    role:   u.position || '',
    region: '',
  }
})

const completedCount = computed(() => successCount.value)
const progressPercent = computed(() => todayTotal.value ? Math.round((successCount.value / todayTotal.value) * 100) : 0)
const formatRevenue = computed(() => '—')

const nextVisit = computed(() => visitsToday.value[0] || null)
const recentCompleted = computed(() => [])

const formattedDate = new Date().toLocaleDateString('ru-RU', {
  day: 'numeric',
  month: 'long'
})
</script>

<style scoped>
.dashboard-page {
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
  gap: var(--space-lg);
}

@media (min-width: 768px) {
  .page-content {
    padding: var(--space-xl);
    padding-bottom: var(--space-xl);
  }
}

/* Greeting */
.greeting-section {
  padding: var(--space-sm) 0;
}

.greeting-label {
  font-size: var(--font-size-md);
  color: var(--color-text-secondary);
  margin-bottom: 4px;
}

.greeting-name {
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text-primary);
  margin-bottom: 2px;
}

.greeting-role {
  font-size: var(--font-size-sm);
  color: var(--color-text-tertiary);
}

/* Stats card */
.stats-card {
  background: var(--color-bg-card);
  border-radius: var(--radius-xl);
  padding: var(--space-lg);
  border: 1px solid var(--color-border);
}

.stats-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--space-base);
}

.stats-title {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-semibold);
}

.stats-date {
  font-size: var(--font-size-sm);
  color: var(--color-text-tertiary);
  text-transform: capitalize;
}

.stats-progress {
  margin-bottom: var(--space-lg);
}

.progress-bar {
  width: 100%;
  height: 8px;
  background: var(--color-bg-elevated);
  border-radius: var(--radius-full);
  overflow: hidden;
  margin-bottom: var(--space-sm);
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--color-accent), var(--color-primary));
  border-radius: var(--radius-full);
  transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  font-size: var(--font-size-sm);
}

.progress-done {
  color: var(--color-accent);
  font-weight: var(--font-weight-medium);
}

.progress-total {
  color: var(--color-text-tertiary);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-base);
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.stat-value {
  font-size: var(--font-size-2xl);
  font-weight: var(--font-weight-extrabold);
  line-height: 1;
}

.stat-label {
  font-size: var(--font-size-xs);
  color: var(--color-text-tertiary);
  font-weight: var(--font-weight-medium);
}

/* Quick actions */
.section-title {
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-semibold);
  margin-bottom: var(--space-md);
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-md);
}

.action-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-sm);
  padding: var(--space-base);
  background: var(--color-bg-card);
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
  text-decoration: none;
  color: var(--color-text-primary);
  transition: transform var(--transition-fast), background var(--transition-fast);
}

.action-card:active {
  transform: scale(0.95);
  background: var(--color-bg-card-hover);
}

.action-icon-wrap {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
}

.action-icon {
  font-size: 26px;
}

.action-label {
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-medium);
  text-align: center;
  line-height: var(--line-height-tight);
  color: var(--color-text-secondary);
}

.action-count {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-bold);
  color: var(--color-text-primary);
}

/* Sections */
.recent-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}
</style>
