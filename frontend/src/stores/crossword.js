import { defineStore } from 'pinia'
import { loadCrosswordById, saveCrosswordProgress, startAttempt, stopAttempt } from '@/services/crosswordApi'
import { useAuthStore } from '@/stores/auth'

/**
 * Egy nyers cella inputot egyetlen nagybetűs karakterre normalizál.
 * A whitespace karaktereket eltávolítja, hogy ne lehessen üres helyet beírni.
 *
 * @param {unknown} value Input eseményből érkező nyers érték.
 * @returns {string} Normalizált egykarakteres érték vagy üres string.
 */
function normalizeLetter(value) {
  if (value === null || value === undefined) {
    return ''
  }

  const normalized = String(value).normalize().replace(/\s/g, '').toLocaleUpperCase('hu-HU')

  return Array.from(normalized)[0] ?? ''
}

/**
 * Létrehozza az input mátrixot az összes szóhoz.
 * Minden szó kap egy tömböt, ami a cellaszámával egyezik.
 *
 * @param {Array<{ cells: Array<unknown> }>|null|undefined} words Rejtvény szavak.
 * @param {Object<string, string[]>} savedWordInputs Mentett szóinputok, hogyha van korábbi próbálkozás.
 * @returns {string[][]} Szavankénti felhasználói input tömbök. Lehetnek üres stringek, ha a felhasználó még nem írt be karaktert.
 */
function createWordInputs(words, savedWordInputs = {}) {
  return (words ?? []).map((word) => {
    const placementId = String(word.placement_id)

    const savedCells = Array.isArray(savedWordInputs[placementId]) ? savedWordInputs[placementId] : []

    return Array.from({ length: word.cells.length }, (_, index) => {
      return normalizeLetter(savedCells[index] ?? '')
    })
  })
}

/**
 * Létrehozza az ellenőrzési állapot objektumokat minden szóhoz.
 *
 * @param {Array<unknown>|null|undefined} words Rejtvény szavak.
 * @returns {Array<{ filled: boolean, correct: boolean }>} Kezdeti szó állapot lista.
 */
function createWordStatus(words) {
  return (words ?? []).map(() => ({ filled: false, correct: false }))
}

