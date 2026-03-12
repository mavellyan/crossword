<template>
  <div class="register-page">
    <h1 class="title">Regisztráció</h1>
    <div class="form-wrapper">
      <form @submit.prevent="onSubmit">
        <label for="username">Felhasználónév</label>
        <input id="username" v-model="username" type="text" required />
        <label for="email">Email</label>
        <input id="email" v-model="email" type="email" required />
        <label for="password">Jelszó</label>
        <input id="password" v-model="password" type="password" required />
        <button type="submit">Regisztráció</button>
      </form>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RegisterPage',
  data() {
    return {
      username: '',
      email: '',
      password: ''
    }
  },
  methods: {
    onSubmit() {
      const msg = "Regisztráció sikeres! Adatok: \n" +
        "Username: " + this.username + "\n" +
        "Email: " + this.email + "\n" +
        "Password: " + this.password

      window.alert(msg)
      this.$router.push('/')
    }
  }
}
</script>