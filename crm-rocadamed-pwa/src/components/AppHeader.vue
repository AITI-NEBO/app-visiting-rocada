<template>
  <header class="app-header glass">
    <div class="header-left">
      <button v-if="showBack" class="header-back" @click="$router.back()">
        <span class="material-symbols-rounded">arrow_back</span>
      </button>
      <div v-else class="header-logo">
        <div class="logo-icon">R</div>
      </div>
    </div>
    <h1 class="header-title">{{ title }}</h1>
    <div class="header-right">
      <router-link to="/profile" class="header-avatar" aria-label="Профиль">
        <span class="avatar-initials">АП</span>
        <span class="avatar-status"></span>
      </router-link>
    </div>
  </header>
</template>

<script setup>
defineProps({
  title: { type: String, default: 'RocadaMed' },
  showBack: { type: Boolean, default: false }
})
</script>

<style scoped>
.app-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: var(--header-height);
  padding: 0 var(--space-base);
  position: sticky;
  top: 0;
  z-index: var(--z-dropdown);
  background: rgba(10, 22, 40, 0.85);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--color-border);
}

.header-left {
  width: 40px;
  display: flex;
  align-items: center;
}

.header-back {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text-primary);
  transition: background var(--transition-fast);
}

.header-back:active {
  background: var(--color-bg-card);
}

.logo-icon {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: var(--font-weight-extrabold);
  font-size: var(--font-size-md);
  color: white;
}

.header-title {
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text-primary);
  text-align: center;
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.header-right {
  width: 40px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.header-avatar {
  position: relative;
  width: 34px;
  height: 34px;
  border-radius: var(--radius-full);
  background: linear-gradient(135deg, var(--color-primary), var(--color-accent));
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
}

.avatar-initials {
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-bold);
  color: white;
  letter-spacing: 0.5px;
}

.avatar-status {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 10px;
  height: 10px;
  border-radius: var(--radius-full);
  background: var(--color-success);
  border: 2px solid var(--color-bg-primary);
}
</style>
