<template>
  <aside class="sidebar-nav glass">
    <div class="sidebar-logo">
      <img src="/logo.jpeg" alt="RocadaMed" class="sidebar-logo-img" />
      <span class="sidebar-brand text-gradient">RocadaMed</span>
    </div>

    <nav class="sidebar-menu">
      <router-link
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        class="sidebar-item"
        :class="{ active: isActive(item) }"
      >
        <div class="sidebar-icon-wrap">
          <span class="material-symbols-rounded sidebar-icon">{{ item.icon }}</span>
          <span v-if="item.badge" class="sidebar-badge">{{ item.badge }}</span>
        </div>
        <span class="sidebar-label">{{ item.label }}</span>
        <span class="material-symbols-rounded sidebar-arrow">chevron_right</span>
      </router-link>
    </nav>

    <div class="sidebar-user">
      <div class="sidebar-user-avatar">
        <span class="sidebar-user-initials">АП</span>
        <span class="sidebar-user-status"></span>
      </div>
      <div class="sidebar-user-info">
        <span class="sidebar-user-name">Алексей Петров</span>
        <span class="sidebar-user-role">Торговый представитель</span>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { useRoute } from 'vue-router'
import { visits } from '../data/mock'

const route = useRoute()
const pendingCount = visits.filter(v => v.status !== 'completed').length

const navItems = [
  { to: '/', icon: 'home', label: 'Главная', exact: true },
  { to: '/visits', icon: 'assignment', label: 'Визиты', badge: pendingCount },
  { to: '/map', icon: 'map', label: 'Карта' },
  { to: '/profile', icon: 'person', label: 'Профиль' }
]

function isActive(item) {
  if (item.exact) return route.path === item.to
  return route.path.startsWith(item.to)
}
</script>

<style scoped>
.sidebar-nav {
  display: none;
  width: var(--sidebar-width, 260px);
  height: 100dvh;
  position: fixed;
  left: 0;
  top: 0;
  flex-direction: column;
  background: var(--color-bg-secondary);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border-right: 1px solid var(--color-border);
  z-index: var(--z-dropdown);
  padding: var(--space-lg) 0;
  overflow-y: auto;
}

@media (min-width: 768px) {
  .sidebar-nav {
    display: flex;
  }
}

.sidebar-logo {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-sm) var(--space-lg);
  margin-bottom: var(--space-xl);
}

.sidebar-logo-img {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  object-fit: cover;
}

.sidebar-brand {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-extrabold);
  letter-spacing: -0.3px;
}

.sidebar-menu {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 0 var(--space-md);
}

.sidebar-item {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-md) var(--space-base);
  border-radius: var(--radius-md);
  text-decoration: none;
  color: var(--color-text-secondary);
  transition: all var(--transition-fast);
  position: relative;
}

.sidebar-item:hover {
  color: var(--color-text-primary);
  background: rgba(255, 255, 255, 0.04);
}

.sidebar-item.active {
  color: var(--color-accent);
  background: linear-gradient(135deg, rgba(0, 212, 170, 0.08), rgba(0, 102, 255, 0.08));
}

.sidebar-item.active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 25%;
  height: 50%;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: linear-gradient(180deg, var(--color-accent), var(--color-primary));
}

.sidebar-icon-wrap {
  position: relative;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sidebar-icon {
  font-size: 24px;
  transition: font-variation-settings var(--transition-fast);
}

.sidebar-item.active .sidebar-icon {
  font-variation-settings: 'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24;
}

.sidebar-badge {
  position: absolute;
  top: -6px;
  right: -10px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: var(--radius-full);
  background: var(--color-danger);
  color: white;
  font-size: 10px;
  font-weight: var(--font-weight-bold);
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.sidebar-label {
  flex: 1;
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-medium);
}

.sidebar-arrow {
  font-size: 18px;
  color: var(--color-text-tertiary);
  opacity: 0;
  transition: opacity var(--transition-fast);
}

.sidebar-item:hover .sidebar-arrow,
.sidebar-item.active .sidebar-arrow {
  opacity: 1;
}

.sidebar-user {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-base) var(--space-lg);
  margin-top: var(--space-base);
  border-top: 1px solid var(--color-border);
}

.sidebar-user-avatar {
  position: relative;
  width: 40px;
  height: 40px;
  border-radius: var(--radius-full);
  background: linear-gradient(135deg, var(--color-primary), var(--color-accent));
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.sidebar-user-initials {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-bold);
  color: white;
  letter-spacing: 0.5px;
}

.sidebar-user-status {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 10px;
  height: 10px;
  border-radius: var(--radius-full);
  background: var(--color-success);
  border: 2px solid var(--color-bg-secondary);
}

.sidebar-user-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.sidebar-user-name {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar-user-role {
  font-size: var(--font-size-xs);
  color: var(--color-text-tertiary);
}
</style>
