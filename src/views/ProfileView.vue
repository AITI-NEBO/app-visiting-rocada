<template>
  <div class="profile-page">
    <AppHeader title="Профиль" />

    <div class="page-content">
      <!-- Profile card -->
      <div class="profile-card animate-fade-in-up">
        <div class="profile-avatar-wrap">
          <div class="profile-avatar">
            <span class="avatar-text">{{ user.initials }}</span>
          </div>
          <span class="profile-status-dot"></span>
        </div>
        <h2 class="profile-name">{{ user.name }}</h2>
        <p class="profile-role">{{ user.role }}</p>
        <p class="profile-region">{{ user.region }}</p>
        <div class="profile-contacts">
          <div class="contact-item">
            <span class="material-symbols-rounded contact-icon">phone</span>
            <span>{{ user.phone }}</span>
          </div>
          <div class="contact-item">
            <span class="material-symbols-rounded contact-icon">email</span>
            <span>{{ user.email }}</span>
          </div>
          <div class="contact-item">
            <span class="material-symbols-rounded contact-icon">domain</span>
            <span>{{ user.department }}</span>
          </div>
        </div>
      </div>

      <!-- Statistics -->
      <div class="stats-section animate-fade-in-up" style="animation-delay: 100ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">bar_chart</span>
          Статистика продаж
        </h3>
        <div class="stats-cards">
          <div class="stat-card">
            <div class="stat-card-header">
              <span class="stat-card-title">Сегодня</span>
              <span class="material-symbols-rounded stat-card-icon" style="color: var(--color-accent)">today</span>
            </div>
            <div class="stat-card-value">
              <span class="stat-big text-gradient">{{ user.stats.today.completed }}</span>
              <span class="stat-total">/ {{ user.stats.today.visits }}</span>
            </div>
            <div class="stat-mini-info">
              <span>{{ user.stats.today.orders }} заказов</span>
              <span class="mini-revenue">{{ formatCurrency(user.stats.today.revenue) }}</span>
            </div>
            <div class="stat-bar">
              <div class="stat-bar-fill" :style="{ width: todayPercent + '%' }"></div>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-card-header">
              <span class="stat-card-title">Неделя</span>
              <span class="material-symbols-rounded stat-card-icon" style="color: var(--color-primary)">date_range</span>
            </div>
            <div class="stat-card-value">
              <span class="stat-big" style="color: var(--color-primary)">{{ user.stats.week.completed }}</span>
              <span class="stat-total">/ {{ user.stats.week.visits }}</span>
            </div>
            <div class="stat-mini-info">
              <span>{{ user.stats.week.orders }} заказов</span>
              <span class="mini-revenue">{{ formatCurrency(user.stats.week.revenue) }}</span>
            </div>
            <div class="stat-bar">
              <div class="stat-bar-fill" :style="{ width: weekPercent + '%', background: 'var(--color-primary)' }"></div>
            </div>
          </div>

          <div class="stat-card stat-card-wide">
            <div class="stat-card-header">
              <span class="stat-card-title">Месяц</span>
              <span class="material-symbols-rounded stat-card-icon" style="color: #A78BFA">calendar_month</span>
            </div>
            <div class="stat-card-value">
              <span class="stat-big" style="color: #A78BFA">{{ user.stats.month.completed }}</span>
              <span class="stat-total">/ {{ user.stats.month.visits }}</span>
            </div>
            <div class="stat-mini-info">
              <span>{{ user.stats.month.orders }} заказов</span>
              <span class="mini-revenue">{{ formatCurrency(user.stats.month.revenue) }}</span>
            </div>
            <div class="stat-bar">
              <div class="stat-bar-fill" :style="{ width: monthPercent + '%', background: '#A78BFA' }"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Settings -->
      <div class="settings-section animate-fade-in-up" style="animation-delay: 200ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">settings</span>
          Настройки
        </h3>
        <div class="settings-list">
          <!-- <div class="setting-item">
            <div class="setting-left">
              <span class="material-symbols-rounded setting-icon">notifications</span>
              <span class="setting-label">Уведомления</span>
            </div>
            <div class="toggle" :class="{ on: notifyOn }" @click="notifyOn = !notifyOn">
              <div class="toggle-thumb"></div>
            </div>
          </div> -->
          <div class="setting-item">
            <div class="setting-left">
              <span class="material-symbols-rounded setting-icon">{{ isDarkTheme ? 'dark_mode' : 'light_mode' }}</span>
              <span class="setting-label">Тёмная тема</span>
            </div>
            <div class="toggle" :class="{ on: isDarkTheme }" @click="toggleTheme()">
              <div class="toggle-thumb"></div>
            </div>
          </div>
          <div class="setting-item">
            <div class="setting-left">
              <span class="material-symbols-rounded setting-icon">language</span>
              <span class="setting-label">Язык</span>
            </div>
            <span class="setting-value">Русский</span>
          </div>
          <div class="setting-item">
            <div class="setting-left">
              <span class="material-symbols-rounded setting-icon">info</span>
              <span class="setting-label">Версия</span>
            </div>
            <span class="setting-value">beta 1.0.0</span>
          </div>
        </div>
      </div>

      <!-- Logout -->
      <div class="logout-section animate-fade-in-up" style="animation-delay: 300ms">
        <button class="logout-btn" @click="handleLogout">
          <span class="material-symbols-rounded logout-icon">logout</span>
          Выйти из аккаунта
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, inject } from 'vue'
import { useRouter } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import { useAuth } from '../composables/useAuth'

