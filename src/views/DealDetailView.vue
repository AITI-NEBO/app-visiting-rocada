<template>
  <div class="deal-page">
    <AppHeader :title="visit?.title || 'Визит'" :showBack="true" />

    <!-- Loading -->
    <div v-if="!visit" class="loading-state">
      <span class="material-symbols-rounded spin" style="font-size:32px; color: var(--color-primary)">progress_activity</span>
    </div>

    <div v-if="visit" class="page-content">
      <!-- Status hero -->
      <div class="deal-hero animate-fade-in-up">
        <div class="hero-status" :style="{ background: statusBg }">
          <span class="hero-status-dot" :style="{ background: statusColor }"></span>
          <span class="hero-status-text" :style="{ color: statusColor }">{{ visit.stage_name || visit.stage_id }}</span>
        </div>
        <h2 class="hero-title">{{ visit.title }}</h2>
        <div class="hero-meta">
          <span v-if="visit.visit_date" class="hero-info">
            <span class="material-symbols-rounded" style="font-size:16px">schedule</span>
            {{ formatDateTime(visit.visit_date) }}
          </span>
          <span v-else-if="visit.date" class="hero-info">
            <span class="material-symbols-rounded" style="font-size:16px">event</span>
            {{ formatDate(visit.date) }}
          </span>
          <span v-if="visit.close_date" class="hero-info">
            <span class="material-symbols-rounded" style="font-size:16px">event_available</span>
            до {{ formatDate(visit.close_date) }}
          </span>
        </div>
      </div>

      <!-- Deal info cards -->
      <div class="info-grid animate-fade-in-up" style="animation-delay: 80ms">
        <div v-if="visit.opportunity" class="info-card">
          <div class="info-card-icon" style="background: rgba(0, 196, 140, 0.12)">
            <span class="material-symbols-rounded" style="color: var(--color-success)">payments</span>
          </div>
          <div class="info-card-content">
            <span class="info-card-label">Сумма</span>
            <span class="info-card-value">{{ formatAmount(visit.opportunity) }} {{ visit.currency || '₽' }}</span>
          </div>
        </div>

        <div v-if="visit.date_create" class="info-card">
          <div class="info-card-icon" style="background: rgba(0, 102, 255, 0.12)">
            <span class="material-symbols-rounded" style="color: var(--color-primary)">calendar_today</span>
          </div>
          <div class="info-card-content">
            <span class="info-card-label">Создана</span>
            <span class="info-card-value">{{ formatDate(visit.date_create) }}</span>
          </div>
        </div>

        <div v-if="visit.geo_set" class="info-card">
          <div class="info-card-icon" style="background: rgba(0, 196, 140, 0.12)">
            <span class="material-symbols-rounded" style="color: var(--color-success)">location_on</span>
          </div>
          <div class="info-card-content">
            <span class="info-card-label">Геолокация</span>
            <span class="info-card-value">{{ visit.lat?.toFixed(4) }}, {{ visit.lng?.toFixed(4) }}</span>
          </div>
        </div>
      </div>

      <!-- Company -->
      <div v-if="company" class="section animate-fade-in-up" style="animation-delay: 100ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">business</span>
          Компания
        </h3>
        <router-link
          :to="company.id ? `/company/${company.id}` : '#'"
          class="detail-card detail-card-link"
        >
          <div class="detail-row">
            <span class="detail-label">Название</span>
            <span class="detail-value">{{ company.title }}</span>
          </div>
          <div v-if="company.phone" class="detail-row">
            <span class="detail-label">Телефон</span>
            <span class="detail-value" style="color:var(--color-primary)">{{ company.phone }}</span>
          </div>
          <div v-if="company.address" class="detail-row">
            <span class="detail-label">Адрес</span>
            <span class="detail-value">{{ company.address }}</span>
          </div>
          <div v-if="company.id" class="detail-row" style="justify-content:flex-end">
            <span style="font-size:12px;color:var(--color-primary);display:flex;align-items:center;gap:4px">
              <span class="material-symbols-rounded" style="font-size:16px">open_in_new</span>
              Открыть карточку
            </span>
          </div>
        </router-link>
      </div>

      <!-- Contact -->
      <div v-if="contact" class="section animate-fade-in-up" style="animation-delay: 120ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">person</span>
          Контакт
        </h3>
        <router-link
          :to="contact.id ? `/contact/${contact.id}` : '#'"
          class="detail-card detail-card-link"
        >
          <div class="detail-row">
            <span class="detail-label">ФИО</span>
            <span class="detail-value">{{ contact.fullName }}</span>
          </div>
          <div v-if="contact.position" class="detail-row">
            <span class="detail-label">Должность</span>
            <span class="detail-value">{{ contact.position }}</span>
          </div>
          <div v-if="contact.phone" class="detail-row">
            <span class="detail-label">Телефон</span>
            <span class="detail-value" style="color:var(--color-primary)">{{ contact.phone }}</span>
          </div>
          <div v-if="contact.id" class="detail-row" style="justify-content:flex-end">
            <span style="font-size:12px;color:var(--color-primary);display:flex;align-items:center;gap:4px">
              <span class="material-symbols-rounded" style="font-size:16px">open_in_new</span>
              Открыть карточку
            </span>
          </div>
        </router-link>
      </div>

      <!-- Comments -->
      <div v-if="visit.comments" class="section animate-fade-in-up" style="animation-delay: 140ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">chat</span>
          Комментарий
        </h3>
        <div class="detail-card">
          <div class="comment-text bbcode" v-html="parseBBCode(visit.comments)"></div>
        </div>
      </div>

      <!-- Products -->
      <div v-if="products.length" class="section animate-fade-in-up" style="animation-delay: 160ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">inventory_2</span>
          Товары ({{ products.length }})
        </h3>
        <div class="products-list">
          <div class="product-item" v-for="p in products" :key="p.id">
            <div class="product-info">
              <span class="material-symbols-rounded product-icon">package_2</span>
              <span class="product-name">{{ p.name }}</span>
            </div>
            <div class="product-right">
              <span class="product-qty">{{ p.quantity }} шт.</span>
              <span class="product-price">{{ formatAmount(p.sum) }} ₽</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Extra deal fields configured in direction -->
      <div
        v-if="extraFields.length"
        class="section animate-fade-in-up"
        style="animation-delay: 170ms"
      >
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">list_alt</span>
          Доп. поля
        </h3>
        <div class="detail-card">
          <div v-for="f in extraFields" :key="f.code" class="detail-row">
            <span class="detail-label">{{ f.label }}</span>
            <span class="detail-value">{{ f.value || '—' }}</span>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="actions-section animate-fade-in-up" style="animation-delay: 200ms">
        <router-link
          v-if="!visit.stage_id?.includes('WON')"
          :to="`/map?planDealId=${visit.id}&title=${encodeURIComponent(visit.title||'')}`"
          class="action-btn action-plan"
        >
          <span class="material-symbols-rounded action-btn-icon">calendar_add_on</span>
          Запланировать визит
        </router-link>

        <router-link
          v-if="!visit.stage_id?.includes('WON')"
          :to="`/visits/${visit.id}/result`"
          class="action-btn action-complete"
        >
          <span class="material-symbols-rounded action-btn-icon">task_alt</span>
          Завершить визит
        </router-link>

        <router-link :to="`/visits/${visit.id}/comment`" class="action-btn action-comment">
          <span class="material-symbols-rounded action-btn-icon">edit_note</span>
          Написать комментарий
        </router-link>

        <a :href="'tel:' + contact?.phone" v-if="contact?.phone" class="action-btn action-call">
          <span class="material-symbols-rounded action-btn-icon">phone</span>
          Позвонить контакту
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import { useVisits } from '../composables/useVisits'
import { parseBBCode } from '../data/utils'

