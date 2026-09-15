<template>
  <div v-if="!isPopupShown && isPageLoaded">
    <h1 class="text-center mb-2">
      {{ crosswordStore.title }}
    </h1>
    <h5 class="text-center text-muted mb-5 fst-italic">
      Készítette: {{ crosswordStore.creator }}
    </h5>
    <CrosswordGrid
      v-if="crosswordStore.words !== null"
      :words="crosswordStore.words"
      :width="crosswordStore.width"
      :is-crossword-resetting="isCrosswordResetting"
      @start-game="startGame()"
      @abandon-attempt="abandonAttempt()"
    />
    <div v-if="isLoggedIn && !isCrosswordCompleted" class="text-center mt-4">
      Időmérő
      <div>
        {{  displayTime  }}
      </div>
    </div>
    <div
      v-if="isLoggedIn && isCrosswordCompleted"
    >
      <div class="text-center bg-light p-4 rounded shadow w-50 mx-auto mt-5">
        <p class="lead fw-bold">Gratulálunk, sikeresen megoldottad a rejtvényt!</p>
        <p>Jelenlegi időd: {{ displayTime }}</p>
        <p>Legjobb időd: {{ formatBestTime }}</p>
      </div>
    </div>
    <div class="text-center mt-5">
      <div class="fw-bold lead">A legjobb próbálkozások listája:</div>
      <table v-if="isTableVisible" class="table table-striped w-50 mx-auto mt-3">
        <thead>
          <tr>
            <th scope="col">Rang</th>
            <th scope="col">Felhasználó</th>
            <th scope="col">Idő</th>
            <th scope="col">Dátum</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(attempt, index) in attemptStore.bestAttempts" :key="index">
            <td>{{ index + 1 }}</td>
            <td>{{ attempt.username }}</td>
            <td>{{ formatTime(attempt.elapsed_time) }}</td>
            <td>{{ formatDate(attempt.completed_at) }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else class="text-muted">Még nincs befejezett próbálkozás. Légy te az első!</p>
    </div>
  </div>
  <div v-else class="text-center">
    <p v-if="crosswordStore.loading">
      Betöltés...
    </p>
    <p v-else-if="crosswordStore.error" class="alert alert-danger">
      {{ crosswordStore.error }}
    </p>
  </div>
  <div
    v-if="isPopupShown && isPageLoaded"
    class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-flex justify-content-center align-items-center z-3 p-3"
  >
    <div class="card shadow-lg p-4 p-md-5 text-center" style="max-width: 520px; width: 100%;">
      <h3 class="fw-bold mb-3">Szeretnéd elmenteni az eredményeid?</h3>
    
      <p class="text-muted mb-3">
        Vendégként is játszhatsz, de bejelentkezve elérhetővé válik az 
        <strong>időmérés</strong>, a <strong>félbehagyott játékok mentése</strong>, <strong>saját rejtvények készítése</strong> és még sok más.
      </p>

      <div class="d-grid gap-2 col-11 mx-auto mt-2">
        <router-link to="/login" class="btn btn-primary fw-bold text-uppercase shadow-sm">
          Bejelentkezés / Regisztráció
        </router-link>
        <button 
          type="button" 
          class="btn btn-outline-secondary fw-bold" 
          @click="isPopupHidden = true"
        >
          Folytatás vendégként
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { useCrosswordStore } from '../stores/crossword';
import { useAttemptStore } from '../stores/attempt';
import CrosswordGrid from '../components/CrosswordGrid.vue';
import { useAuthStore } from '../stores/auth';
import { mapState } from 'pinia';

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
      /**
       * Jelzi, hogy a vendég felhasználó számára megjelenő popup el van-e rejtve. Ha igaz, akkor a popup nem látható.
       * 
       * @type {boolean}
       */
      isPopupHidden: false,
    }
  },
  computed: {
    ...mapState(useAuthStore, ['isLoggedIn']),
    /**
     * Elérhetővé teszi a crossword Pinia store-t az oldal számára.
     *
     * @returns {import('../stores/crossword').useCrosswordStore}
     */
    crosswordStore() {
      return useCrosswordStore()
    },
    attemptStore() {
      return useAttemptStore()
    },
    /**
     * Ellenőrzi, hogy a rejtvény adatai betöltődtek-e.
     * 
     * @returns {boolean} Igaz, ha a rejtvény adatai betöltődtek, hamis egyébként.
     */
    isPageLoaded() {
      if (!this.isLoggedIn) {
        return this.crosswordStore.id !== null && !this.crosswordStore.loading && !this.crosswordStore.error && Array.isArray(this.crosswordStore.words)
      } else {
        return this.crosswordStore.id !== null && this.attemptStore.id !== null &&
          !this.crosswordStore.loading && !this.attemptStore.loading &&
          !this.crosswordStore.error && !this.attemptStore.error &&
          Array.isArray(this.crosswordStore.words)
      }
    },
    isTableVisible() {
      return this.attemptStore.bestAttempts.length > 0
    },
    /**
     * Ellenőrzi, hogy a rejtvény be van-e fejezve.
     * 
     * @returns {boolean} Igaz, ha a rejtvény be van fejezve, hamis egyébként.
     */
    isCrosswordCompleted() {
      return this.attemptStore.isCompleted || this.attemptStore.status === 'completed'
    },
    /**
     * Formázza az eltelt időt órákra, percekre és másodpercekre.
     *
     * @returns {string} Az eltelt idő formázott stringként (HH:MM:SS).
     */
    displayTime() {
      this.timerTick // Timer ticket használjuk, hogy a computed property újraszámolódjon minden másodpercben.

      let totalSeconds = this.attemptStore.elapsedTime

      if (this.attemptStore.startedAt) {
        const startedAt = new Date(this.attemptStore.startedAt)
        const elapsedSinceStart = Math.floor((Date.now() - startedAt.getTime()) / 1000)

        totalSeconds += elapsedSinceStart
      }

      const hours = Math.floor(totalSeconds / 3600)
      const minutes = Math.floor(totalSeconds / 60) % 60
      const seconds = totalSeconds % 60

      return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
    },
    /**
     * A rejtvény befejezését követően, vagy befejezett rejtvény betöltése esetén megjeleníti a felhasználó próbálkozásai közül
     * a legjobb időt, formázottan, vagy 'N/A' ha nincs még befejezett próbálkozás.
     * 
     * @returns {string} A legjobb idő formázott stringként (HH:MM:SS) vagy 'N/A'.
     */
    formatBestTime() {
      if (this.attemptStore.bestTime === null) {
        return 'N/A'
      }

      const totalSeconds = this.attemptStore.bestTime
      const hours = Math.floor(totalSeconds / 3600)
      const minutes = Math.floor(totalSeconds / 60) % 60
      const seconds = totalSeconds % 60

      return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
    },
    isPopupShown() {
      return (!this.isLoggedIn && !this.isPopupHidden)
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

        await this.attemptStore.loadAttempt(newId)
        await this.crosswordStore.loadCrossword(newId)
        await this.attemptStore.loadBestAttempts(newId)
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
      const started = await this.attemptStore.startAttempt()
      
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
        await this.attemptStore.saveProgress()
        await this.attemptStore.stopAttempt()
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
        await this.attemptStore.abandonAttempt()
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

        this.attemptStore.flushOnUnload()
      }
    },
    /**
     * Formázza az időt óra:perc:másodperc formátumba.
     * 
     * @param timeInSeconds Az eltelt idő másodpercben.
     * @returns {string} A formázott idő.
     */
    formatTime(timeInSeconds) {
      if (timeInSeconds === null) {
        return 'N/A'
      }

      const hours = Math.floor(timeInSeconds / 3600)
      const minutes = Math.floor(timeInSeconds / 60) % 60
      const seconds = timeInSeconds % 60

      return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
    },
    /**
     * Formázza a dátumot a felhasználó helyi beállításainak megfelelően.
     * 
     * @param dateString A dátum string, amit formázni szeretnénk.
     * @returns {string} A formázott dátum.
     */
    formatDate(dateString) {
      if (!dateString) {
        return 'N/A'
      }

      // Ha 3-nál több tizedesjegy van a másodperc után, levágjuk 3 jegyre (.123456Z -> .123Z),
      // mert bizonyos böngészők nem tudják jól kezelni, és amúgy sem jelenítjük meg őket.
      const normalizedDate = dateString.replace(/\.(\d{3})\d+Z$/, '.$1Z')

      const date = new Date(normalizedDate)

      if (isNaN(date.getTime())) {
        console.log('ezaz?')
        return 'N/A'
      }

      return date.toLocaleDateString('hu-HU', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
      })
    },
  }
}
</script>

<style scoped>
@import '../styles/crosswordPage.scss';
</style>