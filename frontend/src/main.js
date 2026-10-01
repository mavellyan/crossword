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
import 'bootstrap-icons/font/bootstrap-icons.css'
import * as bootstrap from 'bootstrap'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowLeft, faArrowRight, faCheck, faTimes, faPlus, faMinus, faTrashCan, faSpinner, faTriangleExclamation, faFloppyDisk, faEye, faEyeSlash } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

library.add(faArrowLeft, faArrowRight, faCheck, faTimes, faPlus, faMinus, faTrashCan, faSpinner, faTriangleExclamation, faFloppyDisk, faEye, faEyeSlash)

axios.defaults.baseURL = import.meta.env.VITE_API_BASE_URL || '/api'

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
        let placement = 'top'
        if (binding.modifiers.bottom) placement = 'bottom'
        else if (binding.modifiers.left) placement = 'left'
        else if (binding.modifiers.right) placement = 'right'
        else if (binding.modifiers.top) placement = 'top'

        const trigger = binding.modifiers.hover ? 'hover' : 'click'

        new bootstrap.Tooltip(el, {
            title: binding.value,
            placement: placement,
            trigger: trigger,
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