const route = useRoute()
const { findVisit, loadVisits, loadVisitDetail } = useVisits()

const visit = ref(null)
const company = ref(null)
const contact = ref(null)
const products = ref([])

onMounted(async () => {
  await loadVisits()
  visit.value = findVisit(route.params.id)

  try {
    const detail = await loadVisitDetail(route.params.id)
    if (detail?.deal) {
      visit.value = { ...(visit.value || {}), ...detail.deal }
    }
    company.value = detail?.company || null
    contact.value = detail?.contact || null
    products.value = detail?.products || []
  } catch (e) {
    console.warn('[DealDetail] Could not load details:', e)
  }
})

// Доп. поля из конфига направления (приходят с бэка как [{code, label, value}])
const extraFields = computed(() =>
  (visit.value?.fields || []).filter(f => f.value !== '' && f.value != null)
)

// Stage color
function getStageColor(stageId) {
  if (!stageId) return '#94A3B8'
  const short = stageId.includes(':') ? stageId.split(':')[1] : stageId
  const map = { NEW: '#4DA6FF', PREPARATION: '#FFB020', PREPAYMENT_INVOICE: '#A78BFA', EXECUTING: '#FF6B35', WON: '#00C48C', LOSE: '#FF4D6A' }
  return map[short] || '#4DA6FF'
}

