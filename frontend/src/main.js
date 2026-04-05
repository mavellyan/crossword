import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import './styles/main.scss'
import axios from 'axios'

axios.defaults.baseURL = 'http://localhost:8000/api'

axios.interceptors.request.use(config => {
    const token = localStorage.getItem('token')

    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }

    return config
})

createApp(App).use(createPinia()).use(router).mount('#app')