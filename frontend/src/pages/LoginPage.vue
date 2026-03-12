<template>
  <div class="login-page">
    <h1 class="title">Belépés</h1>
    <div class="form-wrapper">
      <form @submit.prevent="onSubmit">
        <label for="email">Email</label>
        <input id="email" v-model="email" type="email" required />
        <label for="password">Jelszó</label>
        <input id="password" v-model="password" type="password" required />
        <button type="submit">Belépés</button>
      </form>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LoginPage',
  data() {
    return {
      email: '',
      password: ''
    }
  },
  methods: {
    onSubmit() {
      const msg = "Belépés sikeres! Adatok: \n" +
        "Email: " + this.email + "\n" +
        "Password: " + this.password

      window.alert(msg)
      this.$router.push('/')
    }
  }
}
</script>