<template>
  <nav>
    <div class="left-side">
      <RouterLink class="nav-button" to="/">Főoldal</RouterLink>
      <RouterLink class="nav-button" to="/puzzlelist">Rejtvények</RouterLink>
    </div>
    <div v-if="isLoggedIn" class="right-side">
      <RouterLink class="nav-button" to="/">Kijelentkezés</RouterLink>
    </div>
    <div v-else class="right-side">
      <RouterLink class="nav-button" to="/login">Belépés</RouterLink>
      <RouterLink class="nav-button" to="/register">Regisztráció</RouterLink>
    </div>
  </nav>
</template>

<script>
export default {
  name: 'NavBar',
  data() {
    return {
      isLoggedIn: false
    }
  }
}
</script>

<style lang="scss" scoped>
@import "@/styles/navBar.scss";
</style>