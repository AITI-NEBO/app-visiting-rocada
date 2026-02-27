<template>
  <div class="deal-page">
    <AppHeader :title="visit?.title || 'Визит'" :showBack="true" />

    <div v-if="visit" class="page-content">
      <!-- Status hero -->
      <div class="deal-hero animate-fade-in-up">
        <div class="hero-status" :style="{ background: statusBg }">
          <span class="hero-status-dot" :style="{ background: statusColor }"></span>
          <span class="hero-status-text" :style="{ color: statusColor }">{{ statusLabel }}</span>
        </div>
        <h2 class="hero-title">{{ visit.title }}</h2>
        <div class="hero-meta">
          <span class="hero-type" :style="{ color: typeColor }">
            <span class="material-symbols-rounded" style="font-size:16px">{{ typeIcon }}</span>
            {{ typeLabel }}
          </span>
          <span class="hero-time">
            <span class="material-symbols-rounded" style="font-size:16px">schedule</span>
            {{ visit.time }} — {{ visit.timeEnd }}
          </span>
        </div>
      </div>

      <!-- Info Reason -->
      <div v-if="visit.infoReason" class="info-reason-section animate-fade-in-up" style="animation-delay: 80ms">
        <div class="info-reason-card">
          <span class="material-symbols-rounded info-reason-icon">campaign</span>
          <div class="info-reason-content">
            <span class="info-reason-label">Инфоповод</span>
            <p class="info-reason-text">{{ visit.infoReason }}</p>
          </div>
        </div>
      </div>

      <!-- LPR (Decision Maker) -->
      <div v-if="visit.lpr" class="lpr-section animate-fade-in-up" style="animation-delay: 100ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">person</span>
          ЛПР (лицо, принимающее решение)
        </h3>
        <div class="lpr-card">
          <div class="lpr-avatar">
            <span>{{ visit.lpr.name[0] }}</span>
          </div>
          <div class="lpr-info">
            <span class="lpr-name">{{ visit.lpr.name }}</span>
            <span class="lpr-role">{{ visit.lpr.role }}</span>
          </div>
        </div>
        <div class="lpr-contacts">
          <a :href="'tel:' + visit.lpr.phone" class="lpr-contact-item">
            <span class="material-symbols-rounded lpr-contact-icon" style="color: var(--color-success)">phone</span>
            <span>{{ visit.lpr.phone }}</span>
          </a>
          <a v-if="visit.lpr.email" :href="'mailto:' + visit.lpr.email" class="lpr-contact-item">
            <span class="material-symbols-rounded lpr-contact-icon" style="color: var(--color-primary)">email</span>
            <span>{{ visit.lpr.email }}</span>
          </a>
          <div v-if="visit.lpr.telegram" class="lpr-contact-item">
            <span class="material-symbols-rounded lpr-contact-icon" style="color: #229ED9">chat</span>
            <span>{{ visit.lpr.telegram }}</span>
          </div>
        </div>
      </div>

      <!-- Address -->
      <div class="info-section animate-fade-in-up" style="animation-delay: 120ms">
        <div class="info-card">
          <div class="info-card-icon" style="background: rgba(0, 102, 255, 0.12)">
            <span class="material-symbols-rounded" style="color: var(--color-primary)">location_on</span>
          </div>
          <div class="info-card-content">
            <span class="info-card-label">Адрес</span>
            <span class="info-card-value">{{ visit.address }}</span>
          </div>
        </div>
      </div>

      <!-- Description -->
      <div class="description-section animate-fade-in-up" style="animation-delay: 150ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">description</span>
          Описание визита
        </h3>
        <p class="description-text">{{ visit.description }}</p>
      </div>

      <!-- Products -->
      <div v-if="visit.products && visit.products.length" class="products-section animate-fade-in-up" style="animation-delay: 180ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">inventory_2</span>
          Товары
        </h3>
        <div class="products-list">
          <div class="product-item" v-for="(product, i) in visit.products" :key="i">
            <div class="product-info">
              <span class="material-symbols-rounded product-icon">package_2</span>
              <span class="product-name">{{ product.name }}</span>
            </div>
            <span class="product-price">{{ visit.isApproximate ? '≈ ' : '' }}{{ formatCurrency(product.price) }}</span>
          </div>
        </div>
        <div v-if="visit.orderAmount" class="order-total">
          <span class="order-total-label">{{ visit.isApproximate ? 'Примерная сумма' : 'Сумма заказа' }}</span>
          <span class="order-total-value">{{ visit.isApproximate ? '≈ ' : '' }}{{ formatCurrency(visit.orderAmount) }}</span>
        </div>
      </div>

      <!-- Visit Result (if completed) -->
      <div v-if="visit.result" class="result-section animate-fade-in-up" style="animation-delay: 200ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">assignment_turned_in</span>
          Итог визита
        </h3>
        <div class="result-card">
          <div class="result-status-badge" :style="{ color: resultStatusColor }">
            <span class="material-symbols-rounded" style="font-size: 18px">{{ resultStatusIcon }}</span>
            {{ resultStatusLabel }}
          </div>
          <p class="result-text">{{ visit.result.text }}</p>
          <span class="result-time">{{ visit.result.completedAt }}</span>
        </div>
      </div>

      <!-- Geo status -->
      <div v-if="visit.geoSent" class="map-section animate-fade-in-up" style="animation-delay: 220ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">map</span>
          Геолокация подтверждена
        </h3>
        <div class="map-preview">
          <div class="map-placeholder">
            <span class="material-symbols-rounded map-pin">check_circle</span>
            <div class="map-coords">
              <span>{{ visit.geoLat?.toFixed(4) }}, {{ visit.geoLng?.toFixed(4) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Comments -->
      <div v-if="visit.comments.length" class="comments-section animate-fade-in-up" style="animation-delay: 250ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">chat</span>
          Комментарии ({{ visit.comments.length }})
        </h3>
        <div class="comment-item" v-for="(comment, i) in visit.comments" :key="i">
          <div class="comment-avatar"><span>{{ comment.author[0] }}</span></div>
          <div class="comment-body">
            <div class="comment-header">
              <span class="comment-author">{{ comment.author }}</span>
              <span class="comment-date">{{ comment.time }}</span>
            </div>
            <p class="comment-text">{{ comment.text }}</p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="actions-section animate-fade-in-up" style="animation-delay: 300ms">
        <!-- Complete visit button -->
        <router-link
          v-if="visit.status !== 'completed' && !visit.result"
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

        <a :href="'tel:' + visit.lpr?.phone" v-if="visit.lpr?.phone" class="action-btn action-call">
          <span class="material-symbols-rounded action-btn-icon">phone</span>
          Позвонить ЛПР
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import { visits, visitsTomorrow, getStatusLabel, getStatusColor, getVisitTypeLabel, getVisitTypeIcon, getResultStatusLabel, resultStatusOptions, formatCurrency } from '../data/mock'

const route = useRoute()
const allVisits = [...visits, ...visitsTomorrow]
const visit = computed(() => allVisits.find(v => v.id === Number(route.params.id)))

const statusLabel = computed(() => visit.value ? getStatusLabel(visit.value.status) : '')
const statusColor = computed(() => visit.value ? getStatusColor(visit.value.status) : '')
const statusBg = computed(() => {
  if (!visit.value) return ''
  const map = {
    completed: 'rgba(0, 196, 140, 0.1)',
    in_progress: 'rgba(255, 176, 32, 0.1)',
    planned: 'rgba(77, 166, 255, 0.1)'
  }
  return map[visit.value.status] || 'rgba(0,0,0,0.05)'
})

const typeLabel = computed(() => visit.value ? getVisitTypeLabel(visit.value.type) : '')
const typeIcon = computed(() => visit.value ? getVisitTypeIcon(visit.value.type) : '')
const typeColor = computed(() => {
  if (!visit.value) return ''
  const colors = { order: 'var(--color-accent)', presentation: 'var(--color-primary)', consultation: 'var(--color-warning)', new_client: '#A78BFA' }
  return colors[visit.value.type] || 'var(--color-text-secondary)'
})

const resultStatusLabel = computed(() => visit.value?.result ? getResultStatusLabel(visit.value.result.status) : '')
const resultStatusColor = computed(() => {
  if (!visit.value?.result) return ''
  const opt = resultStatusOptions.find(o => o.value === visit.value.result.status)
  return opt?.color || 'var(--color-text-secondary)'
})
const resultStatusIcon = computed(() => {
  if (!visit.value?.result) return ''
  const opt = resultStatusOptions.find(o => o.value === visit.value.result.status)
  return opt?.icon || 'check'
})
</script>

<style scoped>
.deal-page { display: flex; flex-direction: column; min-height: 100dvh; }

.page-content {
  flex: 1; padding: var(--space-base);
  padding-bottom: calc(var(--bottom-nav-height) + var(--safe-area-bottom) + var(--space-xl));
  display: flex; flex-direction: column; gap: var(--space-lg);
}

@media (min-width: 768px) {
  .page-content { padding: var(--space-xl); padding-bottom: var(--space-xl); }
}

/* Hero */
.deal-hero { text-align: center; padding: var(--space-lg) 0; }

.hero-status {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 14px; border-radius: var(--radius-full); margin-bottom: var(--space-md);
}

.hero-status-dot { width: 8px; height: 8px; border-radius: 50%; }
.hero-status-text { font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); }

.hero-title {
  font-size: var(--font-size-xl); font-weight: var(--font-weight-bold);
  line-height: var(--line-height-tight); margin-bottom: var(--space-sm);
}

.hero-meta {
  display: flex; align-items: center; justify-content: center; gap: var(--space-base);
  color: var(--color-text-secondary); font-size: var(--font-size-sm);
}

.hero-type { display: flex; align-items: center; gap: 4px; font-weight: var(--font-weight-medium); }
.hero-time { display: flex; align-items: center; gap: 4px; }

/* Info Reason */
.info-reason-card {
  display: flex; gap: var(--space-md); padding: var(--space-base);
  background: linear-gradient(135deg, rgba(255, 176, 32, 0.08), rgba(255, 176, 32, 0.03));
  border-radius: var(--radius-lg); border: 1px solid rgba(255, 176, 32, 0.2);
}

.info-reason-icon { font-size: 24px; color: var(--color-warning); flex-shrink: 0; margin-top: 2px; }
.info-reason-content { display: flex; flex-direction: column; gap: 4px; }
.info-reason-label { font-size: var(--font-size-xs); color: var(--color-warning); font-weight: var(--font-weight-semibold); text-transform: uppercase; letter-spacing: 0.5px; }
.info-reason-text { font-size: var(--font-size-sm); color: var(--color-text-secondary); line-height: var(--line-height-relaxed); }

/* LPR */
.lpr-card {
  display: flex; align-items: center; gap: var(--space-md);
  padding: var(--space-base); background: var(--color-bg-card);
  border-radius: var(--radius-lg); border: 1px solid var(--color-border);
  margin-bottom: var(--space-md); box-shadow: var(--shadow-sm);
}

.lpr-avatar {
  width: 44px; height: 44px; border-radius: 50%;
  background: linear-gradient(135deg, var(--color-primary), var(--color-accent));
  display: flex; align-items: center; justify-content: center;
  font-size: var(--font-size-md); font-weight: var(--font-weight-bold); color: white;
  flex-shrink: 0;
}

.lpr-info { display: flex; flex-direction: column; gap: 2px; }
.lpr-name { font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); }
.lpr-role { font-size: var(--font-size-xs); color: var(--color-text-tertiary); }

