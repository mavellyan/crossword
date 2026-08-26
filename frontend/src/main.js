import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import VueSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import Notifications from '@kyvg/vue3-notification'
import App from './App.vue'
import './styles/main.scss'
import axios from 'axios'
import 'bootstrap/dist/js/bootstrap.bundle.min.js'
import * as bootstrap from 'bootstrap'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowLeft, faArrowRight, faCheck, faTimes, faPlus, faMinus, faTrashCan } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

library.add(faArrowLeft, faArrowRight, faCheck, faTimes, faPlus, faMinus, faTrashCan)

axios.defaults.baseURL = 'http://localhost:8000/api'

axios.interceptors.request.use(config => {
    const token = localStorage.getItem('token')

    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }

    return config
})

const app = createApp(App)

app.directive('tooltip', {
    mounted(el, binding) {
        new bootstrap.Tooltip(el, {
            title: binding.value,
            trigger: 'binding.modifiers.hover' ? 'hover' : 'click',
        })
    },
    unmounted(el) {
        const tooltip = bootstrap.Tooltip.getInstance(el)
        if (tooltip) {
            tooltip.dispose()
        }
    }
})

app
    .use(createPinia())
    .use(router)
    .use(Notifications)
    .component('v-select', VueSelect)
    .component('font-awesome-icon', FontAwesomeIcon)
    .mount('#app')