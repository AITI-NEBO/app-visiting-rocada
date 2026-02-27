<template>
  <AppSplash />

  <div class="app-shell" :class="{ 'has-sidebar': hasSidebar }">
    <SidebarNav v-if="showNav" />

    <main class="app-main">
      <router-view v-slot="{ Component, route }">
        <transition name="page" mode="out-in">
          <component :is="Component" :key="route.path" />
        </transition>
      </router-view>
    </main>

    <BottomNav v-if="showNav" />
    <InstallPrompt v-if="showNav" />
  </div>

  <OnboardingOverlay ref="onboarding" />
</template>

<script setup>
import { computed, ref, watch, onMounted, provide } from 'vue'
import { useRoute } from 'vue-router'
import BottomNav from './components/BottomNav.vue'
import SidebarNav from './components/SidebarNav.vue'
import InstallPrompt from './components/InstallPrompt.vue'
import OnboardingOverlay from './components/OnboardingOverlay.vue'
import AppSplash from './components/AppSplash.vue'

const route = useRoute()
const showNav = computed(() => route.path !== '/login')
const hasSidebar = computed(() => showNav.value)
const onboarding = ref(null)

// Theme management
const isDarkTheme = ref(false)

function applyTheme(dark) {
  isDarkTheme.value = dark
  document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light')
  localStorage.setItem('rocadamed_theme', dark ? 'dark' : 'light')
}

function toggleTheme() {
  applyTheme(!isDarkTheme.value)
}

provide('theme', { isDarkTheme, toggleTheme, applyTheme })

onMounted(() => {
  // Apply saved theme or default to light
  const saved = localStorage.getItem('rocadamed_theme')
  applyTheme(saved === 'dark')

  // Trigger onboarding
  if (route.path !== '/login') {
    onboarding.value?.checkAndShow()
  }
})

// Trigger onboarding after login
watch(() => route.path, (newPath, oldPath) => {
  if (oldPath === '/login' && newPath === '/') {
    onboarding.value?.checkAndShow()
  }
})
</script>

<style scoped>
.app-shell {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
  position: relative;
  background: var(--color-bg-primary);
}

.app-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
}

@media (min-width: 768px) {
  .app-shell.has-sidebar {
    flex-direction: row;
  }

  .app-shell.has-sidebar .app-main {
    margin-left: var(--sidebar-width, 260px);
    max-width: calc(100vw - var(--sidebar-width, 260px));
  }
}

@media (min-width: 1200px) {
  .app-shell.has-sidebar .app-main {
    padding: 0 var(--space-xl);
  }

  .app-shell.has-sidebar .app-main > * {
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
    width: 100%;
  }
}
</style>
