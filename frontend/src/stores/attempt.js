import { defineStore } from 'pinia'
import { loadAttempt, startAttempt, stopAttempt, abandonAttempt, saveProgress, saveAndStopBeacon, listBestAttempts } from '@/services/attemptApi'
import { useAuthStore } from '@/stores/auth'
import { useCrosswordStore } from '@/stores/crossword'

export const useAttemptStore = defineStore('attempt', {
  state: () => ({
    crosswordStore: useCrosswordStore(),
    /**
     * Az adott rejtvényhez tartozó próbálkozás azonosítója
     * 
     * @type {string|null}
     */
    id: null,
    /**
     * A próbálkozás során a felhasználó által beírt szavak, ahol a kulcs a szó azonosítója, az érték pedig egy tömb,
     * amely a szó celláinak számával egyezik és a beírt karaktereket tartalmazza.
     * 
     * @type {Array<Array<string>>}
     */
    wordInputs: [],
    /**
     * A backend által visszaadott helyes szavak elhelyezési azonosítóinak listája. Ezt a frontend a crosswordStoreban található wordStatus tömb frissítésére használja.
     * Ez alapján jelöljük a hibás és helyes szavakat a felhasználói felületen.
     * 
     * @type {Array<string>}
     */
    correctWords: [],
    /**
     * A próbálkozás státusza, lehetséges értékek: "not_started", "in_progress", "completed", "abandoned".
     * 
     * @type {string|null}
     */
    status: null,
    /**
     * A próbálkozás állapotának verziószáma, amelyet a backend kezel. Minden mentés után növekszik.
     * 
     * @type {number|null}
     */
    stateVersion: null,
    /**
     * A rejtvény megoldására fordított összes eltelt idő másodpercben.
     * Backenden történik a tényleges számlálás, a frontend csak betölti, és folytatás esetén intervallal számol tovább, de a backend a mérvadó, db-be az kerül.
     * 
     * @type {number}
     */
    elapsedTime: 0,
    /**
     * A próbálkozás elindításának időbélyege, amelyet a backend ad vissza. Ha null, akkor a próbálkozás még nem indult el.
     * 
     * @type {string|null}
     */
    startedAt: null,
    /**
     * A rejtvény megoldásának állapota.
     * 
     * @type {boolean}
     */
    isCompleted: false,
    /**
     * A felhasználó legjobb ideje a rejtvény megoldására másodpercben. Ha null, akkor nincs még befejezett próbálkozás.
     * 
     * @type {number|null}
     */
    bestTime: null,
    /**
     * Jelzi, hogy a próbálkozás betöltése folyamatban van-e. Ha true, akkor a komponensek betöltési állapotot jelenítenek meg.
     * 
     * @type {boolean}
     */
    loading: false,
    /**
     * Hibaüzenet, ha a próbálkozás betöltése vagy mentése közben hiba történt. Ha null, akkor nincs hiba.
     * 
     * @type {string|null}
     */
    error: null,
    /**
     * Jelzi, hogy a próbálkozás módosult-e a legutóbbi mentés óta.
     * 
     * @type {boolean}
     */
    modified: false,
    /**
     * Jelzi, hogy a próbálkozás mentése folyamatban van-e.
     * 
     * @type {boolean}
     */
    saving: false,
    /**
     * A rejtvényhez tartozó 5 legjobb próbálkozás listája, a backend adja vissza. Ha üres, akkor nincs még befejezett próbálkozás.
     * 
     * @type {Array<Object>}
     */
    bestAttempts: [],
  }),

  actions: {
    resetState() {
      this.id = null
      this.wordInputs = []
      this.correctWords = []
      this.status = null
      this.stateVersion = null
      this.elapsedTime = 0
      this.startedAt = null
      this.isCompleted = false
      this.bestTime = null
      this.loading = false
      this.error = null
      this.modified = false
      this.saving = false
      this.bestAttempts = []
    },
    initializeAttemptState(attempt) {
      this.id = attempt.id
      this.wordInputs = attempt.word_inputs ?? []
      this.correctWords = attempt.correct_words ?? []
      this.status = attempt.status
      this.stateVersion = attempt.state_version
      this.elapsedTime = attempt.elapsed_time ?? 0
      this.startedAt = attempt.started_at ?? null
      this.isCompleted = attempt.status === 'completed'
    },
    /**
     * Rejtvény adatot kér route id alapján, majd inicializálja a játékállapotot.
     *
     * @param {string|number} id A route-ból érkező rejtvény azonosító.
     * @returns {Promise<void>}
     */
    async loadAttempt(id) {
      this.resetState()
      this.loading = true
    
      try {
        const { attempt, bestTime } = await loadAttempt(id)
    
        this.bestTime = bestTime
        this.initializeAttemptState(attempt)
      } catch (error) {
        console.log('error: ', error)
        this.error = error?.message ?? 'Hiba a rejtvény betöltése közben.'
      } finally {
        this.loading = false
      }
    },
    /**
     * Bejelentkezett felhasználók esetén elindítja a rejtvény próbálkozást a backendnél. Ha nincs bejelentkezett felhasználó, vagy nincs attemptId, akkor nem történik semmi.
     * 
     * @returns {Promise<boolean>} Sikeres volt-e a próbálkozás indítása.
     */
    async startAttempt() {
      if (!useAuthStore().isLoggedIn || !this.id) {
        return
      }
    
      try {
        const data = await startAttempt(this.id)

        if (data.attempt) {
          this.elapsedTime = data.attempt.elapsed_time ?? 0
          this.startedAt = data.attempt.started_at ?? null
          this.status = data.attempt.status
        }
    
        return true
      } catch (error) {
        console.log('Hiba a rejtvény próbálkozás elindítása közben:', error)
        this.error = error?.response?.data?.message ?? error?.message ?? 'Hiba a rejtvény próbálkozás elindítása közben.'
    
        return false
      }
    },
    /**
     * Leállítja a rejtvény próbálkozást. Ha nincs bejelentkezett felhasználó, vagy nincs attemptId, akkor nem történik semmi.
     * 
     * @returns {Promise<void>}
     */
    async stopAttempt() {
      if (!useAuthStore().isLoggedIn || !this.id) {
        return
      }
    
      if (this.status === 'completed' || !this.startedAt) {
        return
      }
    
      try {
        const data = await stopAttempt(this.id)
    
        if (data.attempt) {
          this.status = data.attempt.status ?? this.status
          this.elapsedTime = data.attempt.elapsed_time ?? this.elapsedTime
          this.startedAt = null
        }
      } catch (error) {
        console.log('Hiba a rejtvény próbálkozás leállítása közben:', error)
        this.error = error?.response?.data?.message ?? error?.message ?? 'Hiba a rejtvény próbálkozás leállítása közben.'
      }
    },
    /**
     * Feladja a rejtvény próbálkozást, és újratölti a rejtvényt, így létrehozva egy új próbálkozást.
     * 
     * @returns {Promise<void>}
     */
    async abandonAttempt() {
      if (!useAuthStore().isLoggedIn || !this.id) {
        return
      }
    
      try {
        const data = await abandonAttempt(this.id)
    
        if (data.success) {
          this.loadAttempt(this.crosswordStore.id)
          this.crosswordStore.loadCrossword(this.crosswordStore.id)
          this.loadBestAttempts(this.crosswordStore.id)
        }
      } catch (error) {
        console.log('Hiba a rejtvény próbálkozás feladása közben:', error)
        this.error = error?.response?.data?.message ?? error?.message ?? 'Hiba a rejtvény próbálkozás feladása közben.'
      }
    },
    /**
     * Elmenti a rejtvény aktuális állapotát, 2 másodpercenként fut.
     * Ha nincs bejelentkezett felhasználó, nincs attemptId, vagy a rejtvény már be van fejezve,
     * nem történt módosítás, vagy van jelenleg futó mentés, akkor nem történik semmi.
     * 
     * @returns {Promise<void>}
     */
    async saveProgress() {
      // Ha a felhasználó nincs belentkezve, nincs attemptId, a rejtvény már be van fejezve, vagy éppen mentés folyik, akkor nem csinálunk semmit
      if (!useAuthStore().isLoggedIn || !this.id || this.status === 'completed' || this.saving || !this.modified) {
        return
      }

      this.saving = true

      try {
        const snapshot = this.crosswordStore.createProgressPayload()
        
        const response = await saveProgress(this.id, snapshot, this.stateVersion)

        // Itt biztosan lennie kell már attemptnek, mivel a backend csak akkor engedi a mentést, ha van attemptId és login
        this.status = response.attempt.status
        this.stateVersion = response.attempt.state_version
        this.correctWords = response.attempt.correct_words ?? []

        // Ha a rejtvény befejeződött, akkor nullázzuk a startedAt értéket, és frissítjük az elapsedTime-ot a backendről
        if (this.status === 'completed') {
          this.elapsedTime = response.attempt.elapsed_time ?? this.elapsedTime
          this.startedAt = null
          this.isCompleted = true
        }

        this.crosswordStore.validateWords()

        // Ellenőrízzük, hogy a mentés közben történt-e változás a rejtvényben
        // Ha a snapshot és az új snapshot nem egyezik, akkor a rejtvény módosult a mentés óta
        const newSnapshot = this.crosswordStore.createProgressPayload()
        this.modified = JSON.stringify(snapshot) !== JSON.stringify(newSnapshot)

        if (this.modified) {
          this.scheduleSave()
        }

        if (this.status === 'completed' && response.best_time !== undefined) {
          this.bestTime = response.best_time
          this.loadBestAttempts(this.crosswordStore.id)
        }

      } catch (error) {

        console.log('Hiba a rejtvény mentése közben:', error)
        this.error = error?.response?.data?.message ?? error?.message ?? 'Hiba a rejtvény mentése közben.'

      } finally {
        this.saving = false
      }
    },
    /**
     * Mentés és próbálkozás leállítás a böngésző ablak bezárása/újratöltése előtt.
     * 
     * @returns {Promise<void>}
     */
    flushOnUnload() {
      if (!useAuthStore().isLoggedIn || !this.id || this.status !== 'in_progress') {
        return
      }

      const snapshot = this.crosswordStore.createProgressPayload()
      saveAndStopBeacon(this.id, snapshot, this.stateVersion)
    },
    async loadBestAttempts(crosswordId) {
      if (!crosswordId) {
        return
      }

      try {
        const response = await listBestAttempts(crosswordId)
        this.bestAttempts = response
      } catch (error) {
        console.error('Hiba a legjobb próbálkozások betöltésekor:', error)
      }
    },
  },
})