import { defineStore } from 'pinia'
import axios from 'axios'

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem('token') || null,
        userId: localStorage.getItem('user_id') || null,
    }),

    getters: {
        isLoggedIn: (state) => !!state.token
    },

    actions: {
        async login(email, password) {
            const response = await axios.post('/login', { email, password })

            this.token = response.data.token
            this.userId = response.data.user_id
            localStorage.setItem('token', this.token)
            localStorage.setItem('user_id', this.userId)
        },

        async logout() {
            try {
                await axios.post('/logout')
            } catch (e) { }

            this.token = null
            this.userId = null
            localStorage.removeItem('token')
            localStorage.removeItem('user_id')
        },

        async register(username, email, password, passwordConfirm) {
            const response = await axios.post('/register', { username, email, password, password_confirmation: passwordConfirm })
            return response
        },
    }
})