const statusColor = computed(() => visit.value ? getStageColor(visit.value.stage_id) : '')
const statusBg = computed(() => visit.value ? statusColor.value + '15' : '')

function formatDate(d) {
  if (!d) return ''
  try {
    return new Date(d).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' })
  } catch { return d }
}

function formatDateTime(d) {
  if (!d) return ''
  try {
    const dt = new Date(d)
    if (isNaN(dt)) return d
    return dt.toLocaleString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  } catch { return d }
}

function formatAmount(val) {
  if (!val) return '0'
  return Number(val).toLocaleString('ru-RU', { maximumFractionDigits: 0 })
}
</script>

<style scoped>
.deal-page {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
}

.loading-state {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
.spin { animation: spin 1s linear infinite; }

.page-content {
  flex: 1;
  padding: var(--space-base);
  padding-bottom: calc(var(--bottom-nav-height) + var(--safe-area-bottom) + var(--space-xl));
  display: flex;
  flex-direction: column;
  gap: var(--space-base);
}

/* Hero */
.deal-hero {
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  padding: var(--space-lg);
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.hero-status {
  display: inline-flex;
  align-items: center;
  align-self: flex-start;
  gap: 6px;
  padding: 4px 12px;
  border-radius: var(--radius-full);
}

.hero-status-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
}

.hero-status-text {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-semibold);
}

.hero-title {
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  line-height: var(--line-height-tight);
}

.hero-meta {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-base);
}

.hero-info {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
}

/* Info grid */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: var(--space-md);
}

.info-card {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-base);
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.info-card-icon {
  width: 40px; height: 40px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.info-card-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.info-card-label {
  font-size: var(--font-size-xs);
  color: var(--color-text-tertiary);
}

.info-card-value {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text-primary);
}

/* Sections */
.section {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.section-title {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-semibold);
}

.section-icon {
  font-size: 20px;
  color: var(--color-primary);
}

.detail-card {
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-base);
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: var(--space-base);
}

.detail-label {
  font-size: var(--font-size-sm);
  color: var(--color-text-tertiary);
  flex-shrink: 0;
}

.detail-value {
  font-size: var(--font-size-sm);
  color: var(--color-text-primary);
  text-align: right;
}

.detail-link {
  font-size: var(--font-size-sm);
  color: var(--color-primary);
  text-decoration: none;
  text-align: right;
}

.comment-text {
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  line-height: var(--line-height-relaxed);
}

.bbcode :deep(a) {
  color: var(--color-primary);
  word-break: break-all;
}

.bbcode :deep(strong) {
  color: var(--color-text-primary);
  font-weight: var(--font-weight-semibold);
}

.bbcode :deep(p) {
  margin: 4px 0;
}

.bbcode :deep(blockquote) {
  border-left: 3px solid var(--color-primary);
  padding: 4px 12px;
  margin: 4px 0;
  color: var(--color-text-secondary);
}

.bbcode :deep(img) {
  max-width: 100%;
  border-radius: 8px;
  margin: 4px 0;
}

.bbcode :deep(code) {
  background: var(--color-bg-elevated);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 0.85em;
}

/* Products */
.products-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.product-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-md) var(--space-base);
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.product-info {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  min-width: 0;
}

.product-icon {
  font-size: 18px;
  color: var(--color-text-tertiary);
  flex-shrink: 0;
}

.product-name {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-medium);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.product-right {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  flex-shrink: 0;
}

.product-qty {
  font-size: var(--font-size-xs);
  color: var(--color-text-tertiary);
}

.product-price {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-bold);
  color: var(--color-accent);
}

/* Actions */
.actions-section {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  margin-top: var(--space-base);
}

.action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  padding: var(--space-base);
  border-radius: var(--radius-lg);
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-semibold);
  text-decoration: none;
  transition: all var(--transition-base);
}

.action-complete {
  background: linear-gradient(135deg, var(--color-success), #00a06a);
  color: white;
}

.action-plan {
  background: var(--color-bg-card);
  border: 1px solid var(--color-primary);
  color: var(--color-primary);
}

.action-comment {
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  color: var(--color-text-primary);
}

.action-call {
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  color: var(--color-primary);
}

.action-btn-icon {
  font-size: 20px;
}
.detail-card-link { display: block; text-decoration: none; color: inherit; cursor: pointer; }
.detail-card-link:hover .detail-value { color: var(--color-primary); }
</style>