.lpr-contacts { display: flex; flex-direction: column; gap: var(--space-sm); }

.lpr-contact-item {
  display: flex; align-items: center; gap: var(--space-sm);
  padding: var(--space-md) var(--space-base);
  background: var(--color-bg-card); border-radius: var(--radius-md);
  border: 1px solid var(--color-border); font-size: var(--font-size-sm);
  color: var(--color-text-primary); text-decoration: none;
  transition: background var(--transition-fast);
}

.lpr-contact-item:active { background: var(--color-bg-card-hover); }
.lpr-contact-icon { font-size: 20px; flex-shrink: 0; }

/* Info cards */
.info-card {
  display: flex; align-items: center; gap: var(--space-md);
  padding: var(--space-base); background: var(--color-bg-card);
  border-radius: var(--radius-lg); border: 1px solid var(--color-border);
  box-shadow: var(--shadow-sm);
}

.info-card-icon {
  width: 44px; height: 44px; border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

.info-card-content { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.info-card-label { font-size: var(--font-size-xs); color: var(--color-text-tertiary); font-weight: var(--font-weight-medium); text-transform: uppercase; letter-spacing: 0.5px; }
.info-card-value { font-size: var(--font-size-base); font-weight: var(--font-weight-medium); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Description */
.section-title {
  display: flex; align-items: center; gap: var(--space-sm);
  font-size: var(--font-size-md); font-weight: var(--font-weight-semibold);
  margin-bottom: var(--space-md);
}

.section-icon { font-size: 20px; color: var(--color-text-tertiary); }

.description-text {
  font-size: var(--font-size-base); color: var(--color-text-secondary);
  line-height: var(--line-height-relaxed); padding: var(--space-base);
  background: var(--color-bg-card); border-radius: var(--radius-md);
  border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);
}

/* Products */
.products-list { display: flex; flex-direction: column; gap: var(--space-sm); }

.product-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: var(--space-md) var(--space-base); background: var(--color-bg-card);
  border-radius: var(--radius-md); border: 1px solid var(--color-border);
}

.product-info { display: flex; align-items: center; gap: var(--space-sm); min-width: 0; flex: 1; }
.product-icon { font-size: 20px; color: var(--color-accent); flex-shrink: 0; }
.product-name { font-size: var(--font-size-sm); font-weight: var(--font-weight-medium); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.product-price { font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); color: var(--color-text-secondary); flex-shrink: 0; margin-left: var(--space-sm); }

.order-total {
  display: flex; justify-content: space-between; align-items: center;
  margin-top: var(--space-md); padding: var(--space-base);
  background: linear-gradient(135deg, rgba(0, 212, 170, 0.08), rgba(0, 102, 255, 0.08));
  border-radius: var(--radius-md); border: 1px solid rgba(0, 212, 170, 0.2);
}

.order-total-label { font-size: var(--font-size-sm); color: var(--color-text-secondary); font-weight: var(--font-weight-medium); }
.order-total-value { font-size: var(--font-size-lg); font-weight: var(--font-weight-bold); color: var(--color-accent); }

/* Result */
.result-card {
  padding: var(--space-base); background: var(--color-bg-card);
  border-radius: var(--radius-lg); border: 1px solid var(--color-border);
  display: flex; flex-direction: column; gap: var(--space-sm);
  box-shadow: var(--shadow-sm);
}

.result-status-badge {
  display: flex; align-items: center; gap: 6px;
  font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold);
}

