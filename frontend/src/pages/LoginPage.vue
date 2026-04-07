<template>
  <div class="login-page d-flex flex-fill align-items-center justify-content-center w-100">
    <div class="card shadow p-4 form-width">
      <h1 class="text-center text-uppercase mb-3 title">Belépés</h1>
      <form @submit.prevent="onSubmit">
        <p v-if="showErrorMessage('general')" class="alert alert-danger">{{ showErrorMessage('general') }}</p>
        <p v-if="showErrorMessage('invalid-login')" class="alert alert-danger">{{  showErrorMessage('invalid-login') }}</p>

        <div class="mb-3">
          <label for="email" class="form-label">Email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            class="form-control"
            :class="{ 'is-invalid': isInputWrong('invalid-login') }"
          />
        </div>

        <div class="mb-3">
          <label for="password" class="form-label">Jelszó</label>
          <input
            id="password"
            v-model="password"
            type="password"
            class="form-control"
            :class="{ 'is-invalid': isInputWrong('invalid-login') }"
          />
        </div>

        <button type="submit" class="btn btn-primary w-100">Belépés</button>
      </form>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth'

export default {
  name: 'LoginPage',
  data() {
    return {
      email: '',
      password: '',
      fieldErrors: [],
    }
  },
  setup() {
    const auth = useAuthStore()

    return {
      auth
    }
  },
  methods: {
    async onSubmit() {
      this.fieldErrors = []

      if (!this.validateForm()) {
        return
      }

      try {
        await this.auth.login(this.email.trim(), this.password.trim())
        this.$router.push('/')
      } catch (error) {
        if (error.response.status === 401) {
          this.fieldErrors.push({
            field: 'invalid-login',
            message: 'Hibás email cím vagy jelszó!'
          })
        } else {
          this.fieldErrors.push({
            field: 'general',
            message: 'Hiba történt a bejelentkezés során. Kérlek próbáld újra később!'
          })
        }
      }
    },
    /**
     * Bejelentkezési adatok validálása
     *
     * @returns {boolean}
     */
    validateForm() {
      if (this.password.trim().length === 0 || !this.validateEmail(this.email)) {
        this.fieldErrors.push({
          field: 'invalid-login',
          message: 'Hibás email cím vagy jelszó!'
        })
      }

      if (this.fieldErrors.length > 0) {
        return false
      }

      return true
    },
    /**
     * Email cím validálása
     *
     * @param email
     * @returns boolean
     */
    validateEmail(email) {
      const regex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/
      return regex.test(email.trim())
    },
    /**
     * Ellenőrzi, hogy a megadott mező hibás-e, piros körvonallal kiemeli ha igen
     *
     * @param field mező neve (username, email, password, passwordConfirm)
     * @returns boolean
     */
    isInputWrong(field) {
      return this.fieldErrors.some(error => error.field === field)
    },
    /**
     * Visszaadja a megadott mezőhöz tartozó hibaüzenetet, ha van
     *
     * @param field mező neve (username, email, password, passwordConfirm)
     * @returns string hibaüzenet vagy üres string, ha nincs hiba
     */
    showErrorMessage(field) {
      const error = this.fieldErrors.find(error => error.field === field)
      return error ? error.message : ''
    }
  }
}
</script>
