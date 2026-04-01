// src/composables/useAuth.js
// Авторизация через Битрикс24 OAuth2 — без нашего серверного модуля

import { ref, computed } from 'vue'

// ── Константы из .env ──────────────────────────────────────────────────────
const CLIENT_ID = import.meta.env.VITE_B24_CLIENT_ID || ''
const CLIENT_SECRET = import.meta.env.VITE_B24_CLIENT_SECRET || ''
const REDIRECT = import.meta.env.VITE_B24_REDIRECT_URI || (window.location.origin + '/auth/callback')

// ── Хранилище ─────────────────────────────────────────────────────────────
const STORAGE_TOKEN = 'rocadamed_token'
const STORAGE_USER = 'rocadamed_user'

// ── Реактивное состояние ──────────────────────────────────────────────────
const token = ref(localStorage.getItem(STORAGE_TOKEN) || null)
const user = ref(JSON.parse(localStorage.getItem(STORAGE_USER) || 'null'))

export function useAuth() {
  const isAuthenticated = computed(() => !!token.value)
  const hasOAuth = computed(() => !!CLIENT_ID)

  // ── Сохранение ──────────────────────────────────────────────────────────
  function persist(accessToken, userData) {
    token.value = accessToken
    user.value = userData
    localStorage.setItem(STORAGE_TOKEN, accessToken)
    localStorage.setItem(STORAGE_USER, JSON.stringify(userData))
    localStorage.setItem('rocadamed_auth', 'true')
  }

  // ── Очистка ─────────────────────────────────────────────────────────────
  function clear() {
    token.value = null
    user.value = null
    localStorage.removeItem(STORAGE_TOKEN)
    localStorage.removeItem(STORAGE_USER)
    localStorage.removeItem('rocadamed_auth')
  }

  // ── ① OAuth2: редирект на страницу входа Б24 ────────────────────────────
  function loginOAuth() {
    if (!CLIENT_ID) {
      throw new Error('OAuth не настроен: VITE_B24_CLIENT_ID не задан')
    }
    // Очищаем старые токены — иначе роутер пускает на главную без авторизации
    clear()
    const params = new URLSearchParams({
      client_id: CLIENT_ID,
      response_type: 'code',
      redirect_uri: REDIRECT,
    })
    window.location.href = `https://office.rocadatech.ru/oauth/authorize/?${params}`
  }

  // ── ② OAuth2 callback: code → access_token (через Vite proxy, без CORS) ─
  async function handleOAuthCallback(code) {
    if (!CLIENT_ID) throw new Error('OAuth не настроен')

    // Шаг 1: Битрикс24 принимает token request через GET (не POST)
    const tokenParams = new URLSearchParams({
      grant_type: 'authorization_code',
      client_id: CLIENT_ID,
      client_secret: CLIENT_SECRET,
      code,
      redirect_uri: REDIRECT,
    })
    const tokenRes = await fetch(`/b24oauth/token/?${tokenParams}`)

    // Читаем как текст — если вернули HTML (форму), даём понятную ошибку
    const rawText = await tokenRes.text()
    let tokenData
    try {
      tokenData = JSON.parse(rawText)
    } catch {
      const preview = rawText.slice(0, 500).replace(/\s+/g, ' ')
      console.error('[OAuth] Token endpoint вернул не-JSON:', preview)
      throw new Error(
        `Ошибка обмена кода на токен (HTTP ${tokenRes.status}).\n` +
        `Ответ сервера: ${preview || '(пусто)'}`
      )
    }

    if (!tokenData.access_token) {
      const err = tokenData.error_description || tokenData.error || 'Неизвестная ошибка'
      throw new Error('OAuth ошибка: ' + err)
    }

    const accessToken = tokenData.access_token

    // Шаг 2: профиль пользователя через прокси /b24rest → office.rocadatech.ru/rest
    const profileRes = await fetch(`/b24rest/user.current.json?auth=${accessToken}`)
    const profileData = await profileRes.json()

    if (!profileData.result) {
      throw new Error('Не удалось получить профиль пользователя')
    }

    const p = profileData.result
    const userData = {
      id: p.ID,
      fullName: [p.LAST_NAME, p.NAME].filter(Boolean).join(' '),
      firstName: p.NAME || '',
      lastName: p.LAST_NAME || '',
      email: p.EMAIL || '',
      phone: p.PERSONAL_PHONE || '',
      position: p.WORK_POSITION || '',
      photo: p.PERSONAL_PHOTO || null,
    }

    persist(accessToken, userData)
    return { token: accessToken, user: userData }
  }

  // ── ③ Выход ──────────────────────────────────────────────────────────────
  function logout() {
    clear()
  }

  return {
    token,
    user,
    isAuthenticated,
    hasOAuth,
    loginOAuth,
    handleOAuthCallback,
    logout,
    clear,
  }
}
