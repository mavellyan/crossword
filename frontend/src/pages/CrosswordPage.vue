<template>
  <div v-if="isCrosswordLoaded">
    <h1 class="text-center mb-2">
      {{ store.title }}
    </h1>
    <h5 class="text-center text-muted mb-5 fst-italic">
      Készítette: {{ store.creator }}
    </h5>
    <CrosswordGrid
      :words="store.words"
      :width="store.width"
    />
  </div>
  <div v-else class="text-center">
    <p v-if="store.loading">
      Betöltés...
    </p>
    <p v-else-if="store.error" class="alert alert-danger">
      {{ store.error }}
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
  data() {
    return {
      saveInterval: null,
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
    /**
     * Ellenőrzi, hogy a rejtvény adatai betöltődtek-e.
     * 
     * @returns {boolean} Igaz, ha a rejtvény adatai betöltődtek, hamis egyébként.
     */
    isCrosswordLoaded() {
      return (this.store.id !== null &&
        Array.isArray(this.store.words) &&
        !this.store.loading &&
        !this.store.error
      )
    },
  },
  watch: {
    id: {
      immediate: true,
      /**
       * Ha a route id változik, először elmentjük a jelenlegi rejtvény állapotát, majd betöltjük az új rejtvényt.
       * 
       * @param newId - Az új rejtvény id-je amit a route paraméterből kapunk.
       * @param oldId - A régi rejtvény id-je amit a route paraméterből kaptunk.
       * @returns {Promise<void>}
       */
      async handler(newId, oldId) {
        if (oldId && oldId !== newId) {
          await this.store.saveProgress()
        }

        await this.store.loadCrossword(newId)
      }
    },
  },
  mounted() {
    this.startAutoSave()
  },
  beforeUnmount() {
    this.stopAutoSave()
  },
  async beforeRouteLeave() {
    this.stopAutoSave()
    await this.store.saveProgress()
  },
  methods: {
    /**
     * Elindítja az automatikus mentést, ami 2 másodpercenként elmenti a rejtvény aktuális állapotát.
     * Ha már fut az automatikus mentés, nem történik semmi.
     */
    startAutoSave() {
      if (this.saveInterval !== null) {
        return
      }

      this.saveInterval = setInterval(() => {
        this.store.saveProgress()
      }, 2000)
    },
    /**
     * Leállítja az automatikus mentést. Ha nincs futó automatikus mentés, nem történik semmi.
     */
    stopAutoSave() {
      if (this.saveInterval === null) {
        return
      }

      clearInterval(this.saveInterval)
      this.saveInterval = null
    }
  }
}
</script>

<style scoped>
@import '../styles/crosswordPage.scss';
</style>