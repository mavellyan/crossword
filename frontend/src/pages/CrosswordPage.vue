<template>
  <h1>Rejtvény</h1>
  <div>
    <CrosswordGrid
      v-if="store.grid !== null"
      :grid="store.grid"
      :main_solution="store.mainSolution"
      :words="store.words"
      :width="store.width"
      :height="store.height"
    />
  </div>
</template>

<script>
import { useCrosswordStore } from '../stores/crossword';
import CrosswordGrid from '../components/CrosswordGrid.vue';

export default {
  name: 'CrosswordPage',
  components: {
    CrosswordGrid,
  },
  props: {
    id: {
      type: String,
      required: true
    }
  },
  computed: {
    store() {
      return useCrosswordStore()
    }
  },
  mounted() {
    this.store.loadCrossword(this.id)
  },
}
</script>

<style scoped>
@import '../styles/crosswordPage.scss';
</style>