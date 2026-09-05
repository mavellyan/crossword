import { defineStore } from 'pinia'
import { loadCrossword, validateWordForGuest } from '@/services/crosswordApi'
import { useAttemptStore } from '@/stores/attempt'
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
  return (words ?? []).map(() => ({ filled: false, correct: false, pending: false }))
}

export const useCrosswordStore = defineStore('crossword', {
  state: () => ({
    /**
     * A store-ban tárolt rejtvény adatok és játékállapotok.
     * A store minden mezője alapértelmezett értékre van állítva, hogy a komponensek ne kapjanak undefined értékeket.
     * A rejtvény betöltésekor a loadCrossword() hívás után az initializePlayState() metódus inicializálja a store-t a backendről érkező adatokkal.
     */

    attemptStore: useAttemptStore(),
    authStore: useAuthStore(),
    /**
     * A rejtvény azonosítója
     * 
     * @type {string|null}
     */
    id: null,
    /**
     * A rejtvény rácsa, amely egy kétdimenziós tömb, ahol minden cella lehet üres vagy tartalmazhat egy karaktert.
     * 
     * @type {Array<Array<unknown>>|null}
     */
    grid: null,
    /**
     * A rejtvény címe
     * 
     * @type {string|null}
     */
    title: null,
    /**
     * A rejtvény készítőjének neve
     * 
     * @type {string|null}
     */
    creator: null,
    /**
     * A rejtvény fő megoldása
     * 
     * @type {string|null}
     */
    mainSolution: null,
    /**
     * A rejtvény szavai, amelyek tartalmazzák a cellák elhelyezkedését és a megoldásokat.
     * 
     * @type {Array<unknown>|null}
     */
    words: null,
    /**
     * A rejtvény szélessége
     * 
     * @type {number|null}
     */
    width: null,
    /**
     * A rejtvény magassága
     * 
     * @type {number|null}
     */
    height: null,
    /**
     * A felhasználó által beírt karakterek minden szóhoz. Minden szóhoz tartozik egy tömb, amely a szó celláinak számával egyezik.
     * 
     * @type {Array<Array<string>>}
     */
    wordInputs: [],
    /**
     * A felhasználó által beírt karakterek állapota minden szóhoz. Minden szóhoz tartozik egy objektum, amely jelzi,
     * hogy a szó teljesen ki van-e töltve, helyes-e, és van-e folyamatban lévő mentés.
     * 
     * @type {Array<{ filled: boolean, correct: boolean, pending: boolean }>}
     */
    wordStatus: [],

    /**
     * Az aktív szó indexe a words tömbben.
     * 
     * @type {number|null}
     */
    activeWordIndex: null,
    /**
     * Az aktív cella indexe minden szóhoz. Minden szóhoz tartozik egy szám, amely az aktív cella indexét jelzi a szó celláinak tömbjében.
     * 
     * @type {Array<number>}
     */
    activeCellByWord: [],
    /**
     * A mentés ütemezéséhez használt timer azonosítója. Ha null, akkor nincs ütemezett mentés.
     * Módosítás esetén 1,5 másodperces debounce timer indul, ha közben újabb módosítás történik, akkor a timer újraindul.
     * Ha a timer lejár, akkor a rejtvény mentése megtörténik. Ha egy sort kitölt a user,
     * akkor automatikusan instant mentés történik, hogy ne érződjön "laggosnak" a felhasználó részéről.
     * 
     * @type {number|null}
     */
    saveTimer: null,
    /**
     * Jelzi, hogy a rejtvény betöltése folyamatban van-e. Ha true, akkor a komponensek betöltési állapotot jelenítenek meg.
     * 
     * @type {boolean}
     */
    loading: false,
    /**
     * Jelzi, hogy a rejtvény mentése folyamatban van-e. Ha true, akkor a komponensek mentési állapotot jelenítenek meg.
     * 
     * @type {boolean}
     */
    saving: false,
    /**
     * Hibaüzenet, ha a rejtvény betöltése vagy mentése közben hiba történt. Ha null, akkor nincs hiba.
     * 
     * @type {string|null}
     */
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

      this.wordInputs = []
      this.wordStatus = []

      this.activeWordIndex = null
      this.activeCellByWord = []

      this.saveTimer = null

      this.loading = false
      this.error = null
    },

    /**
     * A betöltött rejtvény adatokból inicializálja a futásidejű játékállapot tömböket.
     *
     * @param {object} crossword Betöltött rejtvény objektum.
     * @param {object|null} attempt Felhasználói próbálkozás objektum, ha van. Amennyiben nincs regisztrálva a felhasználó, null.
     * @returns {void}
     */
    initializePlayState(crossword) {
      this.id = crossword.id
      this.grid = crossword.grid ?? null
      this.title = crossword.title
      this.creator = crossword.creator
      this.mainSolution = crossword.main_solution ?? null
      this.words = crossword.words ?? []
      this.width = crossword.width ?? null
      this.height = crossword.height ?? null

      this.wordInputs = createWordInputs(this.words, this.attemptStore.wordInputs ?? {})
      this.wordStatus = createWordStatus(this.words)

      // Validálni kell, mivel lehet, hogy volt korábbi próbálkozás már
      this.validateWords()

      this.activeWordIndex = this.words?.length ? 0 : null
      this.activeCellByWord = this.wordInputs.map(() => 0)
    },

    /**
     * Frissít egy adott cellát egy szóban.
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
      this.attemptStore.modified = true

      if (this.wordInputs[wordIndex].join('').length === this.words[wordIndex].cells.length) {
        this.wordStatus[wordIndex].pending = true

        if (this.authStore.isLoggedIn) {
          this.attemptStore.saveProgress()
        } else {
          this.validateGuestWord(wordIndex)
        }
      }

      this.scheduleSave()
    },

    /**
     * Törli a karaktert egy adott szó cellájából.
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

      if (this.wordInputs[wordIndex].join('').length < this.words[wordIndex].cells.length) {
        this.wordStatus[wordIndex].filled = false
      }

      this.attemptStore.modified = true
      this.scheduleSave()
    },

    /**
     * Backenden validáljuk a megoldásokat, és visszaadjuk a helyesen kitöltött szavak indexeit. Frontenden ez alapján jelöljük a hibás és helyes szavakat.
     * Egy szó csak akkor lehet helyesnek vagy hibásnak jelölve, ha teljesen ki van töltve.
     *
     * @returns {void}
     */
    validateWords() {
      this.words.forEach((word, wordIndex) => {
        const isCorrect = this.attemptStore.correctWords.includes(word.placement_id)

        this.wordStatus[wordIndex] = {
          filled: this.wordInputs[wordIndex].every(cell => cell !== ''),
          correct: isCorrect,
          pending: false,
        }
      })
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
        const crossword = await loadCrossword(id)

        this.initializePlayState(crossword)
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
     * Ütemezi a rejtvény mentését 1,5 másodperccel a legutóbbi változtatás után.
     * 
     * @returns {void}
     */
    scheduleSave() {
      if (!this.authStore.isLoggedIn) {
        return
      }

      if (this.saveTimer) {
        clearTimeout(this.saveTimer)
      }
      
      this.saveTimer = setTimeout(() => {
        this.attemptStore.saveProgress()
      }, 1500)
    },
    async validateGuestWord(wordIndex) {
      const userInput = this.wordInputs[wordIndex].join('')

      try {
        const isCorrect = await validateWordForGuest(this.id, wordIndex, userInput)

        this.wordStatus[wordIndex].correct = isCorrect
        this.wordStatus[wordIndex].filled = true
        
        this.wordStatus[wordIndex].pending = false
      } catch (error) {
        console.log('Hiba a vendég szó validálásakor:', error)
      }
    },
  },
})
