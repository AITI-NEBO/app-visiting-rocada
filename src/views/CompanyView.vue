<template>
  <div class="entity-page">
    <AppHeader :title="company?.TITLE || 'Компания'" :showBack="true" />

    <div class="page-content" v-if="!loading && !error">
      <div class="entity-card animate-fade-in-up">
        <!-- Аватар / иконка -->
        <div class="entity-avatar">
          <span class="material-symbols-rounded">business</span>
        </div>
        <h2 class="entity-name">{{ company?.TITLE }}</h2>
        <p v-if="company?.ADDRESS" class="entity-meta">
          <span class="material-symbols-rounded" style="font-size:14px">location_on</span>
          {{ company.ADDRESS }}
        </p>
      </div>

      <div class="info-section animate-fade-in-up" style="animation-delay:60ms">
        <div class="info-row" v-if="company?.PHONE?.length">
          <span class="info-label">Телефон</span>
          <a :href="`tel:${company.PHONE[0]?.VALUE}`" class="info-value info-link">
            {{ company.PHONE[0]?.VALUE }}
          </a>
        </div>
        <div class="info-row" v-if="company?.EMAIL?.length">
          <span class="info-label">Email</span>
          <a :href="`mailto:${company.EMAIL[0]?.VALUE}`" class="info-value info-link">
            {{ company.EMAIL[0]?.VALUE }}
          </a>
        </div>
        <div class="info-row" v-if="company?.WEBSITE">
          <span class="info-label">Сайт</span>
          <a :href="company.WEBSITE" target="_blank" class="info-value info-link">{{ company.WEBSITE }}</a>
        </div>
        <!-- Доп. поля из конфига -->
        <template v-for="field in extraFields" :key="field.code">
          <div class="info-row" v-if="company?.[field.code]">
            <span class="info-label">{{ field.label }}</span>
            <span class="info-value">{{ company[field.code] }}</span>
          </div>
        </template>
      </div>

      <!-- Контакты компании -->
      <div v-if="contacts?.length" class="section-card animate-fade-in-up" style="animation-delay:100ms">
        <h3 class="section-title">
          <span class="material-symbols-rounded">contacts</span>
          Контакты
        </h3>
        <div class="contact-list">
          <router-link
            v-for="c in contacts"
            :key="c.ID"
            :to="`/contact/${c.ID}`"
            class="contact-item"
          >
            <div class="contact-avatar">{{ getInitials(c.NAME, c.LAST_NAME) }}</div>
            <div class="contact-info">
              <div class="contact-name">{{ c.NAME }} {{ c.LAST_NAME }}</div>
              <div class="contact-phone" v-if="c.PHONE?.[0]">{{ c.PHONE[0].VALUE }}</div>
            </div>
            <span class="material-symbols-rounded" style="color:var(--color-text-tertiary)">chevron_right</span>
          </router-link>
        </div>
      </div>
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
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import { useApi } from '../composables/useApi'

const route   = useRoute()
const api     = useApi()
const company  = ref(null)
const contacts = ref([])
const loading  = ref(true)
const error    = ref('')
const extraFields = ref([])

onMounted(async () => {
  try {
    const data = await api.apiGet(`api/clients/${route.params.id}`, { type: 'company' })
    company.value   = data.company  ?? data
    contacts.value  = data.contacts ?? []
    extraFields.value = data.extra_fields ?? []
  } catch (e) {
    error.value = e.message || 'Ошибка загрузки'
  } finally {
    loading.value = false
  }
})

function getInitials(name, lastName) {
  return ((name?.[0] || '') + (lastName?.[0] || '')).toUpperCase() || '?'
}
</script>

<style scoped>
.entity-page { display: flex; flex-direction: column; min-height: 100dvh; }
.page-content { flex: 1; padding: var(--space-base); padding-bottom: calc(var(--bottom-nav-height) + var(--safe-area-bottom) + var(--space-xl)); display: flex; flex-direction: column; gap: var(--space-base); }

.entity-card { display: flex; flex-direction: column; align-items: center; gap: var(--space-sm); background: var(--color-bg-card); border-radius: var(--radius-xl); padding: var(--space-xl); border: 1px solid var(--color-border); }
.entity-avatar { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, var(--color-primary), var(--color-accent)); display: flex; align-items: center; justify-content: center; }
.entity-avatar .material-symbols-rounded { font-size: 36px; color: white; }
.entity-name { font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); text-align: center; }
.entity-meta { display: flex; align-items: center; gap: 4px; font-size: var(--font-size-sm); color: var(--color-text-secondary); }

.info-section { background: var(--color-bg-card); border-radius: var(--radius-xl); padding: var(--space-base); border: 1px solid var(--color-border); }
.info-row { display: flex; justify-content: space-between; align-items: center; padding: var(--space-sm) 0; border-bottom: 1px solid var(--color-border); gap: var(--space-base); }
.info-row:last-child { border-bottom: none; }
.info-label { font-size: var(--font-size-sm); color: var(--color-text-secondary); flex-shrink: 0; }
.info-value { font-size: var(--font-size-sm); font-weight: var(--font-weight-medium); text-align: right; }
.info-link { color: var(--color-primary); text-decoration: none; }

.section-card { background: var(--color-bg-card); border-radius: var(--radius-xl); padding: var(--space-base); border: 1px solid var(--color-border); }
.section-title { display: flex; align-items: center; gap: var(--space-sm); font-size: var(--font-size-md); font-weight: var(--font-weight-semibold); margin-bottom: var(--space-base); }
.section-title .material-symbols-rounded { font-size: 20px; color: var(--color-primary); }
.contact-list { display: flex; flex-direction: column; gap: var(--space-sm); }
.contact-item { display: flex; align-items: center; gap: var(--space-md); padding: var(--space-sm); border-radius: var(--radius-md); background: var(--color-bg-elevated); text-decoration: none; color: inherit; }
.contact-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--color-accent), var(--color-primary)); display: flex; align-items: center; justify-content: center; font-size: var(--font-size-sm); font-weight: var(--font-weight-bold); color: white; flex-shrink: 0; }
.contact-info { flex: 1; }
.contact-name { font-size: var(--font-size-sm); font-weight: var(--font-weight-medium); }
.contact-phone { font-size: var(--font-size-xs); color: var(--color-text-secondary); }

.loading-state, .error-state { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: var(--space-md); color: var(--color-text-secondary); }
.loading-state .material-symbols-rounded { font-size: 40px; }
.error-state .material-symbols-rounded { font-size: 40px; color: var(--color-danger); }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
