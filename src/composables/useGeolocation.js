/**
 * Геолокация для завершения визита.
 *
 * checkGeoAccess()  — есть ли у приложения доступ к геолокации (шаг перед итогом визита);
 * getSaveLocation() — местоположение в момент нажатия «Сохранить итог».
 */

export function geoErrorText(err) {
  if (!navigator.geolocation) return 'Телефон или браузер не поддерживает геолокацию'
  if (err?.code === 1) return 'Нет доступа к геолокации'
  if (err?.code === 2) return 'Геолокация на телефоне выключена или недоступна'
  if (err?.code === 3) return 'Телефон не успел определить местоположение'
  return 'Не удалось получить местоположение'
}

function getPosition(options) {
  return new Promise(resolve => {
    navigator.geolocation.getCurrentPosition(
      pos => resolve({
        ok: true,
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
        accuracy: pos.coords.accuracy,
      }),
      err => resolve({ ok: false, code: err?.code ?? 0, error: geoErrorText(err) }),
      options
    )
  })
}

/**
 * Проверка доступа к геолокации.
 * @returns {Promise<'granted'|'denied'|'unavailable'|'unsupported'>}
 *   granted     — доступ есть, можно идти дальше;
 *   denied      — доступ запрещён (в браузере или в настройках телефона);
 *   unavailable — геолокация на телефоне выключена;
 *   unsupported — браузер не умеет геолокацию.
 */
export async function checkGeoAccess() {
  if (!navigator.geolocation) return 'unsupported'

  // Если браузер сразу говорит, что доступ разрешён — не ждём GPS
  try {
    const st = await navigator.permissions?.query({ name: 'geolocation' })
    if (st?.state === 'granted') return 'granted'
  } catch { /* Permissions API есть не везде — проверяем запросом */ }

  // Запрос местоположения заодно покажет системный вопрос о разрешении
  const res = await getPosition({ enableHighAccuracy: false, timeout: 15000, maximumAge: 120000 })
  if (res.ok) return 'granted'
  if (res.code === 1) return 'denied'
  if (res.code === 2) return 'unavailable'
  // Таймаут: доступ есть, просто телефон долго ищет спутники — не держим сотрудника
  return 'granted'
}

/**
 * Местоположение в момент сохранения визита: сначала точное, потом примерное.
 * @returns {Promise<{ok:true, lat, lng, accuracy} | {ok:false, error}>}
 */
export async function getSaveLocation() {
  if (!navigator.geolocation) return { ok: false, error: geoErrorText(null) }
  const precise = await getPosition({ enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 })
  if (precise.ok || precise.code === 1) return precise
  return getPosition({ enableHighAccuracy: false, timeout: 10000, maximumAge: 120000 })
}
