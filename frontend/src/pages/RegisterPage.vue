<template>
  <div class="register-page">
    <div v-if="generalError !== null">{{ generalError }}</div>
    <div class="form-wrapper">
      <h1 class="title">Regisztráció</h1>
      <form @submit.prevent="onSubmit">
        <div class="form-row">
          <label for="username">Felhasználónév</label>
          <input
            id="username"
            v-model="username"
            type="text"
            :class="{ 'field-error': fieldErrors.username }"
          />
          <p v-if="fieldErrors.username" class="error-message">{{ fieldErrors.username }}</p>
        </div>
        <div class="form-row">
          <label for="email">Email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            :class="{ 'field-error': fieldErrors.email }"
          />
          <p v-if="fieldErrors.email" class="error-message">{{ fieldErrors.email }}</p>
        </div>
        <div class="form-row">
          <label for="password">Jelszó</label>
          <input
            id="password"
            v-model="password"
            type="password"
            :class="{ 'field-error': fieldErrors.password }"
          />
          <p v-if="fieldErrors.password" class="error-message">{{ fieldErrors.password }}</p>
        </div>
        <div class="form-row">
          <label for="password-confirm">Jelszó megerősítése</label>
          <input
            id="password-confirm"
            v-model="passwordConfirm"
            type="password"
            :class="{ 'field-error': fieldErrors.passwordConfirm }"
          />
          <p v-if="fieldErrors.passwordConfirm" class="error-message">{{ fieldErrors.passwordConfirm }}</p>
        </div>
        <div class="form-row">
          <button type="submit">Regisztráció</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

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
  methods: {
    /**
     * TODO: Regisztrációs api meghívása
     */
    onSubmit() {
      this.fieldErrors = {}
      this.generalError = null

      if (!this.validateForm()) {
        return
      }

      axios.post('/api/register', {
        username: this.username.trim(),
        email: this.email.trim(),
        password: this.password.trim(),
        password_confirmation: this.passwordConfirm.trim()
      }).then(() => {
        this.$router.push('/login')
      }).catch(error => {
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
      })
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
