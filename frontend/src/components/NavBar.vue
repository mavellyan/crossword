<template>
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary px-3">
    <div class="navbar-nav">
      <RouterLink class="nav-link" to="/">Főoldal</RouterLink>
      <RouterLink class="nav-link" to="/crosswordlist">Rejtvények</RouterLink>
      <RouterLink v-if="auth.isLoggedIn" class="nav-link" to="/create">Rejtvény létrehozása</RouterLink>
      <RouterLink v-if="auth.isAdmin" class="nav-link" to="/admin">Admin panel</RouterLink>
    </div>
    <div class="navbar-nav ms-auto">
      <template v-if="auth.isLoggedIn">
          <RouterLink class="nav-link mx-3" to="/profile">Profil</RouterLink>
          <button class="btn btn-outline-light" @click="logout">Kijelentkezés</button>
      </template>
      <template v-else>
        <RouterLink class="nav-link" to="/login">Belépés</RouterLink>
        <RouterLink class="nav-link" to="/register">Regisztráció</RouterLink>
      </template>
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
.nav-link {
    color: white;
}
</style>