export const useCrosswordStore = defineStore('crossword', {
  state: () => ({
    id: null,
    grid: null,
    title: null,
    creator: null,
    mainSolution: null,
    words: null,
    width: null,
    height: null,

    attemptId: null,
    status: null,
    stateVersion: null,
    isAttemptInProgress: false,

    elapsedTime: 0,
    startedAt: null,

    wordInputs: [],
    wordStatus: [],

    activeWordIndex: null,
    activeCellByWord: [],

    loading: false,
    saving: false,
    modified: false,
    error: null,
  }),

  actions: {
    /**
     * Törli a store-ból az összes rejtvény adatot és játékállapotot.
     *
     * @returns {void}
     */
    resetState() {
      this.id = null
      this.grid = null
      this.title = null
      this.creator = null
      this.mainSolution = null
      this.words = null
      this.width = null
      this.height = null

      this.attemptId = null
      this.status = null
      this.stateVersion = null
      this.isAttemptInProgress = false

      this.elapsedTime = 0
      this.startedAt = null

      this.wordInputs = []
      this.wordStatus = []

      this.activeWordIndex = null
      this.activeCellByWord = []

      this.loading = false
      this.saving = false
      this.modified = false
      this.error = null
    },

    /**
     * A betöltött rejtvény adatokból inicializálja a futásidejű játékállapot tömböket.
     *
     * @param {object} crossword Betöltött rejtvény objektum.
     * @param {object|null} attempt Felhasználói próbálkozás objektum, ha van. Amennyiben nincs regisztrálva a felhasználó, null.
     * @returns {void}
     */
    initializePlayState(crossword, attempt) {
      this.id = crossword.id
      this.grid = crossword.grid ?? null
      this.title = crossword.title
      this.creator = crossword.creator
      this.mainSolution = crossword.main_solution ?? null
      this.words = crossword.words ?? []
      this.width = crossword.width ?? null
      this.height = crossword.height ?? null

      if (attempt) {
        this.attemptId = attempt.id
        this.status = attempt.status
        this.stateVersion = attempt.state_version
        
        if (attempt.status === 'in_progress') {
          this.elapsedTime = attempt.elapsed_time ?? 0
          this.startedAt = attempt.started_at ?? null
          this.isAttemptInProgress = this.startedAt !== null
        }
      }

      this.wordInputs = createWordInputs(this.words, attempt?.word_inputs ?? {})
      this.wordStatus = createWordStatus(this.words)

      // Validálni kell, mivel lehet, hogy volt korábbi próbálkozás már
      this.words.forEach((_, index) => this.validateWord(index))

      this.activeWordIndex = this.words?.length ? 0 : null
      this.activeCellByWord = this.wordInputs.map(() => 0)
    },

    /**
     * Frissít egy adott cellát egy szóban, majd újraszámolja a szó állapotát.
     * Érvénytelen indexeket biztonságosan figyelmen kívül hagy.
     *
     * @param {number} wordIndex A cél szó indexe.
     * @param {number} cellIndex A cél cella indexe a szóban.
     * @param {unknown} value UI inputból érkező nyers érték.
     * @returns {void}
     */
    updateWordCell(wordIndex, cellIndex, value) {
      if (!this.wordInputs[wordIndex]) {
        return
      }

      if (cellIndex < 0 || cellIndex >= this.wordInputs[wordIndex].length) {
        return
      }

      this.wordInputs[wordIndex][cellIndex] = normalizeLetter(value)
      this.validateWord(wordIndex)
      this.modified = true
    },

    /**
     * Törli a karaktert egy adott szó cellájából, majd újraszámolja a státuszt.
     * Érvénytelen indexeket biztonságosan figyelmen kívül hagy.
     *
     * @param {number} wordIndex A cél szó indexe.
     * @param {number} cellIndex A cél cella indexe a szóban.
     * @returns {void}
     */
    deleteWordCell(wordIndex, cellIndex) {
      if (!this.wordInputs[wordIndex]) {
        return
      }

      if (cellIndex < 0 || cellIndex >= this.wordInputs[wordIndex].length) {
        return
      }

      this.wordInputs[wordIndex][cellIndex] = ''
      this.validateWord(wordIndex)
      this.modified = true
    },

    /**
     * Egy szót úgy validál, hogy összehasonlítja az aktuális felhasználói inputot az elvárt megoldással.
     * Egy szó csak akkor lehet helyes, ha teljesen ki van töltve.
     *
     * @param {number} wordIndex A validálandó szó indexe.
     * @returns {void}
     */
    validateWord(wordIndex) {
      const inputs = this.wordInputs[wordIndex]
      const word = this.words?.[wordIndex]

      if (!inputs || !word) {
        return
      }

      const filled = inputs.every((cell) => cell !== '')
      const expected = (word.solution ?? '').toUpperCase()
      const attempt = inputs.join('')

      this.wordStatus[wordIndex] = {
        filled,
        correct: filled && attempt === expected,
      }
    },

    /**
     * Beállítja az aktuálisan aktív szót a kiemeléshez és a billentyűzet logikához.
     *
     * @param {number} wordIndex Az aktiválandó szó indexe.
     * @returns {void}
     */
    setActiveWord(wordIndex) {
      if (!Number.isInteger(wordIndex)) {
        return
      }

      if (wordIndex < 0 || wordIndex >= (this.words?.length ?? 0)) {
        return
      }

      this.activeWordIndex = wordIndex

      if (!Number.isInteger(this.activeCellByWord[wordIndex])) {
        this.activeCellByWord[wordIndex] = 0
      }
    },

    /**
     * Eltárolja, hogy egy szón belül melyik cella az aktív.
     * Az érték érvényes tartományra van clampelve.
     *
     * @param {number} wordIndex A szó indexe.
     * @param {number} cellIndex A kívánt cella index.
     * @returns {void}
     */
    setActiveCellInWord(wordIndex, cellIndex) {
      if (!Number.isInteger(wordIndex) || !Number.isInteger(cellIndex)) {
        return
      }

      const cells = this.wordInputs[wordIndex]
      if (!cells) {
        return
      }

      const clamped = Math.min(Math.max(cellIndex, 0), cells.length - 1)
      this.activeCellByWord[wordIndex] = clamped
    },

    /**
     * Rejtvény adatot kér route id alapján, majd inicializálja a játékállapotot.
     *
     * @param {string|number} id A route-ból érkező rejtvény azonosító.
     * @returns {Promise<void>}
     */
    async loadCrossword(id) {
      this.resetState()
      this.loading = true

      try {
        const { crossword, attempt } = await loadCrosswordById(id)

        this.initializePlayState(crossword, attempt)
      } catch (error) {
        console.log('error: ', error)
        this.error = error?.message ?? 'Hiba a rejtvény betöltése közben.'
      } finally {
        this.loading = false
      }
    },

    /**
     * Elkészíti a mentési payloadot a backendnek a szóinputokból. Csak akkor van jelentősége, ha a user be van jelentkezve.
     * 
     * @returns {Object<string, string[]>} Mentési payload a backendnek
     */
    createProgressPayload() {
      return Object.fromEntries(
        (this.words ?? []).map((word, index) => [
          String(word.placement_id),
          [...(this.wordInputs[index] ?? [])],
        ]),
      )
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
      if (!useAuthStore().isLoggedIn || !this.attemptId || this.status === 'completed' || this.saving) {
        return
      }

      this.saving = true

      try {
        const snapshot = this.createProgressPayload()
        
        const response = await saveCrosswordProgress(this.attemptId, snapshot, this.stateVersion)

        // Itt biztosan lennie kell már attemptnek, mivel a backend csak akkor engedi a mentést, ha van attemptId és login
        this.status = response.attempt.status
        this.stateVersion = response.attempt.state_version

      } catch (error) {
        console.log('Hiba a rejtvény mentése közben:', error)
        this.error = error?.response?.data?.message ?? error?.message ?? 'Hiba a rejtvény mentése közben.'
      } finally {
        this.saving = false
      }
    },
    async startAttempt() {
      if (!useAuthStore().isLoggedIn || !this.attemptId) {
        return
      }

      try {
        const data = await startAttempt(this.attemptId)

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
    async stopAttempt() {
      if (!useAuthStore().isLoggedIn || !this.attemptId) {
        return
      }

      if (this.status === 'completed' || !this.startedAt) {
        return
      }

      try {
        const data = await stopAttempt(this.attemptId)

        if (data.attempt) {
          this.status = data.attempt.status ?? this.status
          this.elapsedTime = data.attempt.elapsed_time ?? this.elapsedTime
          this.startedAt = null
          this.isAttemptInProgress = false
        }
      } catch (error) {
        console.log('Hiba a rejtvény próbálkozás leállítása közben:', error)
        this.error = error?.response?.data?.message ?? error?.message ?? 'Hiba a rejtvény próbálkozás leállítása közben.'
      }
    }
  },
})
