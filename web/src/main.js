import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './styles/tokens.scss'
import './styles/main.scss'
import App from './App.vue'
import router from './router/index.js'

createApp(App).use(createPinia()).use(router).mount('#app')
