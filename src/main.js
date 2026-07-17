import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import './assets/styles/variables.css'
import './assets/styles/global.css'

const app = createApp(App)
app.use(router)
app.mount('#app')

// Пока шрифт иконок не загружен, лигатуры скрыты (см. global.css).
// Показываем иконки только после загрузки шрифта — иначе на медленной сети
// вместо иконок виден сырой текст ("home", "map" и т.д.).
function markIconsReady() {
    document.documentElement.classList.add('fonts-icons-ready')
}
if (document.fonts?.load) {
    Promise.race([
        document.fonts.load('24px "Material Symbols Rounded"'),
        new Promise(resolve => setTimeout(resolve, 3000)), // страховка: не держим иконки скрытыми дольше 3с
    ]).then(markIconsReady).catch(markIconsReady)
} else {
    markIconsReady()
}
