<template>
  <router-link :to="`/visits/${visit.id}`" class="visit-card" :class="visit.status">
    <div class="card-status-bar" :style="{ background: statusColor }"></div>
    <div class="card-body">
      <div class="card-top">
        <span class="card-time">
          <span class="material-symbols-rounded time-icon">schedule</span>
          {{ visit.time }}
        </span>
        <span class="card-badge" :style="{ color: statusColor, background: statusBg }">
          {{ statusLabel }}
        </span>
      </div>
      <h3 class="card-title">{{ visit.title }}</h3>
      <div class="card-info">
        <div class="info-row">
          <span class="material-symbols-rounded info-icon">location_on</span>
          <span class="info-text">{{ visit.address }}</span>
        </div>
        <div class="info-row">
          <span class="material-symbols-rounded info-icon">person</span>
          <span class="info-text">{{ visit.client }}</span>
        </div>
      </div>
    </div>
    <div class="card-arrow">
      <span class="material-symbols-rounded">chevron_right</span>
    </div>
  </router-link>
</template>

<script setup>
import { computed } from 'vue'
import { getStatusLabel, getStatusColor } from '../data/mock'

const props = defineProps({
  visit: { type: Object, required: true }
})

const statusLabel = computed(() => getStatusLabel(props.visit.status))
const statusColor = computed(() => getStatusColor(props.visit.status))
const statusBg = computed(() => {
  const map = {
    completed: 'rgba(0, 196, 140, 0.12)',
    in_progress: 'rgba(255, 176, 32, 0.12)',
    pending: 'rgba(255, 255, 255, 0.06)'
  }
  return map[props.visit.status] || 'rgba(255,255,255,0.06)'
})
</script>

<style scoped>
.visit-card {
  display: flex;
  align-items: stretch;
  background: var(--color-bg-card);
  border-radius: var(--radius-lg);
  overflow: hidden;
  text-decoration: none;
  color: var(--color-text-primary);
  transition: transform var(--transition-fast), background var(--transition-fast), box-shadow var(--transition-fast);
  border: 1px solid var(--color-border);
  animation: fadeInUp var(--transition-slow) ease both;
}

.visit-card:active {
  transform: scale(0.98);
  background: var(--color-bg-card-hover);
}

.card-status-bar {
  width: 4px;
  flex-shrink: 0;
}

.card-body {
  flex: 1;
  padding: var(--space-base);
  min-width: 0;
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--space-sm);
}

.card-time {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  font-weight: var(--font-weight-medium);
}

.time-icon {
  font-size: 16px;
}

.card-badge {
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-semibold);
  padding: 3px 10px;
  border-radius: var(--radius-full);
  letter-spacing: 0.3px;
}

.card-title {
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-semibold);
  line-height: var(--line-height-tight);
  margin-bottom: var(--space-sm);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.card-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.info-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.info-icon {
  font-size: 16px;
  color: var(--color-text-tertiary);
  flex-shrink: 0;
}

.info-text {
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.card-arrow {
  display: flex;
  align-items: center;
  padding-right: var(--space-sm);
  color: var(--color-text-tertiary);
}
</style>
