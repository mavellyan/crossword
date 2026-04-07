<template>
  <div class="app">
    <NavBar />
    <main class="d-flex flex-fill">
      <router-view />
    </main>
  </div>
</template>

<script>
import NavBar from '@/components/NavBar.vue'

export default {
  components: {
    NavBar
  }
}
</script>

<style lang="scss"> 
.app {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  z-index: 1;
}

main {
  z-index: 1;
}
</style>