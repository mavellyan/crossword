<template>
  <div v-if="isCrosswordLoaded">
    <h1 class="text-center mb-2">
      {{ store.title }}
    </h1>
    <h5 class="text-center text-muted mb-5 fst-italic">
      Készítette: {{ store.creator }}
    </h5>
    <CrosswordGrid
      v-if="store.words !== null"
      :words="store.words"
      :width="store.width"
      :is-crossword-resetting="isCrosswordResetting"
      @start-game="startGame()"
      @abandon-attempt="abandonAttempt()"
    />
    <div class="text-center mt-4">
      Időmérő
      <div>
        {{  displayTime  }}
      </div>
    </div>
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
      /**
       * Jelzi, hogy a rejtvény játék elindult-e. Ha igaz, akkor az időzítő fut.
       * 
       * @type {boolean}
       */
      isCrosswordStarted: false,
      /**
       * Az időzítő intervallum azonosítója, ami minden másodpercben frissíti az eltelt időt. Ha null, akkor nincs futó időzítő.
       */
      timerInterval: null,
      /**
       * A timerTick változó minden másodpercben növekszik, így a displayTime computed property újraszámolódik.
       * 
       * @type {number}
       */
      timerTick: 0,
      /**
       * Jelzi, hogy a próbálkozás megállt-e. Ha igaz, akkor a játék leállt és a felhasználó nem tudja folytatni.
       * Azért van rá szükség, hogy ne próbáljuk meg 2x leállítani a próbálkozást a visibilitychange miatt.
       * 
       * @type {boolean}
       */
      isAttemptStopped: true,
      /**
       * Jelzi, hogy a rejtvény visszaállítása folyamatban van-e. Ha igaz, akkor a felhasználó nem tudja újraindítani a játékot.
       * 
       * @type {boolean}
       */
      isCrosswordResetting: false,
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
    /**
     * Formázza az eltelt időt órákra, percekre és másodpercekre.
     *
     * @returns {string} Az eltelt idő formázott stringként (HH:MM:SS).
     */
    displayTime() {
      this.timerTick // Timer ticket használjuk, hogy a computed property újraszámolódjon minden másodpercben.

      let totalSeconds = this.store.elapsedTime

      console.log('totalsec ', totalSeconds)

      if (this.store.startedAt) {
        const startedAt = new Date(this.store.startedAt)
        const elapsedSinceStart = Math.floor((Date.now() - startedAt.getTime()) / 1000)

        totalSeconds += elapsedSinceStart
      }

      const hours = Math.floor(totalSeconds / 3600)
      const minutes = Math.floor(totalSeconds / 60) % 60
      const seconds = totalSeconds % 60

      return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
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
          await this.stopGame()
        }

        await this.store.loadCrossword(newId)
      }
    },
  },
  mounted() {
    window.addEventListener('pagehide', this.handlePageHide)
  },
  beforeUnmount() {
    window.removeEventListener('pagehide', this.handlePageHide)

    this.stopGameTimer()
  },
  async beforeRouteLeave() {
    await this.stopGame()
  },
  methods: {
    /**
     * Elindítja a játékot és az időzítőt, beállítja a `isCrosswordStarted` változót igazra.
     */
    async startGame() {
      const started = await this.store.startAttempt()
      
      if (!started) {
        return
      }

      this.isCrosswordStarted = true
      this.isAttemptStopped = false

      this.startGameTimer()
    },
    /**
     * Leállítja a játékot, elmenti a rejtvény aktuális állapotát és leállítja az időzítőt.
     * Ha a játék már leállt, vagy még nem indult el, nem történik semmi.
     */
    async stopGame() {
      if (!this.isCrosswordStarted || this.isAttemptStopped) {
        return
      }

      this.isAttemptStopped = true

      this.stopGameTimer()

      try {
        await this.store.saveProgress()
        await this.store.stopAttempt()
      } finally {
        this.isCrosswordStarted = false
      }
    },
    /**
     * Leállítja a próbálkozást, eldobja az aktuális próbálkozás állapotát és visszaállítja a rejtvényt a kezdeti állapotba.
     */
    async abandonAttempt() {
      if (this.isCrosswordStarted || !this.isAttemptStopped || this.isCrosswordResetting) {
        return
      }

      this.isCrosswordResetting = true

      try {
        await this.store.abandonAttempt()
      } catch (error) {
        console.error('Hiba a próbálkozás elhagyása közben:', error)
      } finally {
        this.isCrosswordStarted = false
        this.isAttemptStopped = false
        this.isCrosswordResetting = false
      }
    },
    /**
     * Elindítja a játék időzítőjét, ami minden másodpercben frissíti az eltelt időt.
     */
    startGameTimer() {
      this.stopGameTimer()

      this.timerInterval = setInterval(() => {
        this.timerTick++
      }, 1000)
    },
    /**
     * Leállítja a játék időzítőjét, ha az fut. Ha nincs futó időzítő, nem történik semmi.
     */
    stopGameTimer() {
      if (this.timerInterval) {
        clearInterval(this.timerInterval)
        this.timerInterval = null
      }
    },
    /**
     * Oldal elhagyás, F5, bezárás esetén leállítja a próbálkozást és elmenti az időt
     */
    handlePageHide() {
      if (this.isCrosswordStarted && !this.isAttemptStopped) {
        this.isAttemptStopped = true

        this.stopGameTimer()

        this.store.flushOnUnload()
      }
    },
  }
}
</script>

<style scoped>
@import '../styles/crosswordPage.scss';
</style>