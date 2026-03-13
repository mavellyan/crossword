<template>
  <div class="register-page">
    <div class="form-wrapper">
      <h1 class="title">Regisztráció</h1>
      <form @submit.prevent="onSubmit">
        <div class="form-row">
          <label for="username">Felhasználónév</label>
          <input
            id="username"
            v-model="username"
            type="text"
            :class="{ 'field-error': isInputWrong('username') }"
          />
          <p v-if="showErrorMessage('username')" class="error-message">{{ showErrorMessage('username') }}</p>
        </div>
        <div class="form-row">
          <label for="email">Email</label>
          <input
            id="email"
            v-model="email"
            :class="{ 'field-error': isInputWrong('email') }"
          />
          <p v-if="showErrorMessage('email')" class="error-message">{{ showErrorMessage('email') }}</p>
        </div>
        <div class="form-row">
          <label for="password">Jelszó</label>
          <input
            id="password"
            v-model="password"
            type="password"
            :class="{ 'field-error': isInputWrong('password') }"
          />
          <p v-if="showErrorMessage('password')" class="error-message">{{ showErrorMessage('password') }}</p>
        </div>
        <div class="form-row">
          <label for="password-confirm">Jelszó megerősítése</label>
          <input
            id="password-confirm"
            v-model="passwordConfirm"
            type="password"
            :class="{ 'field-error': isInputWrong('passwordConfirm') }"
          />
          <p v-if="showErrorMessage('passwordConfirm')" class="error-message">{{ showErrorMessage('passwordConfirm') }}</p>
        </div>
        <button type="submit">Regisztráció</button>
      </form>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'RegisterPage',
  data() {
    return {
      username: '',
      email: '',
      password: '',
      passwordConfirm: '',
      fieldErrors: [],
    }
  },
  methods: {
    /**
     * TODO: Regisztrációs api meghívása
     */
    onSubmit() {
      this.fieldErrors = []

      if (!this.validateForm()) {
        console.log(this.fieldErrors)
        return
      }

      axios.post('/api/register', {
        username: this.username,
        email: this.email,
        password: this.password,
        password_confirmation: this.passwordConfirm
      }).then(response => {
        console.log(response.data)
        //this.$router.push('/login')
      }).catch(error => {
        console.error(error)
      })
    },
    /**
     * Regisztrációs adatok validálása
     */
    validateForm() {
      if (this.password.trim() !== this.passwordConfirm.trim()) {
        this.fieldErrors.push({
          field: 'passwordConfirm',
          message: 'A jelszavak nem egyeznek!'
        })
      }

      if (this.password.trim().length < 6) {
        this.fieldErrors.push({
          field: 'password',
          message: 'A jelszónak legalább 6 karakter hosszúnak kell lennie!'
        })
      }

      if (this.passwordConfirm.trim().length < 6) {
        this.fieldErrors.push({
          field: 'passwordConfirm',
          message: 'A jelszónak legalább 6 karakter hosszúnak kell lennie!'
        })
      }

      if (this.username.trim().length < 5) {
        this.fieldErrors.push({
          field: 'username',
          message: 'A felhasználónévnek legalább 5 karakter hosszúnak kell lennie!'
        })
      }

      if (!this.validateEmail(this.email)) {
        this.fieldErrors.push({
          field: 'email',
          message: 'Érvénytelen email cím!'
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
  },
}
</script>