<template>
  <div class="geo-page">
    <AppHeader title="Геолокация" :showBack="true" />

    <div class="page-content">
      <div class="geo-container">
        <!-- Map area -->
        <div class="geo-map animate-fade-in-up">
          <div class="map-visual">
            <div class="map-grid-bg">
              <div v-for="n in 30" :key="n" class="grid-cell"></div>
            </div>
            <div class="map-pin-container" :class="{ sent: isSent }">
              <div class="pin-pulse" v-if="!isSent"></div>
              <span class="material-symbols-rounded pin-icon" :class="{ sent: isSent }">
                {{ isSent ? 'check_circle' : 'location_on' }}
              </span>
            </div>
            <div class="map-radius" :class="{ active: isLocating }"></div>
          </div>
        </div>

        <!-- Coordinates info -->
        <div class="geo-info animate-fade-in-up" style="animation-delay: 150ms">
          <div class="coord-card">
            <div class="coord-icon">
              <span class="material-symbols-rounded">explore</span>
            </div>
            <div class="coord-data">
              <span class="coord-label">Широта</span>
              <span class="coord-value">{{ isLocating || isSent ? '55.7558' : '—' }}</span>
            </div>
          </div>
          <div class="coord-card">
            <div class="coord-icon">
              <span class="material-symbols-rounded">explore</span>
            </div>
            <div class="coord-data">
              <span class="coord-label">Долгота</span>
              <span class="coord-value">{{ isLocating || isSent ? '37.6173' : '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Status message -->
        <div class="geo-status animate-fade-in-up" style="animation-delay: 200ms">
          <div v-if="isSent" class="status-success">
            <span class="material-symbols-rounded success-icon">verified</span>
            <h3>Геолокация отправлена!</h3>
            <p>Координаты успешно обновлены в сделке</p>
          </div>
          <div v-else-if="isLocating" class="status-locating">
            <LogoSpinner size="medium" />
            <p>Определение местоположения...</p>
          </div>
          <div v-else class="status-idle">
            <span class="material-symbols-rounded idle-icon">my_location</span>
            <p>Нажмите кнопку для определения<br/>и отправки вашей геолокации</p>
          </div>
        </div>

        <!-- Action -->
        <div class="geo-action animate-fade-in-up" style="animation-delay: 300ms">
          <button
            v-if="!isSent"
            class="geo-btn"
            :class="{ locating: isLocating }"
            :disabled="isLocating"
            @click="sendGeo"
          >
            <span class="material-symbols-rounded geo-btn-icon">{{ isLocating ? 'gps_fixed' : 'send' }}</span>
            {{ isLocating ? 'Определение...' : 'Отправить геолокацию' }}
          </button>
          <router-link
            v-else
            :to="`/visits/${$route.params.id}`"
            class="geo-btn geo-btn-back"
          >
            <span class="material-symbols-rounded geo-btn-icon">arrow_back</span>
            Вернуться к визиту
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import AppHeader from '../components/AppHeader.vue'
import LogoSpinner from '../components/LogoSpinner.vue'

const isLocating = ref(false)
const isSent = ref(false)

function sendGeo() {
  isLocating.value = true
  setTimeout(() => {
    isLocating.value = false
    isSent.value = true
  }, 2000)
}
</script>

<style scoped>
.geo-page {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
}

.page-content {
  flex: 1;
  padding: var(--space-base);
  padding-bottom: calc(var(--bottom-nav-height) + var(--safe-area-bottom) + var(--space-xl));
}

@media (min-width: 768px) {
  .page-content {
    padding: var(--space-xl);
    padding-bottom: var(--space-xl);
  }
}

.geo-container {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

/* Map visual */
.geo-map {
  border-radius: var(--radius-xl);
  overflow: hidden;
  border: 1px solid var(--color-border);
}

.map-visual {
  height: 220px;
  background: linear-gradient(135deg, var(--color-bg-card), var(--color-bg-secondary));
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}

.map-grid-bg {
  position: absolute;
  inset: 0;
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  grid-template-rows: repeat(5, 1fr);
  pointer-events: none;
}

.grid-cell {
  border: 1px solid rgba(255,255,255,0.03);
}

.map-pin-container {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pin-pulse {
  position: absolute;
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: var(--color-primary-glow);
  animation: pulse 2s ease-in-out infinite;
}

.pin-icon {
  font-size: 48px;
  color: var(--color-danger);
  position: relative;
  z-index: 1;
  transition: all var(--transition-slow);
}

.pin-icon.sent {
  color: var(--color-success);
  font-size: 56px;
  animation: successBounce 0.5s ease forwards;
}

.map-radius {
  position: absolute;
  width: 120px;
  height: 120px;
  border-radius: 50%;
  border: 2px solid transparent;
  transition: all var(--transition-slow);
}

.map-radius.active {
  border-color: var(--color-primary);
  background: var(--color-primary-glow);
  animation: pulse 1.5s ease-in-out infinite;
}

/* Coordinates */
.geo-info {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-md);
}

.coord-card {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-base);
  background: var(--color-bg-card);
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
}

.coord-icon {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  background: rgba(0, 102, 255, 0.12);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-primary);
}

.coord-data {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.coord-label {
  font-size: var(--font-size-xs);
  color: var(--color-text-tertiary);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.coord-value {
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-semibold);
  font-variant-numeric: tabular-nums;
}

/* Status */
.geo-status {
  text-align: center;
  padding: var(--space-lg);
}

.status-success {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-sm);
  animation: fadeInUp 0.4s ease;
}

.success-icon {
  font-size: 48px;
  color: var(--color-success);
  animation: successBounce 0.5s ease;
}

.status-success h3 {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-bold);
  color: var(--color-success);
}

.status-success p {
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
}

.status-locating {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-base);
}

/* spinner replaced with LogoSpinner component */

.status-locating p, .status-idle p {
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  line-height: var(--line-height-relaxed);
}

.status-idle {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-sm);
}

.idle-icon {
  font-size: 40px;
  color: var(--color-text-tertiary);
}

/* Button */
.geo-btn {
  width: 100%;
  height: 52px;
  border-radius: var(--radius-lg);
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
  color: white;
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-semibold);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  transition: transform var(--transition-fast), box-shadow var(--transition-fast), opacity var(--transition-fast);
  box-shadow: var(--shadow-glow-accent);
  text-decoration: none;
}

.geo-btn:active:not(:disabled) {
  transform: scale(0.97);
}

.geo-btn:disabled {
  opacity: 0.7;
}

.geo-btn.locating {
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  box-shadow: none;
}

.geo-btn-back {
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  box-shadow: none;
  color: var(--color-text-primary);
}

.geo-btn-icon {
  font-size: 22px;
}
</style>
