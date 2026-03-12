<template>
  <div class="app">
    <NavBar />
    <router-view />
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
}

main {
  display: flex;
  flex: 1;
}
</style>