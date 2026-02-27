<template>
  <router-link :to="`/visits/${visit.id}`" class="visit-card glass-light">
    <div class="card-top">
      <div class="card-time">
        <span class="material-symbols-rounded time-icon">schedule</span>
        <span class="time-text">{{ visit.time }} — {{ visit.timeEnd }}</span>
      </div>
      <div class="card-status" :style="{ color: statusColor, background: statusColor + '15' }">
        {{ statusLabel }}
      </div>
    </div>

    <div class="card-body">
      <h4 class="card-title">{{ visit.title }}</h4>
      <div class="card-meta">
        <div class="meta-row">
          <span class="material-symbols-rounded meta-icon">person</span>
          <span class="meta-text">{{ visit.client }} <span class="meta-role">• {{ visit.clientRole }}</span></span>
        </div>
        <div class="meta-row">
          <span class="material-symbols-rounded meta-icon">location_on</span>
          <span class="meta-text">{{ visit.address }}</span>
        </div>
      </div>
    </div>

    <div class="card-footer">
      <div class="card-type" :style="{ color: typeColor }">
        <span class="material-symbols-rounded type-icon">{{ typeIcon }}</span>
        <span class="type-label">{{ typeLabel }}</span>
      </div>
      <span v-if="visit.orderAmount" class="card-amount">{{ formatCurrency(visit.orderAmount) }}</span>
      <span class="material-symbols-rounded card-arrow">chevron_right</span>
    </div>
  </router-link>
</template>

<script setup>
import { computed } from 'vue'
import { getStatusLabel, getStatusColor, getVisitTypeLabel, getVisitTypeIcon, formatCurrency } from '../data/mock'

const props = defineProps({
  visit: { type: Object, required: true }
})

const statusLabel = computed(() => getStatusLabel(props.visit.status))
const statusColor = computed(() => getStatusColor(props.visit.status))
const typeLabel = computed(() => getVisitTypeLabel(props.visit.type))
const typeIcon = computed(() => getVisitTypeIcon(props.visit.type))
const typeColor = computed(() => {
  const colors = {
    order: 'var(--color-accent)',
    presentation: 'var(--color-primary)',
    consultation: 'var(--color-warning)',
    new_client: '#A78BFA'
  }
  return colors[props.visit.type] || 'var(--color-text-secondary)'
})
</script>

<style scoped>
.visit-card {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding: var(--space-base);
  border-radius: var(--radius-lg);
  text-decoration: none;
  color: var(--color-text-primary);
  transition: transform var(--transition-fast), background var(--transition-fast);
}

.visit-card:active {
  transform: scale(0.98);
  background: var(--color-bg-card-hover);
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-time {
  display: flex;
  align-items: center;
  gap: var(--space-xs);
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
}

.time-icon {
  font-size: 16px;
}

.time-text {
  font-weight: var(--font-weight-medium);
}

.card-status {
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-semibold);
  padding: 3px 8px;
  border-radius: var(--radius-full);
  letter-spacing: 0.2px;
}

.card-body {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.card-title {
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-semibold);
  line-height: var(--line-height-tight);
}

.card-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.meta-row {
  display: flex;
  align-items: flex-start;
  gap: var(--space-xs);
  font-size: var(--font-size-sm);
  color: var(--color-text-tertiary);
}

.meta-icon {
  font-size: 16px;
  flex-shrink: 0;
  margin-top: 1px;
}

.meta-text {
  line-height: var(--line-height-normal);
}

.meta-role {
  color: var(--color-text-tertiary);
  opacity: 0.7;
}

.card-footer {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  padding-top: var(--space-sm);
  border-top: 1px solid var(--color-border);
}

.card-type {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-medium);
}

.type-icon {
  font-size: 16px;
}

.card-amount {
  margin-left: auto;
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-bold);
  color: var(--color-accent);
}

.card-arrow {
  font-size: 18px;
  color: var(--color-text-tertiary);
  margin-left: auto;
}

.card-amount + .card-arrow {
  margin-left: 0;
}
</style>
