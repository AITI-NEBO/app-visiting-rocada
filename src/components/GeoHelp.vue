<template>
  <div class="geo-help">
    <p class="gh-title">
      <span class="material-symbols-rounded">help</span>
      Как включить геолокацию
    </p>

    <ol v-if="platform === 'ios'" class="gh-steps">
      <li>Откройте на телефоне приложение «Настройки».</li>
      <li>Зайдите в «Конфиденциальность и безопасность» → «Службы геолокации» и включите переключатель.</li>
      <li>В этом же списке найдите «{{ iosBrowser }}» и выберите «При использовании». Включите «Точная геопозиция».</li>
      <li>Вернитесь сюда и нажмите кнопку определения местоположения.</li>
    </ol>

    <ol v-else-if="platform === 'android'" class="gh-steps">
      <li>Проведите сверху вниз по экрану и включите «Местоположение» (может называться «Геоданные» или «GPS»).</li>
      <li>Если сайт открыт в браузере: нажмите значок слева от адреса сайта → «Разрешения» → включите «Местоположение».</li>
      <li>Если приложение открыто со значка на экране: удерживайте значок → «О приложении» → «Разрешения» → «Местоположение» → «Разрешить».</li>
      <li>Вернитесь сюда и нажмите кнопку определения местоположения.</li>
    </ol>

    <ol v-else class="gh-steps">
      <li>Включите геолокацию в настройках устройства.</li>
      <li>Разрешите этому сайту доступ к местоположению: значок слева от адреса сайта → «Местоположение» → «Разрешить».</li>
      <li>Вернитесь сюда и нажмите кнопку определения местоположения.</li>
    </ol>
  </div>
</template>

<script setup>
const ua = navigator.userAgent || ''
const isIos = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)

const platform = isIos ? 'ios' : /Android/.test(ua) ? 'android' : 'other'

// На iPhone разрешение настраивается отдельно для каждого браузера
const iosBrowser = /CriOS/.test(ua) ? 'Chrome' : /YaBrowser/.test(ua) ? 'Яндекс' : 'Сайты Safari'
</script>

<style scoped>
.geo-help { width: 100%; padding: var(--space-sm) var(--space-base); border-radius: var(--radius-md); background: rgba(255, 170, 0, 0.1); text-align: left; }
.gh-title { display: flex; align-items: center; gap: 6px; font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); color: var(--color-text-primary); }
.gh-title .material-symbols-rounded { font-size: 18px; }
.gh-steps { margin: 6px 0 0; padding-left: 20px; display: flex; flex-direction: column; gap: 4px; font-size: var(--font-size-xs); color: var(--color-text-secondary); }
</style>
