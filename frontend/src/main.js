import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { watch } from 'vue'
import router from './router'
import App from './App.vue'
import './style.css'
import { useSettingsStore } from './stores/settings'
import { applyThemeToDom } from './lib/theme'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia).use(router)

const settings = useSettingsStore(pinia)
settings.init()

watch(
  () => [settings.mode, settings.accent],
  () => applyThemeToDom({ mode: settings.mode, accent: settings.accent }),
  { immediate: true }
)

app.mount('#app')
