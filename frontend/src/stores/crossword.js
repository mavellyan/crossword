import { defineStore } from 'pinia'
import { fetchCrosswordById, saveCrosswordProgress } from '@/services/crosswordApi'

/**
 * Egy nyers cella inputot egyetlen nagybetűs karakterre normalizál.
 * A whitespace karaktereket eltávolítja, hogy ne lehessen üres helyet beírni.
 *
 * @param {unknown} value Input eseményből érkező nyers érték.
 * @returns {string} Normalizált egykarakteres érték vagy üres string.
 */
function normalizeLetter(value) {
  if (!value) {
    return ''
  }

  return String(value).replace(/\s/g, '').slice(0, 1).toUpperCase()
}

/**
 * Létrehozza a futásidejű input mátrixot az összes szóhoz.
 * Minden szó kap egy tömböt, ami a cellaszámával egyezik.
 *
 * @param {Array<{ cells: Array<unknown> }>|null|undefined} words Rejtvény szavak.
 * @returns {string[][]} Szavankénti üres felhasználói input tömbök.
 */
function createWordInputs(words) {
  return (words ?? []).map((word) => Array(word.cells.length).fill(''))
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
    status: null,
    mainSolution: null,
    words: null,
    width: null,
    height: null,
    wordInputs: [],
    wordStatus: [],
    activeWordIndex: null,
    activeCellByWord: [],
    loading: false,
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
      this.status = null
      this.mainSolution = null
      this.words = null
      this.width = null
      this.height = null
      this.wordInputs = []
      this.wordStatus = []
      this.activeWordIndex = null
      this.activeCellByWord = []
      this.loading = false
      this.error = null
    },

    /**
     * A betöltött rejtvény adatokból inicializálja a futásidejű játékállapot tömböket.
     *
     * @param {{ grid: Array<Array<string>>, main_solution?: string|null, words?: Array<{ cells: Array<unknown> }>, width?: number|null, height?: number|null }|null} [crossword=null] Opcionális crossword payload.
     * @returns {void}
     */
    initializePlayState(crossword = null) {
      if (crossword) {
        this.grid = crossword.grid
        this.title = crossword.title
        this.creator = crossword.creator
        this.status = crossword.status
        this.mainSolution = crossword.main_solution ?? null
        this.words = crossword.words ?? []
        this.width = crossword.width ?? null
        this.height = crossword.height ?? null
      }

      this.wordInputs = createWordInputs(this.words)
      this.wordStatus = createWordStatus(this.words)
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
      this.loading = true
      this.error = null

      try {
        const crossword = await fetchCrosswordById(id)

        this.id = id
        this.initializePlayState(crossword)
      } catch (error) {
        console.log('error: ', error)
        this.error = error?.message ?? 'Hiba a rejtvény betöltése közben.'
      } finally {
        this.loading = false
      }
    },

    /**
     * Elmenti a rejtvény aktuális állapotát. Ha a rejtvény már be van fejezve, nem történik mentés.
     * 2 másodpercenként fut
     * 
     * @returns {Promise<void>}
     */
    async saveProgress() {
      if (this.status === 'completed') {
        console.log('A rejtvény már be van fejezve, nem lehet menteni a folyamatot.')
        return
      }

      const words = this.wordInputs.map((cells) => cells.join(''))

      const response = await saveCrosswordProgress(this.id, words, this.grid)

      this.status = response.status

      console.log('resp: ', response) // Szavakat backendre, majd visszaadjuk h tartozik-e attempt, ha igen visszaadjuk szavakat,
      // betöltésnél spliteljük, berakjuk wordinputsba, nyomunk egy checket SHABAMM
    },
  },
})
