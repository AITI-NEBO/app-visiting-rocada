<template>
  <router-link :to="`/visits/${visit.id}`" class="visit-card glass-light">
    <div class="card-top">
      <div class="card-date">
        <span class="material-symbols-rounded time-icon">schedule</span>
        <span class="time-text">{{ displayDate }}</span>
      </div>
      <div class="card-status" :style="{ color: statusColor, background: statusColor + '15' }">
        {{ statusLabel }}
      </div>
    </div>

    <div class="card-body">
      <h4 class="card-title">{{ visit.title }}</h4>
      <div class="card-meta">
        <div v-if="visit.company_address" class="meta-row">
          <span class="material-symbols-rounded meta-icon">location_on</span>
          <span class="meta-text">{{ visit.company_address }}</span>
        </div>
        <div v-if="visit.opportunity" class="meta-row">
          <span class="material-symbols-rounded meta-icon">payments</span>
          <span class="meta-text meta-amount">{{ formatAmount(visit.opportunity) }} {{ visit.currency || '₽' }}</span>
        </div>
        <div v-if="visit.comments" class="meta-row">
          <span class="material-symbols-rounded meta-icon">chat</span>
          <span class="meta-text meta-comment">{{ truncate(visit.comments, 60) }}</span>
        </div>
      </div>
    </div>

    <div class="card-footer">
      <div class="card-info">
        <span class="material-symbols-rounded footer-icon">event</span>
        <span class="footer-text">{{ visit.visit_date ? formatVisitDate(visit.visit_date) : (visit.date || '—') }}</span>
      </div>
      <div v-if="visit.geo_set" class="card-geo">
        <span class="material-symbols-rounded footer-icon" style="color: var(--color-success)">location_on</span>
      </div>
      <span class="material-symbols-rounded card-arrow">chevron_right</span>
    </div>
  </router-link>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  visit: { type: Object, required: true }
})

// Карта стадий → цвет (можно дополнить при необходимости)
const stageColors = {
  NEW: '#4DA6FF',
  PREPARATION: '#FFB020',
  PREPAYMENT_INVOICE: '#A78BFA',
  EXECUTING: '#FF6B35',
  FINAL_INVOICE: '#F59E0B',
  WON: '#00C48C',
  LOSE: '#FF4D6A',
  APOLOGY: '#94A3B8',
}

function getStageColor(stageId) {
  if (!stageId) return '#94A3B8'
  // C7:NEW → NEW
  const short = stageId.includes(':') ? stageId.split(':')[1] : stageId
  return stageColors[short] || '#4DA6FF'
}

const statusLabel = computed(() => props.visit.stage_name || props.visit.stage_id || '—')
const statusColor = computed(() => getStageColor(props.visit.stage_id))

const displayDate = computed(() => {
  const src = props.visit.visit_date || props.visit.date
  if (!src) return '—'
  try {
    const d = new Date(src)
    if (isNaN(d)) return src
    // Если есть время (не 00:00)
    const hasTime = d.getHours() !== 0 || d.getMinutes() !== 0
    if (hasTime) {
      return d.toLocaleString('ru-RU', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
    }
    return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
  } catch {
    return src
  }
})

function formatVisitDate(iso) {
  if (!iso) return '—'
  try {
    const d = new Date(iso)
    if (isNaN(d)) return iso
    return d.toLocaleString('ru-RU', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
  } catch {
    return iso
  }
}

function formatAmount(val) {
  if (!val) return ''
  return Number(val).toLocaleString('ru-RU', { maximumFractionDigits: 0 })
}

function stripBBCode(str) {
  if (!str) return ''
  return str.replace(/\[\/?\w+(?:=[^\]]+)?\]/g, '').trim()
}

function truncate(str, len) {
  const clean = stripBBCode(str)
  return clean.length > len ? clean.slice(0, len) + '…' : clean
}
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

.card-date {
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
  max-width: 140px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
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

.meta-amount {
  color: var(--color-accent);
  font-weight: var(--font-weight-semibold);
}

.meta-comment {
  font-style: italic;
  opacity: 0.8;
}

.card-footer {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  padding-top: var(--space-sm);
  border-top: 1px solid var(--color-border);
}

.card-info {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: var(--font-size-xs);
  color: var(--color-text-tertiary);
}

.footer-icon {
  font-size: 16px;
}

.footer-text {
  font-weight: var(--font-weight-medium);
}

.card-geo {
  display: flex;
  align-items: center;
}

.card-arrow {
  font-size: 18px;
  color: var(--color-text-tertiary);
  margin-left: auto;
}
</style>
