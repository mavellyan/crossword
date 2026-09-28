<template>
  <div v-if="!isPageLoaded" class="text-center mt-5">
    <p v-if="isLoading" class="text-muted">
      Betöltés...
    </p>
    <p v-else-if="pageError" class="alert alert-danger">
      {{ pageError }}
    </p>
  </div>

  <div v-if="!isPopupShown && isPageLoaded" class="container-fluid px-xl-5 px-3 mt-3">
    <h1 class="text-center mb-2">
      {{ crosswordStore.title }}
    </h1>
    <h5 class="text-center text-muted mb-4 fst-italic">
      Készítette: {{ crosswordStore.creator }}
    </h5>

    <div class="row g-1">
      <div class="col-12 col-lg-8 col-xl-8">
        <CrosswordGridSolver
          v-if="!isLoading && !pageError && crosswordStore.words !== null"
          :is-crossword-started="isCrosswordStarted"
        />
      </div>

      <div class="col-12 col-lg-4 col-xl-4">
        
        <div v-if="isLoggedIn && !isCrosswordCompleted" class="text-center bg-light p-3 rounded shadow-sm mb-4">
          <div class="fw-bold lead mb-2">Időmérő</div>
          <div class="fs-3 fw-semibold text-primary">
            {{ displayTime }}
          </div>
          <div v-if="!isCrosswordStarted" class="d-flex justify-content-around mt-3 gap-2">
            <button
              class="btn btn-success btn-md shadow text-uppercase fw-bold"
              @click="startGame()"
              :disabled="isCrosswordResetting"
            >
              {{ startButtonText }}
            </button>
            <button
              v-if="attemptStore.status === 'in_progress'"
              class="btn btn-danger btn-md shadow text-uppercase fw-bold"
              @click="abandonAttempt()"
              :disabled="isCrosswordResetting"
              v-tooltip.hover="'Törli a korábbi próbálkozást és visszaállítja a játékot a kezdeti állapotba.'"
            >
              Újrakezdés
            </button>
          </div>
        </div>

        <div v-if="isCrosswordCompleted" class="text-center bg-success text-white p-4 rounded shadow-sm mb-4">
          <p class="lead fw-bold mb-3">Gratulálunk, sikeresen megoldottad a rejtvényt!</p>
          <p v-if="isLoggedIn" class="mb-1">Jelenlegi időd: <strong>{{ displayTime }}</strong></p>
          <p v-if="isLoggedIn" class="mb-0">Legjobb időd: <strong>{{ formatBestTime }}</strong></p>
          <button
            class="btn btn-secondary btn-md mt-2 shadow text-uppercase fw-bold"
            @click="isLoggedIn ? abandonAttempt() : crosswordStore.guestReset()"
            :disabled="isCrosswordResetting"
          >
            {{ startButtonText }}
          </button>
        </div>

        <div class="bg-white p-3 rounded shadow-sm">
          <div class="text-center fw-bold lead mb-3">A legjobb próbálkozások listája:</div>
          
          <div class="table-responsive">
            <table v-if="isTableVisible" class="table table-striped table-hover mb-0 text-center align-middle">
              <thead class="table-light">
                <tr>
                  <th scope="col">Rang</th>
                  <th scope="col">Felhasználó</th>
                  <th scope="col">Idő</th>
                  <th scope="col">Dátum</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(attempt, index) in attemptStore.bestAttempts" :key="index">
                  <td><strong>{{ index + 1 }}.</strong></td>
                  <td>{{ attempt.username }}</td>
                  <td>{{ formatTime(attempt.elapsed_time) }}</td>
                  <td>{{ formatDate(attempt.completed_at) }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="text-center text-muted mt-3 mb-1">
              Még nincs befejezett próbálkozás. Légy te az első!
            </p>
          </div>
        </div>

      </div>
    </div>
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
import { useAuthStore } from '../stores/auth';
import { mapState } from 'pinia';
import CrosswordGridSolver from '../components/crossword-solver/CrosswordGridSolver.vue';

export default {
  name: 'CrosswordPage',
  components: {
    CrosswordGridSolver,
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
    ...mapState(useCrosswordStore, ['isCompleted']),
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
     * Visszaadja, hogy a rejtvény vagy a próbálkozás betöltése folyamatban van-e.
     * Ha a felhasználó nincs bejelentkezve, akkor csak a rejtvény betöltési állapotát veszi figyelembe.
     * Ha a felhasználó be van jelentkezve, akkor a próbálkozás betöltési állapotát is figyelembe veszi.
     *
     * @returns {boolean} Igaz, ha valamelyik betöltése folyamatban van, hamis egyébként.
     */
    isLoading() {
      if (!this.isLoggedIn) {
        return this.crosswordStore.loading
      } else {
        return this.crosswordStore.loading || this.attemptStore.loading
      }
    },
    /**
     * A start gomb szövegét adja vissza a játék állapotának függvényében.
     *
     * @returns {string}
     */
    startButtonText() {
      if (this.attemptStore.status === 'in_progress') {
        return 'Folytatás'
      } else if (this.attemptStore.status === 'completed' || !this.isLoggedIn) {
        return 'Újraindítás'
      } else {
        return 'Játék indítása'
      }
    },
    /**
     * Visszaadja a rejtvény betöltése közben vagy a próbálkozás betöltése közben fellépő hibát.
     * Ha a felhasználó nincs bejelentkezve, akkor csak a rejtvény betöltési hibát adja vissza.
     * Ha a felhasználó be van jelentkezve, akkor a próbálkozás betöltési hibát is figyelembe veszi.
     * 
     * @returns {string|null} A hiba üzenet, vagy null ha nincs hiba.
     */
    pageError() {
      if (!this.isLoggedIn) {
        return this.crosswordStore.error
      } else {
        if (this.crosswordStore.error || this.attemptStore.error) {
          return this.crosswordStore.error || this.attemptStore.error
        } else {
          return null
        }
      }
    },
    /**
     * Ellenőrzi, hogy a rejtvény adatai betöltődtek-e.
     * 
     * @returns {boolean} Igaz, ha a rejtvény adatai betöltődtek, hamis egyébként.
     */
    isPageLoaded() {
      if (!this.isLoggedIn) {
        return this.crosswordStore.id !== null && !this.isLoading && !this.pageError && Array.isArray(this.crosswordStore.words)
      } else {
        return this.crosswordStore.id !== null && this.attemptStore.id !== null &&
          !this.isLoading && !this.pageError &&
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
      return this.isLoggedIn ? this.attemptStore.isCompleted : this.crosswordStore.isCompleted
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

        if (this.isLoggedIn) {
          this.crosswordStore.applyAttemptState({
            cellInputs: this.attemptStore.cellInputs,
            correctEntryIds: this.attemptStore.correctEntryIds,
          })
        } else {
          this.crosswordStore.applyAttemptState({
            cellInputs: {},
            correctEntryIds: [],
          })
        }
      }
    },
    /**
     * Figyeli a rejtvény befejezését, és ha a rejtvény be van fejezve, leállítja az időzítőt és frissíti a státuszt.
     * 
     * @param completed - A rejtvény befejezésének állapota, amit a Pinia store-ból kapunk.
     */
    isCrosswordCompleted(completed) {
      if (!completed) {
        return
      }

      this.stopGameTimer()
      this.isCrosswordStarted = false
      this.isAttemptStopped = true
    }
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
        this.isAttemptStopped = true
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