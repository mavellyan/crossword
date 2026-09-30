import { defineStore } from 'pinia'
import { loadAttempt, startAttempt, stopAttempt, abandonAttempt, saveProgress, saveAndStopBeacon, listBestAttempts } from '@/services/attemptApi'
import { useAuthStore } from '@/stores/auth'
import { useCrosswordStore } from '@/stores/crossword'

export const useAttemptStore = defineStore('attempt', {
  state: () => ({
    /**
     * Az adott rejtvényhez tartozó próbálkozás azonosítója
     * 
     * @type {string|null}
     */
    id: null,
    /**
     * A próbálkozás során a felhasználó által beírt cellák, a kulcs a cella pozíciója sor:oszlop formátumban, az érték a cellába beírt karakter.
     * 
     * @type {Object<string, string>}
     */
    cellInputs: {},
    /**
     * A backend által visszaadott helyes szavak elhelyezési azonosítóinak listája. Ezt a frontend a crosswordStoreban található wordStatus tömb frissítésére használja.
     * Ez alapján jelöljük a hibás és helyes szavakat a felhasználói felületen.
     * 
     * @type {Array<number>}
     */
    correctEntryIds: [],
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
     * Hibaüzenet, ha a próbálkozás betöltése közben hiba történt. Ha null, akkor nincs hiba.
     * 
     * @type {string|null}
     */
    loadError: null,
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
    /**
     * Jelzi, hogy a rejtvény már meg volt-e oldva, amikor betöltésre került. Ha true, akkor elhomályosítva jelenik meg a rejtvény.
     * Friss befejezés esetén nem akarjuk rögtön elhomályosítani.
     * 
     * @type {boolean}
     */
    wasCompleted: false,
    /**
     * Hibaüzenet, amit megjelenítünk a felhasználónak, ha a próbálkozás mentése/feladása/egyéb művelet közben hiba történt. Ha null, akkor nincs hiba.
     * 
     * @type {string|null}
     */
    displayError: null,
  }),

  getters: {
    /**
     * Egy próbálkozás akkor tekintendő "éppen befejezettnek", ha az aktuális állapot szerint befejezett, de az előző állapot szerint még nem volt befejezett.
     * Ennek segítségével döntjük el, hogy elhomályosítjuk-e a rejtvényt, vagy sem. Ha a rejtvény már betöltéskor be volt fejezve, akkor el akarjuk elhomályosítani.
     * 
     * @param state 
     * @returns {boolean}
     */
    justFinished(state) {
      return state.isCompleted && !state.wasCompleted
    },
  },

  actions: {
    resetState() {
      this.id = null
      this.cellInputs = {}
      this.correctEntryIds = []
      this.status = null
      this.stateVersion = null
      this.elapsedTime = 0
      this.startedAt = null
      this.isCompleted = false
      this.bestTime = null
      this.loading = false
      this.loadError = null
      this.displayError = null
      this.modified = false
      this.saving = false
      this.bestAttempts = []
      this.wasCompleted = false
    },
    initializeAttemptState(attempt) {
      this.id = attempt.id
      this.cellInputs = attempt.cell_inputs ?? {}
      this.correctEntryIds = attempt.correct_entry_ids ?? []
      this.status = attempt.status
      this.stateVersion = attempt.state_version
      this.elapsedTime = attempt.elapsed_time ?? 0
      this.startedAt = attempt.started_at ?? null
      this.isCompleted = attempt.status === 'completed'
      this.wasCompleted = attempt.status === 'completed'
    },
    /**
     * Rejtvény adatot kér route id alapján, majd inicializálja a játékállapotot.
     *
     * @param {string|number} id A route-ból érkező rejtvény azonosító.
     * @returns {Promise<void>}
     */
    async loadAttempt(id) {
      this.resetState()

      if (!id || !useAuthStore().isLoggedIn) {
        return
      }
      
      this.loading = true
      this.loadError = null
    
      try {
        const { attempt, bestTime } = await loadAttempt(id)
    
        this.bestTime = bestTime
        this.initializeAttemptState(attempt)

        return true
      } catch (error) {
        console.log('error: ', error)
        const errorMsg = error?.response?.data?.message ?? error?.message ?? ''
        this.loadError = 'Hiba a rejtvény betöltése közben: ' + errorMsg

        return false
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
        return false
      }

      this.displayError = null
    
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
        const errorMsg = error?.response?.data?.message ?? error?.message ?? ''
        this.displayError = 'Hiba a rejtvény próbálkozás elindítása közben: ' + errorMsg
    
        return false
      }
    },
    /**
     * Leállítja a rejtvény próbálkozást. Ha nincs bejelentkezett felhasználó, vagy nincs attemptId, akkor nem történik semmi.
     * 
     * @returns {Promise<boolean>} Sikeres volt-e a próbálkozás leállítása.
     */
    async stopAttempt() {
      if (!useAuthStore().isLoggedIn || !this.id) {
        return false
      }
    
      if (this.status === 'completed' || !this.startedAt) {
        return true
      }

      this.displayError = null
    
      try {
        const data = await stopAttempt(this.id)
    
        if (data.attempt) {
          this.status = data.attempt.status ?? this.status
          this.elapsedTime = data.attempt.elapsed_time ?? this.elapsedTime
          this.startedAt = null
        }

        return true
      } catch (error) {
        console.log('Hiba a rejtvény próbálkozás leállítása közben:', error)
        const errorMsg = error?.response?.data?.message ?? error?.message ?? ''
        this.displayError = 'Hiba a rejtvény próbálkozás leállítása közben: ' + errorMsg

        return false
      }
    },
    /**
     * Feladja a rejtvény próbálkozást, és újratölti a rejtvényt, így létrehozva egy új próbálkozást.
     * 
     * @returns {Promise<void>}
     */
    async abandonAttempt() {
      if (!useAuthStore().isLoggedIn || !this.id) {
        return false
      }

      this.displayError = null

      const crosswordStore = useCrosswordStore()
    
      try {
        const data = await abandonAttempt(this.id)
    
        if (data.success) {
          await this.loadAttempt(crosswordStore.id)
          await crosswordStore.loadCrossword(crosswordStore.id)
          await this.loadBestAttempts(crosswordStore.id)
        }

        return true
      } catch (error) {
        console.log('Hiba a rejtvény próbálkozás feladása közben:', error)
        const errorMsg = error?.response?.data?.message ?? error?.message ?? ''
        this.displayError = 'Hiba a rejtvény próbálkozás feladása közben: ' + errorMsg

        return false
      }
    },
    /**
     * Elmenti a rejtvény aktuális állapotát, 2 másodpercenként fut.
     * Ha nincs bejelentkezett felhasználó, nincs attemptId, vagy a rejtvény már be van fejezve,
     * nem történt módosítás, vagy van jelenleg futó mentés, akkor nem történik semmi.
     * 
     * @returns {Promise<{status: string, recovered?: boolean}>} A mentés állapota: "saved", "skipped", "failed", "conflict".
     *                                                           Ha a státusz "conflict", akkor a recovered mező jelzi, hogy sikerült-e az újratöltés a konfliktus után.
     */
    async saveProgress() {
      // Ha a felhasználó nincs belentkezve, nincs attemptId, a rejtvény már be van fejezve, vagy éppen mentés folyik, akkor nem csinálunk semmit
      if (!useAuthStore().isLoggedIn || !this.id || this.status === 'completed' || this.saving || !this.modified) {
        return {
          status: 'skipped',
        }
      }
      const crosswordStore = useCrosswordStore()

      this.saving = true
      this.displayError = null

      try {
        const snapshot = crosswordStore.createProgressPayload()
        
        const response = await saveProgress(this.id, snapshot, this.stateVersion)

        // Itt biztosan lennie kell már attemptnek, mivel a backend csak akkor engedi a mentést, ha van attemptId és login
        this.status = response.attempt.status
        this.stateVersion = response.attempt.state_version
        this.correctEntryIds = response.attempt.correct_entry_ids ?? []

        // Ha a rejtvény befejeződött, akkor nullázzuk a startedAt értéket, és frissítjük az elapsedTime-ot a backendről
        if (this.status === 'completed') {
          this.elapsedTime = response.attempt.elapsed_time ?? this.elapsedTime
          this.startedAt = null
          this.isCompleted = true
        }

        crosswordStore.updateEntryStatuses(this.correctEntryIds)

        // Ellenőrízzük, hogy a mentés közben történt-e változás a rejtvényben
        // Ha a snapshot és az új snapshot nem egyezik, akkor a rejtvény módosult a mentés óta
        const newSnapshot = crosswordStore.createProgressPayload()
        this.modified = JSON.stringify(snapshot) !== JSON.stringify(newSnapshot)

        if (this.modified) {
          crosswordStore.scheduleSave()
        }

        if (this.status === 'completed' && response.best_time !== undefined) {
          this.bestTime = response.best_time
          this.loadBestAttempts(crosswordStore.id)
        }

        this.displayError = null

        return {
          status: 'saved',
        }
      } catch (error) {
        const isConflict = error?.response?.status === 409 || error?.response?.data?.save_status === 'conflict'

        if (isConflict) {
          crosswordStore.cancelScheduledSave()

          const recovered = await this.reloadAfterConflict(crosswordStore)

          this.displayError = recovered ? 'A próbálkozás egy másik lapon módosult. A legfrissebb mentett állapotot betöltöttük.'
            : 'Ütközés történt mentésnél. Frissítsd az oldalt a folytatáshoz.'
          
          return {
            status: 'conflict',
            recovered,
          }
        }
        
        this.modified = true
        this.displayError = error?.response?.data?.message ?? error?.message ?? 'Ismeretlen hiba történt a próbálkozás mentése közben.'

        return {
          status: 'failed',
        }
      } finally {
        this.saving = false
      }
    },
    /**
     * Újratölti a próbálkozást a konfliktus után.
     * 
     * @param crosswordStore 
     * @returns {Promise<boolean>} Sikeres volt-e az újratöltés.
     */
    async reloadAfterConflict(crosswordStore) {
      try {
        const { attempt, bestTime } = await loadAttempt(crosswordStore.id)

        this.bestTime = bestTime
        this.initializeAttemptState(attempt)
        this.modified = false

        crosswordStore.applyAttemptState({
          cellInputs: this.cellInputs,
          correctEntryIds: this.correctEntryIds,
        })

        return true
      } catch (error) {
        console.error('Nem sikerült újratölteni a próbálkozást a konfliktus után:', error)
        return false
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

      const crosswordStore = useCrosswordStore()

      const snapshot = crosswordStore.createProgressPayload()
      saveAndStopBeacon(this.id, snapshot, this.stateVersion)
    },
    async loadBestAttempts(crosswordId) {
      if (!crosswordId) {
        return
      }

      this.displayError = null

      try {
        const response = await listBestAttempts(crosswordId)
        this.bestAttempts = response

        return true
      } catch (error) {
        console.error('Hiba a legjobb próbálkozások betöltésekor:', error)
        const errorMsg = error?.response?.data?.message ?? error?.message ?? ''
        this.displayError = 'Hiba a legjobb próbálkozások betöltésekor: ' + errorMsg

        return false
      }
    },
  },
})