<template>
  <div class="container-fluid px-0">
    <div class="row g-0 align-items-center">
      <div class="col-md-6">
        <h1>Keresztrejtvény</h1>
        <div>
          Készen állsz?
        </div>
      </div>
      <div class="col-md-6 text-end">
        <img :src="rejtvenySrc" class="img-fluid" alt="Rejtveny kép"/>
      </div>
    </div>
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