<template>
  <div class="login-page">
    <div class="form-wrapper">
      <h1 class="title">Belépés</h1>
      <form @submit.prevent="onSubmit">
        <div class="form-row">
          <label for="email">Email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            :class="{ 'field-error': isInputWrong('invalid-login') }"
          />
          <p v-if="showErrorMessage('invalid-login')" class="error-message">{{ showErrorMessage('invalid-login') }}</p>
        </div>
        <div class="form-row">
          <label for="password">Jelszó</label>
          <input
            id="password"
            v-model="password"
            type="password"
            :class="{ 'field-error': isInputWrong('invalid-login') }"
          />
          <p v-if="showErrorMessage('invalid-login')" class="error-message">{{ showErrorMessage('invalid-login') }}</p>
        </div>
        <div class="form-row">
          <button type="submit">Belépés</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'LoginPage',
  data() {
    return {
      email: '',
      password: '',
      fieldErrors: [],
    }
  },
  methods: {
    onSubmit() {
      this.fieldErrors = []

      if (!this.validateForm()) {
        return
      }

      axios.post('/api/login', {
        email: this.email.trim(),
        password: this.password.trim(),
      }).then(response => {
        console.log(response)
      }).catch(error => {
        console.error(error)
      })

      const msg = "Belépés sikeres! Adatok: \n" +
        "Email: " + this.email + "\n" +
        "Password: " + this.password

      //window.alert(msg)
      //this.$router.push('/')
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
