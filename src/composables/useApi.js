// src/composables/useApi.js
// API-обёртка для вызовов к модулю rocada.visits с B24 access_token

import { useAuth } from './useAuth'
import { useRouter } from 'vue-router'

const API_BASE = import.meta.env.VITE_API_BASE_URL || '/b24api'

export function useApi() {
    const { token, clear } = useAuth()
    const router = useRouter()

    /**
     * GET-запрос к модулю API
     * @param {string} route — путь типа 'api/visits', 'api/config'
     * @param {Object} queryParams — параметры запроса
     */
    async function apiGet(route, queryParams = {}) {
        const qs = new URLSearchParams(queryParams).toString()
        const url = `${API_BASE}/${route}${qs ? '?' + qs : ''}`

        const res = await fetch(url, {
            method: 'GET',
            headers: buildHeaders(),
        })
        return handleResponse(res)
    }

    /**
     * POST-запрос к модулю API
     */
    async function apiPost(route, body = {}) {
        const res = await fetch(`${API_BASE}/${route}`, {
            method: 'POST',
            headers: { ...buildHeaders(), 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        })
        return handleResponse(res)
    }

    /**
     * DELETE-запрос к модулю API
     */
    async function apiDelete(route) {
        const res = await fetch(`${API_BASE}/${route}`, {
            method: 'DELETE',
            headers: buildHeaders(),
        })
        return handleResponse(res)
    }

    function buildHeaders() {
        const headers = {}
        if (token.value) {
            headers['Authorization'] = `Bearer ${token.value}`
        }
        return headers
    }

    async function handleResponse(res) {
        const text = await res.text()

        let json
        try {
            json = JSON.parse(text)
        } catch {
            console.error('[API] Не-JSON ответ:', text.slice(0, 300))
            throw new Error(`Ошибка сервера (${res.status}): получен не-JSON ответ`)
        }

        // Токен невалидный → разлогиниваем
        if (res.status === 401) {
            clear()
            router.push('/login')
            throw new Error('Сессия истекла. Войдите заново.')
        }

        if (!json.success) {
            throw new Error(json.error || `HTTP ${res.status}: Ошибка сервера`)
        }

        return json.data
    }

    return { apiGet, apiPost, apiDelete }
}
