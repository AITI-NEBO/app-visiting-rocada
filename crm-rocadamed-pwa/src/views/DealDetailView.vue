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
          <span class="hero-id">#{{ visit.id }}</span>
          <span class="hero-time">
            <span class="material-symbols-rounded" style="font-size:16px">schedule</span>
            {{ visit.time }} — {{ visit.endTime }}
          </span>
        </div>
      </div>

      <!-- Info cards -->
      <div class="info-section animate-fade-in-up" style="animation-delay: 100ms">
        <div class="info-card">
          <div class="info-card-icon" style="background: rgba(0, 212, 170, 0.12)">
            <span class="material-symbols-rounded" style="color: var(--color-accent)">person</span>
          </div>
          <div class="info-card-content">
            <span class="info-card-label">Пациент</span>
            <span class="info-card-value">{{ visit.client }}</span>
          </div>
        </div>

        <div class="info-card">
          <div class="info-card-icon" style="background: rgba(0, 102, 255, 0.12)">
            <span class="material-symbols-rounded" style="color: var(--color-primary)">location_on</span>
          </div>
          <div class="info-card-content">
            <span class="info-card-label">Адрес</span>
            <span class="info-card-value">{{ visit.address }}</span>
          </div>
        </div>

        <div class="info-card">
          <div class="info-card-icon" style="background: rgba(124, 58, 237, 0.12)">
            <span class="material-symbols-rounded" style="color: #A78BFA">phone</span>
          </div>
          <div class="info-card-content">
            <span class="info-card-label">Телефон</span>
            <span class="info-card-value">{{ visit.phone }}</span>
          </div>
        </div>
      </div>

      <!-- Description -->
      <div class="description-section animate-fade-in-up" style="animation-delay: 200ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">description</span>
          Описание
        </h3>
        <p class="description-text">{{ visit.description }}</p>
      </div>

      <!-- Map preview -->
      <div v-if="visit.latitude" class="map-section animate-fade-in-up" style="animation-delay: 250ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">map</span>
          Геолокация отправлена
        </h3>
        <div class="map-preview">
          <div class="map-placeholder">
            <span class="material-symbols-rounded map-pin">location_on</span>
            <div class="map-coords">
              <span>{{ visit.latitude?.toFixed(4) }}, {{ visit.longitude?.toFixed(4) }}</span>
            </div>
            <div class="map-grid">
              <div v-for="n in 20" :key="n" class="grid-line"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Comments preview -->
      <div v-if="visit.comments.length" class="comments-section animate-fade-in-up" style="animation-delay: 300ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded section-icon">chat</span>
          Комментарии ({{ visit.comments.length }})
        </h3>
        <div class="comment-item" v-for="(comment, i) in visit.comments" :key="i">
          <div class="comment-avatar">
            <span>{{ comment.author[0] }}</span>
          </div>
          <div class="comment-body">
            <div class="comment-header">
              <span class="comment-author">{{ comment.author }}</span>
              <span class="comment-date">{{ comment.date }}</span>
            </div>
            <p class="comment-text">{{ comment.text }}</p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="actions-section animate-fade-in-up" style="animation-delay: 350ms">
        <router-link :to="`/visits/${visit.id}/geo`" class="action-btn action-geo" v-if="!visit.latitude">
          <span class="material-symbols-rounded action-btn-icon">location_on</span>
          Отправить геолокацию
        </router-link>

        <router-link :to="`/visits/${visit.id}/comment`" class="action-btn action-comment">
          <span class="material-symbols-rounded action-btn-icon">edit_note</span>
          Написать комментарий
        </router-link>

        <button class="action-btn action-file" v-if="visit.description">
          <span class="material-symbols-rounded action-btn-icon">file_download</span>
          Скачать описание
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import { visits, visitsTomorrow, getStatusLabel, getStatusColor } from '../data/mock'

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
    pending: 'rgba(255, 255, 255, 0.05)'
  }
  return map[visit.value.status] || 'rgba(255,255,255,0.05)'
})
</script>

<style scoped>
.deal-page {
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

/* Hero */
.deal-hero {
  text-align: center;
  padding: var(--space-lg) 0;
}

.hero-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: var(--radius-full);
  margin-bottom: var(--space-md);
}

.hero-status-dot {
  width: 8px;
  height: 8px;
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
  margin-bottom: var(--space-sm);
}

.hero-meta {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-base);
  color: var(--color-text-secondary);
  font-size: var(--font-size-sm);
}

.hero-time {
  display: flex;
  align-items: center;
  gap: 4px;
}

/* Info cards */
.info-section {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.info-card {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-base);
  background: var(--color-bg-card);
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
}

.info-card-icon {
  width: 44px;
  height: 44px;
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
  font-weight: var(--font-weight-medium);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-card-value {
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-medium);
  color: var(--color-text-primary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Description */
.section-title {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-semibold);
  margin-bottom: var(--space-md);
}

.section-icon {
  font-size: 20px;
  color: var(--color-text-tertiary);
}

.description-text {
  font-size: var(--font-size-base);
  color: var(--color-text-secondary);
  line-height: var(--line-height-relaxed);
  padding: var(--space-base);
  background: var(--color-bg-card);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

/* Map */
.map-preview {
  border-radius: var(--radius-lg);
  overflow: hidden;
  border: 1px solid var(--color-border);
}

.map-placeholder {
  height: 140px;
  background: linear-gradient(135deg, var(--color-bg-card), var(--color-bg-elevated));
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  position: relative;
  overflow: hidden;
}

.map-pin {
  font-size: 36px;
  color: var(--color-danger);
  animation: float 2s ease-in-out infinite;
}

.map-coords {
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  font-weight: var(--font-weight-medium);
}

.map-grid {
  position: absolute;
  inset: 0;
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  grid-template-rows: repeat(4, 1fr);
  pointer-events: none;
}

.grid-line {
  border: 1px solid rgba(255,255,255,0.03);
}

/* Comments */
.comment-item {
  display: flex;
  gap: var(--space-md);
  padding: var(--space-base);
  background: var(--color-bg-card);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.comment-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--color-primary), var(--color-accent));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-bold);
  color: white;
  flex-shrink: 0;
}

.comment-body {
  flex: 1;
  min-width: 0;
}

.comment-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 4px;
}

.comment-author {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-semibold);
}

.comment-date {
  font-size: var(--font-size-xs);
  color: var(--color-text-tertiary);
}

.comment-text {
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  line-height: var(--line-height-normal);
}

/* Actions */
.actions-section {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
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
  transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.action-btn:active {
  transform: scale(0.97);
}

.action-btn-icon {
  font-size: 22px;
}

.action-geo {
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  color: white;
  box-shadow: var(--shadow-glow-accent);
}

.action-comment {
  background: var(--color-bg-card);
  color: var(--color-text-primary);
  border: 1px solid var(--color-border);
}

.action-file {
  background: var(--color-bg-card);
  color: var(--color-text-secondary);
  border: 1px solid var(--color-border);
}
</style>
