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
    /**
     * Elérhetővé teszi a crossword Pinia store-t az oldal számára.
     *
     * @returns {import('../stores/crossword').useCrosswordStore}
     */
    store() {
      return useCrosswordStore()
    }
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