const router = useRouter()
const auth   = useAuth()
const notifyOn = ref(true)

const { isDarkTheme, toggleTheme } = inject('theme')

// Реальные данные пользователя из OAuth
const user = computed(() => {
  const u = auth.user.value || {}
  const firstName = u.firstName || ''
  const lastName  = u.lastName  || ''
  const initials  = (firstName[0] || '') + (lastName[0] || '')
  return {
    name:       u.fullName || 'Пользователь',
    initials:   initials.toUpperCase() || 'U',
    role:       u.position || 'Сотрудник',
    region:     '',
    phone:      u.phone    || '—',
    email:      u.email    || '—',
    department: u.position || '—',
    stats: {
      today: { completed: 0, visits: 0, orders: 0, revenue: 0 },
      week:  { completed: 0, visits: 0, orders: 0, revenue: 0 },
      month: { completed: 0, visits: 0, orders: 0, revenue: 0 },
    }
  }
})

const todayPercent = computed(() => {
  const s = user.value.stats.today
  return s.visits ? Math.round((s.completed / s.visits) * 100) : 0
})
const weekPercent = computed(() => {
  const s = user.value.stats.week
  return s.visits ? Math.round((s.completed / s.visits) * 100) : 0
})
const monthPercent = computed(() => {
  const s = user.value.stats.month
  return s.visits ? Math.round((s.completed / s.visits) * 100) : 0
})

function formatCurrency(val) {
  return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(val || 0)
}

function handleLogout() {
  auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.profile-page {
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

.profile-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: var(--space-xl);
  background: var(--color-bg-card);
  border-radius: var(--radius-xl);
  border: 1px solid var(--color-border);
  box-shadow: var(--shadow-sm);
}

.profile-avatar-wrap { position: relative; margin-bottom: var(--space-base); }

.profile-avatar {
  width: 80px; height: 80px; border-radius: 50%;
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  display: flex; align-items: center; justify-content: center;
}

.avatar-text {
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-extrabold);
  color: white; letter-spacing: 1px;
}

.profile-status-dot {
  position: absolute; bottom: 2px; right: 2px;
  width: 16px; height: 16px; border-radius: 50%;
  background: var(--color-success);
  border: 3px solid var(--color-bg-card);
}

