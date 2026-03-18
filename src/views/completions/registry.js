// src/views/completions/registry.js
// Реестр типов завершения визита.
// Чтобы добавить новый тип — создай компонент и добавь одну строку сюда.

export const COMPLETION_REGISTRY = {
  /** Продажи: выбор статуса → смена стадии сделки */
  sales: () => import('./SalesResultView.vue'),
  /** Сервис: статус (опционально) + обязательная загрузка фото в UF-поля */
  service: () => import('./ServiceResultView.vue'),
}

/**
 * Динамически загружает компонент завершения по типу.
 * @param {string} type - completion_type из конфига направления
 * @returns {Promise<Component>}
 */
export function getCompletionComponent(type) {
  const loader = COMPLETION_REGISTRY[type] ?? COMPLETION_REGISTRY['sales']
  return loader()
}
