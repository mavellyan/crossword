<template>
  <div class="register-page d-flex flex-fill align-items-center justify-content-center w-100">
    <div v-if="generalError !== null" class="alert alert-danger">{{ generalError }}</div>
    <div class="card shadow p-4 form-width">
      <h1 class="text-center text-uppercase mb-3 title">Regisztráció</h1>
      <form @submit.prevent="onSubmit">

        <div class="mb-2">
          <label for="username" class="form-label">Felhasználónév</label>
          <input
            id="username"
            v-model="username"
            type="text"
            class="form-control"
            :class="{ 'is-invalid': fieldErrors.username }"
          />
          <p v-if="fieldErrors.username" class="invalid-feedback">{{ fieldErrors.username }}</p>
        </div>

        <div class="mb-2">
          <label for="email" class="form-label">Email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            class="form-control"
            :class="{ 'is-invalid': fieldErrors.email }"
          />
          <p v-if="fieldErrors.email" class="invalid-feedback">{{ fieldErrors.email }}</p>
        </div>

        <div class="mb-2">
          <label for="password" class="form-label">Jelszó</label>
          <input
            id="password"
            v-model="password"
            type="password"
            class="form-control"
            :class="{ 'is-invalid': fieldErrors.password }"
          />
          <p v-if="fieldErrors.password" class="invalid-feedback">{{ fieldErrors.password }}</p>
        </div>

        <div class="mb-3">
          <label for="password-confirm" class="form-label">Jelszó megerősítése</label>
          <input
            id="password-confirm"
            v-model="passwordConfirm"
            type="password"
            class="form-control"
            :class="{ 'is-invalid': fieldErrors.passwordConfirm }"
          />
          <p v-if="fieldErrors.passwordConfirm" class="invalid-feedback">{{ fieldErrors.passwordConfirm }}</p>
        </div>

        <button type="submit" class="btn btn-primary w-100">Regisztráció</button>
      </form>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth'

export default {
  name: 'RegisterPage',
  data() {
    return {
      username: '',
      email: '',
      password: '',
      passwordConfirm: '',
      fieldErrors: {},
      generalError: null,
    }
  },
  setup() {
    const auth = useAuthStore()

    return {
      auth
    }
  },
  methods: {
    /**
     * Regisztrációs api meghívása
     */
    async onSubmit() {
      this.fieldErrors = {}
      this.generalError = null

      if (!this.validateForm()) {
        return
      }

      try {
        await this.auth.register(
          this.username.trim(),
          this.email.trim(),
          this.password.trim(),
          this.passwordConfirm.trim()
        )
        this.$router.push('/login')
      } catch (error) {
        if (error.response) {
          if (error.response.status === 422) {
            const errors = error.response.data.errors

            if (errors.username !== undefined) {
              this.fieldErrors.username = errors.username[0]
            }

            if (errors.email !== undefined) {
              this.fieldErrors.email = errors.email[0]
            }

            if (errors.password !== undefined) {
              if (errors.password[0].includes('nem egyeznek')) {
                this.fieldErrors.passwordConfirm = errors.password[0]
              } else {
                this.fieldErrors.password = errors.password[0]
              }
            }

          } else if (error.response.status === 500) {
            this.generalError = 'Szerverhiba történt.'
          } else {
            this.generalError = error.response.data.message || 'Ismeretlen hiba történt'
          }
        } else {
          this.generalError = 'Nem sikerült kapcsolódni a szerverhez.'
        }
      }
    },
    /**
     * Regisztrációs adatok validálása
     */
    validateForm() {
      if (this.password.trim() !== this.passwordConfirm.trim()) {
        this.fieldErrors.passwordConfirm = 'A jelszavak nem egyeznek!'
      }

      if (this.password.trim().length < 6) {
        this.fieldErrors.password = 'A jelszónak legalább 6 karakter hosszúnak kell lennie!'
      }

      if (this.passwordConfirm.trim().length < 6) {
        this.fieldErrors.passwordConfirm = 'A jelszónak legalább 6 karakter hosszúnak kell lennie!'
      }

      if (this.username.trim().length < 5) {
        this.fieldErrors.username = 'A felhasználónévnek legalább 5 karakter hosszúnak kell lennie!'
      }

      if (!this.validateEmail(this.email)) {
        this.fieldErrors.email = 'Érvénytelen email cím!'
      }

      if (this.username.trim() === '') {
        this.fieldErrors.username = 'Mező kitöltése kötelező'
      }

      if (this.password.trim() === '') {
        this.fieldErrors.password = 'Mező kitöltése kötelező'
      }

      if (this.passwordConfirm.trim() === '') {
        this.fieldErrors.passwordConfirm = 'Mező kitöltése kötelező'
      }

      if (this.email.trim() === '') {
        this.fieldErrors.email = 'Mező kitöltése kötelező'
      }

      if (Object.keys(this.fieldErrors).length > 0) {
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
  },
}
</script>
