<template>
  <nav class="bottom-nav glass">
    <router-link
      v-for="item in navItems"
      :key="item.to"
      :to="item.to"
      class="nav-item"
      :class="{ active: isActive(item) }"
    >
      <div class="nav-icon-wrap">
        <span class="material-symbols-rounded nav-icon">{{ item.icon }}</span>
        <span v-if="item.badge" class="nav-badge">{{ item.badge }}</span>
      </div>
      <span class="nav-label">{{ item.label }}</span>
    </router-link>
  </nav>
</template>

<script setup>
import { useRoute } from 'vue-router'
import { visits } from '../data/mock'

const route = useRoute()

const pendingCount = visits.filter(v => v.status !== 'completed').length

const navItems = [
  { to: '/', icon: 'home', label: 'Главная', exact: true },
  { to: '/visits', icon: 'assignment', label: 'Визиты', badge: pendingCount },
  { to: '/profile', icon: 'person', label: 'Профиль' }
]

function isActive(item) {
  if (item.exact) return route.path === item.to
  return route.path.startsWith(item.to)
}
</script>

<style scoped>
.bottom-nav {
  position: fixed;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 100%;
  max-width: 430px;
  height: var(--bottom-nav-height);
  padding-bottom: var(--safe-area-bottom);
  display: flex;
  align-items: center;
  justify-content: space-around;
  background: rgba(10, 22, 40, 0.92);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border-top: 1px solid var(--color-border);
  z-index: var(--z-dropdown);
}

.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  text-decoration: none;
  color: var(--color-text-tertiary);
  transition: color var(--transition-fast);
  position: relative;
  padding: var(--space-sm) var(--space-lg);
  border-radius: var(--radius-md);
}

.nav-item.active {
  color: var(--color-accent);
}

.nav-item:active {
  transform: scale(0.95);
}

.nav-icon-wrap {
  position: relative;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.nav-icon {
  font-size: 26px;
  transition: font-variation-settings var(--transition-fast);
}

.nav-item.active .nav-icon {
  font-variation-settings: 'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24;
}

.nav-badge {
  position: absolute;
  top: -4px;
  right: -8px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
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

.nav-label {
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-medium);
  letter-spacing: 0.2px;
}
</style>
