<template>
  <div class="main-content">
    <h1>Keresztrejtvény</h1>
    <div>
      Készen állsz?
    </div>
  </div>
  <div  class="home-img">
    <img :src="rejtvenySrc" alt="Rejtveny kép">
  </div>
</template>

<script>
import RejtvenyKep from '@/assets/rejtvenykep-no-bg-final-v2.png'

export default {
  name: 'HomePage',
  data() {
    return {
      rejtvenySrc: RejtvenyKep
    }
  }
}
</script>

<style lang="scss" scoped>
@import "@/styles/home.scss";
</style>