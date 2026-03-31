// src/composables/useVisits.js
// Загрузка визитов, деталей сделок, статистики из модуля rocada.visits

import { ref, computed } from 'vue'
import { useApi } from './useApi'

// Глобальный кэш — общий для всех компонентов
const visitsToday = ref([])
const visitsTomorrow = ref([])
const visitsCompleted = ref([])
const stats = ref(null)
const config = ref(null)
const loading = ref(false)
const error = ref(null)
const successCount = ref(0)
const failCount = ref(0)

// Пагинация для каждого периода
const pagination = ref({
    today: { page: 0, pages: 1, total: 0, loaded: false },
    tomorrow: { page: 0, pages: 1, total: 0, loaded: false },
    completed: { page: 0, pages: 1, total: 0, loaded: false },
})

export function useVisits() {
    const api = useApi()

    // ── Загрузка первой страницы визитов ───────────────────────────────
    async function loadVisits(force = false) {
        if (pagination.value.today.loaded && !force) return
        loading.value = true
        error.value = null
        try {
            // Сброс
            visitsToday.value = []
            visitsTomorrow.value = []
            visitsCompleted.value = []
            pagination.value.today = { page: 0, pages: 1, total: 0, loaded: false }
            pagination.value.tomorrow = { page: 0, pages: 1, total: 0, loaded: false }
            pagination.value.completed = { page: 0, pages: 1, total: 0, loaded: false }

            // Загружаем первые страницы параллельно
            await Promise.all([
                loadNextPage('today'),
                loadNextPage('tomorrow'),
                loadNextPage('completed'),
            ])

            pagination.value.today.loaded = true
            pagination.value.tomorrow.loaded = true
            pagination.value.completed.loaded = true
        } catch (e) {
            error.value = e.message
            console.error('[useVisits] loadVisits error:', e)
        } finally {
            loading.value = false
        }
    }

    // ── Загрузка следующей страницы ────────────────────────────────────
    async function loadNextPage(period = 'today') {
        const p = pagination.value[period]
        if (p.page >= p.pages) return // всё загружено

        const perPage = 50
        const nextPage = p.page + 1

        // Берём текущее направление — динамический импорт чтобы избежать circular dep
        let dirId = null
        try {
            const { useDirections } = await import('./useDirections')
            const { currentDirection } = useDirections()
            dirId = currentDirection.value?.id || null
        } catch { /* нет направлений — работаем без фильтра */ }

        try {
            const params = { period, per_page: perPage, page: nextPage }
            if (dirId) params.direction = dirId

            const data = await api.apiGet('api/visits', params)

            const items = data.items || []
            if (period === 'today') {
                visitsToday.value = [...visitsToday.value, ...items.filter(i => !visitsToday.value.some(v => v.id === i.id))]
            } else if (period === 'tomorrow') {
                visitsTomorrow.value = [...visitsTomorrow.value, ...items.filter(i => !visitsTomorrow.value.some(v => v.id === i.id))]
            } else if (period === 'completed') {
                visitsCompleted.value = [...visitsCompleted.value, ...items.filter(i => !visitsCompleted.value.some(v => v.id === i.id))]
            }

            pagination.value[period] = {
                ...p,
                page: nextPage,
                pages: data.pages || 1,
                total: data.total || 0,
            }

            // Сохраняем счётчики успешных/провальных только из запроса "today"
            // (запрос "tomorrow" тоже возвращает 0 и обнулял бы значения)
            if (period === 'today') {
                if (data.success_count !== undefined) successCount.value = data.success_count
                if (data.fail_count !== undefined) failCount.value = data.fail_count
            }
        } catch (e) {
            console.error(`[useVisits] loadNextPage(${period}) error:`, e)
        }
    }

    // Есть ли ещё страницы
    function hasMore(period = 'today') {
        const p = pagination.value[period]
        return p.page < p.pages
    }

    // ── Загрузка деталей одного визита ────────────────────────────────────
    async function loadVisitDetail(id) {
        try {
            return await api.apiGet(`api/visits/${id}`)
        } catch (e) {
            console.error('[useVisits] loadVisitDetail error:', e)
            throw e
        }
    }

    // ── Статистика ────────────────────────────────────────────────────────
    async function loadStats() {
        try {
            stats.value = await api.apiGet('api/stats')
        } catch (e) {
            console.error('[useVisits] loadStats error:', e)
        }
    }

    // ── Конфигурация модуля ───────────────────────────────────────────────
    async function loadConfig() {
        try {
            config.value = await api.apiGet('api/config')
        } catch (e) {
            console.error('[useVisits] loadConfig error:', e)
        }
    }

    // ── Отправка геолокации ───────────────────────────────────────────────
    async function sendGeo(dealId, lat, lng) {
        return api.apiPost(`api/visits/${dealId}/geo`, { lat, lng })
    }

    // ── Добавление комментария ────────────────────────────────────────────
    async function addComment(dealId, text) {
        return api.apiPost(`api/visits/${dealId}/comment`, { text })
    }

    // ── Получение комментариев ────────────────────────────────────────────
    async function loadComments(dealId) {
        return api.apiGet(`api/visits/${dealId}/comment`)
    }

    // ── Результат визита ──────────────────────────────────────────────────
    async function submitResult(dealId, resultData) {
        return api.apiPost(`api/visits/${dealId}/result`, resultData)
    }

    // ── Загрузка файлов ───────────────────────────────────────────────────
    async function uploadFiles(dealId, files, type = 'photo') {
        const formData = new FormData()
        for (const file of files) {
            formData.append('files[]', file)
        }
        formData.append('type', type)
        // Используем fetch напрямую для FormData
        const { token } = await import('./useAuth').then(m => m.useAuth())
        const res = await fetch(`/b24api/api/visits/${dealId}/files`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token.value}` },
            body: formData,
        })
        const data = await res.json()
        if (!data.success) throw new Error(data.error || 'Ошибка загрузки файлов')
        return data.data
    }

    // ── Планирование визита ───────────────────────────────────────────────
    async function fetchUnloadPoints(dealId) {
        return api.apiGet(`api/visits/${dealId}/unload-points`)
    }

    async function planVisitApi(dealId, visitData) {
        return api.apiPost(`api/visits/${dealId}/plan`, visitData)
    }

    // ── Инфоповоды ────────────────────────────────────────────────────────
    async function fetchInfopovods(dealId) {
        return api.apiGet(`api/visits/${dealId}/infopovods`)
    }

    async function submitInfopovods(dealId, items) {
        return api.apiPost(`api/visits/${dealId}/infopovods`, { items })
    }

    // ── Computed ──────────────────────────────────────────────────────────
    const todayCount = computed(() => visitsToday.value.length)
    const tomorrowCount = computed(() => visitsTomorrow.value.length)
    const todayTotal = computed(() => pagination.value.today.total)
    const tomorrowTotal = computed(() => pagination.value.tomorrow.total)
    const completedToday = computed(() => successCount.value)

    // Найти визит по id в любом из списков
    function findVisit(id) {
        const numId = Number(id)
        return visitsToday.value.find(v => v.id === numId)
            || visitsTomorrow.value.find(v => v.id === numId)
            || visitsCompleted.value.find(v => v.id === numId)
            || null
    }

    function reset() {
        visitsToday.value = []
        visitsTomorrow.value = []
        visitsCompleted.value = []
        stats.value = null
        config.value = null
        pagination.value.today = { page: 0, pages: 1, total: 0, loaded: false }
        pagination.value.tomorrow = { page: 0, pages: 1, total: 0, loaded: false }
        pagination.value.completed = { page: 0, pages: 1, total: 0, loaded: false }
        successCount.value = 0
        failCount.value = 0
    }

    return {
        // Данные
        visitsToday,
        visitsTomorrow,
        visitsCompleted,
        stats,
        config,
        loading,
        error,
        pagination,

        // Вычисляемые
        todayCount,
        tomorrowCount,
        todayTotal,
        tomorrowTotal,
        completedTotal: computed(() => pagination.value.completed.total),
        completedToday,
        successCount,
        failCount,

        // Методы
        loadVisits,
        loadNextPage,
        hasMore,
        loadVisitDetail,
        loadStats,
        loadConfig,
        sendGeo,
        addComment,
        loadComments,
        submitResult,
        uploadFiles,
        fetchUnloadPoints,
        planVisitApi,
        fetchInfopovods,
        submitInfopovods,
        findVisit,
        reset,
    }
}