.profile-name { font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); margin-bottom: 4px; }
.profile-role { font-size: var(--font-size-sm); color: var(--color-text-secondary); margin-bottom: 2px; }
.profile-region { font-size: var(--font-size-xs); color: var(--color-text-tertiary); margin-bottom: var(--space-base); }

.profile-contacts { display: flex; flex-direction: column; gap: var(--space-sm); }

.contact-item {
  display: flex; align-items: center; gap: var(--space-sm);
  font-size: var(--font-size-sm); color: var(--color-text-secondary);
}

.contact-icon { font-size: 18px; color: var(--color-text-tertiary); }

.section-title {
  display: flex; align-items: center; gap: var(--space-sm);
  font-size: var(--font-size-md); font-weight: var(--font-weight-semibold);
  margin-bottom: var(--space-md);
}

.section-icon { font-size: 20px; color: var(--color-text-tertiary); }

.stats-cards { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md); }

.stat-card {
  padding: var(--space-base);
  background: var(--color-bg-card); border-radius: var(--radius-lg);
  border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);
}

.stat-card-wide { grid-column: span 2; }

.stat-card-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: var(--space-sm);
}

.stat-card-title { font-size: var(--font-size-sm); color: var(--color-text-secondary); font-weight: var(--font-weight-medium); }
.stat-card-icon { font-size: 22px; }

.stat-card-value { display: flex; align-items: baseline; gap: 4px; margin-bottom: var(--space-sm); }
.stat-big { font-size: var(--font-size-2xl); font-weight: var(--font-weight-extrabold); line-height: 1; }
.stat-total { font-size: var(--font-size-base); color: var(--color-text-tertiary); }

.stat-mini-info {
  display: flex; justify-content: space-between;
  font-size: var(--font-size-xs); color: var(--color-text-tertiary);
  margin-bottom: var(--space-sm);
}

.mini-revenue { color: var(--color-accent); font-weight: var(--font-weight-semibold); }

.stat-bar {
  width: 100%; height: 6px;
  background: var(--color-bg-elevated); border-radius: var(--radius-full);
  overflow: hidden;
}

.stat-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--color-accent), var(--color-primary));
  border-radius: var(--radius-full);
  transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
}

.settings-list {
  display: flex; flex-direction: column;
  background: var(--color-bg-card); border-radius: var(--radius-lg);
  border: 1px solid var(--color-border); overflow: hidden;
  box-shadow: var(--shadow-sm);
}

.setting-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: var(--space-base); border-bottom: 1px solid var(--color-border);
}

.setting-item:last-child { border-bottom: none; }

.setting-left { display: flex; align-items: center; gap: var(--space-md); }
.setting-icon { font-size: 22px; color: var(--color-text-secondary); }
.setting-label { font-size: var(--font-size-base); font-weight: var(--font-weight-medium); }
.setting-value { font-size: var(--font-size-sm); color: var(--color-text-tertiary); }

.toggle {
  width: 44px; height: 26px; border-radius: var(--radius-full);
  background: var(--color-bg-elevated); padding: 3px;
  cursor: pointer; transition: background var(--transition-fast);
}

.toggle.on { background: var(--color-accent); }

.toggle-thumb {
  width: 20px; height: 20px; border-radius: 50%;
  background: white; transition: transform var(--transition-fast);
  box-shadow: var(--shadow-sm);
}

.toggle.on .toggle-thumb { transform: translateX(18px); }

.logout-btn {
  width: 100%; padding: var(--space-base); border-radius: var(--radius-lg);
  display: flex; align-items: center; justify-content: center; gap: var(--space-sm);
  color: var(--color-danger); font-size: var(--font-size-base); font-weight: var(--font-weight-semibold);
  background: rgba(255, 71, 87, 0.08); border: 1px solid rgba(255, 71, 87, 0.2);
  transition: background var(--transition-fast), transform var(--transition-fast);
}

.logout-btn:active { transform: scale(0.97); background: rgba(255, 71, 87, 0.15); }
.logout-icon { font-size: 22px; }
</style>
