import { defineStore } from 'pinia'
import axios from 'axios'

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem('token') || null,
    }),

    getters: {
        isLoggedIn: (state) => !!state.token
    },

    actions: {
        async login(email, password) {
            const response = await axios.post('/login', { email, password })

            this.token = response.data.token
            localStorage.setItem('token', this.token)
        },

        async logout() {
            try {
                await axios.post('/logout')
            } catch (e) {}

            this.token = null
            localStorage.removeItem('token')
        },

        async register(username, email, password, passwordConfirm) {
            const response = await axios.post('/register', { username, email, password, password_confirmation: passwordConfirm })
            return response
        },
    }
})