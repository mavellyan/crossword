<template>
  <div v-if="isCrosswordLoaded">
    <h1 class="text-center mb-2">
      {{ store.title }}
    </h1>
    <h5 class="text-center text-muted mb-5 fst-italic">
      Készítette: {{ store.creator }}
    </h5>
    <CrosswordGrid
      :grid="store.grid"
      :main-solution="store.mainSolution"
      :words="store.words"
      :width="store.width"
      :height="store.height"
    />
  </div>
  <div v-else class="text-center">
    <p v-if="store.loading">
      Betöltés...
    </p>
    <p v-else-if="store.error" class="alert alert-danger">
      Hiba történt!
    </p>
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
    /**
     * Elérhetővé teszi a crossword Pinia store-t az oldal számára.
     *
     * @returns {import('../stores/crossword').useCrosswordStore}
     */
    store() {
      return useCrosswordStore()
    },
    isCrosswordLoaded() {
      return this.store.grid !== null && !this.store.loading && !this.store.error
    },
  },
  watch: {
    id: {
      immediate: true,
      /**
       * Újratölti a rejtvény adatait, amikor változik a route id.
       *
       * @param {string} newId Az új route paraméter érték.
       * @returns {void}
       */
      handler(newId) {
        this.store.loadCrossword(newId)
      },
    },
  },
}
</script>

<style scoped>
@import '../styles/crosswordPage.scss';
</style>