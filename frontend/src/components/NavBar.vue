<template>
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm px-3 py-2">
    <div class="container-fluid">
      <RouterLink class="navbar-brand fw-bold d-lg-none" to="/">
        Lexora
      </RouterLink>

      <button
        class="navbar-toggler border-0"
        type="button"
        @click="isMenuOpen = !isMenuOpen"
        :aria-expanded="isMenuOpen"
        aria-label="Menü megnyitása"
      >
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" :class="{ show: isMenuOpen }">
        <div class="navbar-nav me-auto gap-lg-2 pt-2 pt-lg-0">
          <RouterLink class="nav-link" to="/" @click="closeMenu">Főoldal</RouterLink>
          <RouterLink class="nav-link" to="/crosswordlist" @click="closeMenu">Rejtvények</RouterLink>
          <RouterLink v-if="auth.isLoggedIn" class="nav-link" to="/create" @click="closeMenu">
            Rejtvény létrehozása
          </RouterLink>
          <RouterLink v-if="auth.isAdmin" class="nav-link" to="/admin" @click="closeMenu">
            Admin panel
          </RouterLink>
        </div>

        <hr class="d-lg-none text-white-50 my-2" />

        <div class="navbar-nav ms-auto align-items-lg-center gap-2 pb-2 pb-lg-0">
          <template v-if="auth.isLoggedIn">
            <RouterLink class="nav-link me-lg-2" to="/profile" @click="closeMenu">
              Profil
            </RouterLink>
            <button class="btn btn-outline-light btn-sm px-3 py-2 py-lg-1 text-start text-lg-center" @click="logout">
              Kijelentkezés
            </button>
          </template>
          <template v-else>
            <RouterLink class="nav-link" to="/login" @click="closeMenu">Belépés</RouterLink>
            <RouterLink class="btn btn-light text-primary fw-semibold btn-sm px-3 py-2 py-lg-1 mt-1 mt-lg-0" to="/register" @click="closeMenu">
              Regisztráció
            </RouterLink>
          </template>
        </div>
      </div>
    </div>
  </nav>
</template>

<script>
import { useAuthStore } from '@/stores/auth'

export default {
  name: 'NavBar',
  data() {
    return {
      isMenuOpen: false,
    }
  },
  computed: {
    auth() {
      return useAuthStore()
    },
  },
  methods: {
    closeMenu() {
      this.isMenuOpen = false
    },
    async logout() {
      await this.auth.logout()
      this.$router.push('/login')
    }
  }
}
</script>

<style lang="scss" scoped>
.nav-link {
  color: rgba(255, 255, 255, 0.85);
  border-radius: 0.375rem;
  padding: 0.5rem 0.75rem;
  transition: all 0.2s ease-in-out;

  &:hover {
    color: #ffffff;
    background-color: rgba(255, 255, 255, 0.12);
  }

  &.router-link-exact-active {
    color: #ffffff;
    font-weight: 600;
    background-color: rgba(255, 255, 255, 0.2);
  }
}

@media (max-width: 991.98px) {
  .btn {
    width: 100%;
    text-align: center !important;
  }
}
</style>