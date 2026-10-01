import { defineStore } from 'pinia'
import axios from 'axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('token') || null,
    userId: localStorage.getItem('user_id') || null,
    role: localStorage.getItem('role') || null,
  }),

  getters: {
    isLoggedIn: state => Boolean(state.token),
    isAdmin: state => state.role === 'admin',
  },

  actions: {
    async login(email, password) {
      const response = await axios.post('/login', { email, password })

      this.token = response.data.token
      this.userId = response.data.user_id
      this.role = response.data.role

      localStorage.setItem('token', this.token)
      localStorage.setItem('user_id', String(this.userId))
      localStorage.setItem('role', this.role)
    },

    async logout() {
      try {
        await axios.post('/logout')
      } catch {
        // A lokális állapotot akkor is töröljük, ha a token már lejárt.
      }

      this.token = null
      this.userId = null
      this.role = null

      localStorage.removeItem('token')
      localStorage.removeItem('user_id')
      localStorage.removeItem('role')
    },

    async register(username, email, password, passwordConfirm) {
      return axios.post('/register', {
        username,
        email,
        password,
        password_confirmation: passwordConfirm,
      })
    },
  },
})