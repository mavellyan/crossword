<template>
  <nav>
    <div class="left-side">
      <RouterLink class="nav-button" to="/">Főoldal</RouterLink>
      <RouterLink class="nav-button" to="/puzzlelist">Rejtvények</RouterLink>
    </div>
    <div class="right-side">
      <div v-if="auth.isLoggedIn">
        <button class="nav-button" @click="logout">Kijelentkezés</button>
      </div>
      <div v-else>
        <RouterLink class="nav-button" to="/login">Belépés</RouterLink>
        <RouterLink class="nav-button" to="/register">Regisztráció</RouterLink>
      </div>
    </div>
  </nav>
</template>

<script>
import { useAuthStore } from '@/stores/auth'

export default {
  name: 'NavBar',
  computed: {
    auth() {
      return useAuthStore()
    },
  },
  methods: {
    async logout() {
      await this.auth.logout()
      this.$router.push('/login')
    }
  }
}
</script>

<style lang="scss" scoped>
@import "@/styles/navBar.scss";
</style>