.result-text { font-size: var(--font-size-sm); color: var(--color-text-secondary); line-height: var(--line-height-relaxed); }
.result-time { font-size: var(--font-size-xs); color: var(--color-text-tertiary); }

/* Map */
.map-preview { border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--color-border); }

.map-placeholder {
  height: 100px; background: linear-gradient(135deg, rgba(0, 196, 140, 0.08), rgba(0, 102, 255, 0.08));
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: var(--space-xs);
}

.map-pin { font-size: 32px; color: var(--color-success); }
.map-coords { font-size: var(--font-size-sm); color: var(--color-text-secondary); font-weight: var(--font-weight-medium); }

/* Comments */
.comment-item {
  display: flex; gap: var(--space-md); padding: var(--space-base);
  background: var(--color-bg-card); border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.comment-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: linear-gradient(135deg, var(--color-primary), var(--color-accent));
  display: flex; align-items: center; justify-content: center;
  font-size: var(--font-size-sm); font-weight: var(--font-weight-bold);
  color: white; flex-shrink: 0;
}

.comment-body { flex: 1; min-width: 0; }
.comment-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.comment-author { font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); }
.comment-date { font-size: var(--font-size-xs); color: var(--color-text-tertiary); }
.comment-text { font-size: var(--font-size-sm); color: var(--color-text-secondary); line-height: var(--line-height-normal); }

/* Actions */
.actions-section { display: flex; flex-direction: column; gap: var(--space-md); }

.action-btn {
  display: flex; align-items: center; justify-content: center; gap: var(--space-sm);
  padding: var(--space-base); border-radius: var(--radius-lg);
  font-size: var(--font-size-base); font-weight: var(--font-weight-semibold);
  text-decoration: none; transition: transform var(--transition-fast);
}

.action-btn:active { transform: scale(0.97); }
.action-btn-icon { font-size: 22px; }

.action-complete {
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  color: white; box-shadow: var(--shadow-glow-accent);
}

.action-comment {
  background: var(--color-bg-card); color: var(--color-text-primary);
  border: 1px solid var(--color-border);
}

.action-call {
  background: rgba(0, 196, 140, 0.08); color: var(--color-success);
  border: 1px solid rgba(0, 196, 140, 0.2);
}
</style>
