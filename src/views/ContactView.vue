<template>
  <div class="entity-page">
    <AppHeader :title="fullName || 'Контакт'" :showBack="true" />

    <div class="page-content" v-if="!loading && !error">
      <div class="entity-card animate-fade-in-up">
        <div class="entity-avatar">{{ initials }}</div>
        <h2 class="entity-name">{{ fullName }}</h2>
        <p v-if="contact?.POST" class="entity-meta">{{ contact.POST }}</p>
        <p v-if="contact?.COMPANY_TITLE" class="entity-meta">
          <span class="material-symbols-rounded" style="font-size:14px">business</span>
          {{ contact.COMPANY_TITLE }}
        </p>
      </div>

      <div class="info-section animate-fade-in-up" style="animation-delay:60ms">
        <div class="info-row" v-for="phone in (contact?.PHONE || [])" :key="phone.VALUE">
          <span class="info-label">{{ phone.VALUE_TYPE === 'WORK' ? 'Рабочий' : 'Телефон' }}</span>
          <a :href="`tel:${phone.VALUE}`" class="info-value info-link">{{ phone.VALUE }}</a>
        </div>
        <div class="info-row" v-for="email in (contact?.EMAIL || [])" :key="email.VALUE">
          <span class="info-label">Email</span>
          <a :href="`mailto:${email.VALUE}`" class="info-value info-link">{{ email.VALUE }}</a>
        </div>
        <template v-for="field in extraFields" :key="field.code">
          <div class="info-row" v-if="contact?.[field.code]">
            <span class="info-label">{{ field.label }}</span>
            <span class="info-value">{{ contact[field.code] }}</span>
          </div>
        </template>
      </div>

      <!-- Компания контакта -->
      <router-link v-if="contact?.COMPANY_ID" :to="`/company/${contact.COMPANY_ID}`" class="company-link animate-fade-in-up" style="animation-delay:100ms">
        <span class="material-symbols-rounded">business</span>
        <span>{{ contact.COMPANY_TITLE || 'Перейти к компании' }}</span>
        <span class="material-symbols-rounded" style="color:var(--color-text-tertiary);margin-left:auto">chevron_right</span>
      </router-link>
    </div>

    <div v-else-if="loading" class="loading-state">
      <span class="material-symbols-rounded spin">progress_activity</span>
      <p>Загрузка…</p>
    </div>

    <div v-else-if="error" class="error-state">
      <span class="material-symbols-rounded">error</span>
      <p>{{ error }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import { useApi } from '../composables/useApi'

const route    = useRoute()
const api      = useApi()
const contact  = ref(null)
const loading  = ref(true)
const error    = ref('')
const extraFields = ref([])

const fullName = computed(() => {
  if (!contact.value) return ''
  return [contact.value.NAME, contact.value.LAST_NAME].filter(Boolean).join(' ')
})
const initials = computed(() => {
  if (!contact.value) return '?'
  return ((contact.value.NAME?.[0] || '') + (contact.value.LAST_NAME?.[0] || '')).toUpperCase() || '?'
})

onMounted(async () => {
  try {
    const data = await api.apiGet(`api/clients/${route.params.id}`, { type: 'contact' })
    contact.value   = data.contact  ?? data
    extraFields.value = data.extra_fields ?? []
  } catch (e) {
    error.value = e.message || 'Ошибка загрузки'
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.entity-page { display: flex; flex-direction: column; min-height: 100dvh; }
.page-content { flex: 1; padding: var(--space-base); padding-bottom: calc(var(--bottom-nav-height) + var(--safe-area-bottom) + var(--space-xl)); display: flex; flex-direction: column; gap: var(--space-base); }

.entity-card { display: flex; flex-direction: column; align-items: center; gap: var(--space-sm); background: var(--color-bg-card); border-radius: var(--radius-xl); padding: var(--space-xl); border: 1px solid var(--color-border); }
.entity-avatar { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, var(--color-primary), var(--color-accent)); display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: var(--font-weight-bold); color: white; }
.entity-name { font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); text-align: center; }
.entity-meta { display: flex; align-items: center; gap: 4px; font-size: var(--font-size-sm); color: var(--color-text-secondary); text-align: center; }

.info-section { background: var(--color-bg-card); border-radius: var(--radius-xl); padding: var(--space-base); border: 1px solid var(--color-border); }
.info-row { display: flex; justify-content: space-between; align-items: center; padding: var(--space-sm) 0; border-bottom: 1px solid var(--color-border); gap: var(--space-base); }
.info-row:last-child { border-bottom: none; }
.info-label { font-size: var(--font-size-sm); color: var(--color-text-secondary); flex-shrink: 0; }
.info-value { font-size: var(--font-size-sm); font-weight: var(--font-weight-medium); text-align: right; }
.info-link { color: var(--color-primary); text-decoration: none; }

.company-link { display: flex; align-items: center; gap: var(--space-md); background: var(--color-bg-card); border-radius: var(--radius-xl); padding: var(--space-base); border: 1px solid var(--color-border); text-decoration: none; color: inherit; font-size: var(--font-size-base); font-weight: var(--font-weight-medium); }
.company-link .material-symbols-rounded:first-child { color: var(--color-primary); }

.loading-state, .error-state { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: var(--space-md); color: var(--color-text-secondary); }
.loading-state .material-symbols-rounded { font-size: 40px; }
.error-state .material-symbols-rounded { font-size: 40px; color: var(--color-danger); }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
