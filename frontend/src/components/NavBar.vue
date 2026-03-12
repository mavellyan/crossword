<template>
  <nav>
    <RouterLink class="nav-button" to="/">Főoldal</RouterLink>
    <RouterLink class="nav-button" to="/puzzlelist">Rejtvények</RouterLink>
    <div class="right-side">
      <RouterLink class="nav-button" to="/login">Belépés</RouterLink>
      <RouterLink class="nav-button" to="/register">Regisztráció</RouterLink>
    </div>
  </nav>
</template>

<style lang="scss" scoped>
@import "@/styles/navbar.scss";
</style>