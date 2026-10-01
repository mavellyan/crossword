import { defineStore } from 'pinia'
import { loadCrossword, validateEntryForGuest } from '@/services/crosswordApi'
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

export const useCrosswordStore = defineStore('crossword', {
  state: () => ({
    /**
     * A store-ban tárolt rejtvény adatok és játékállapotok.
     * A store minden mezője alapértelmezett értékre van állítva, hogy a komponensek ne kapjanak undefined értékeket.
     * A rejtvény betöltésekor a loadCrossword() hívás után az initializePlayState() metódus inicializálja a store-t a backendről érkező adatokkal.
     */

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
     * A felhasználó által beírt karakterek minden szóhoz. Az eltárolt adatok "sor:oszlop" : "karakter" formátumban vannak.
     * Pl. { '2:1': 'A', '2:2': 'L' }
     * 
     * @type {Object<string, string>}
     */
    cellInputs: {},
    /**
     * A rejtvényben található szavak id-je és állapotai, amelyek jelzi, hogy a szó teljesen ki van-e töltve, helyes-e, vagy éppen ellenőrzés alatt áll.
     * Pl. { '12': { filled: true, correct: false, pending: false } }
     * 
     * @type {Object<string, { filled: boolean, correct: boolean, pending: boolean }>}
     */
    entryStatus: {},
    /**
     * Az éppen aktuális szó indexe, amelyre a felhasználó fókuszál. Ha nincs aktív szó, akkor null.
     * 
     * @type {string|null}
     */
    activeEntryId: null,
    /**
     * Az éppen aktív cella indexe. Ha nincs aktív cella, akkor null. Formátuma: "sor:oszlop".
     * 
     * @type {string|null}
     */
    activeCellKey: null,
    /**
     * Az éppen aktív irány, amely meghatározza, hogy a felhasználó vízszintesen vagy függőlegesen írja-e be a karaktereket. Ha nincs aktív irány, akkor null.
     * 
     * @type {'horizontal'|'vertical'|null}
     */
    activeDirection: null,
    /**
     * A rejtvény celláinak tartalmazott szavai, ahol a kulcs a cella koordinátája "sor:oszlop" formátumban,
     * az érték pedig az adott cellában található szó vagy szavak id-je, amennyiben az egy metszéspont.
     * Pl. { '2:2' : [12, 17] }
     * 
     * @type {Object<string, Array<number>>}
     */
    cellEntries: {},
    /**
     * A rejtvény szavainak adatai, ahol a kulcs a szó id-je, az érték pedig a szó objektuma.
     * Pl. { '12': entryObject, '17': entryObject }
     * 
     * @type {Object<string, Entry>}
     */
    entriesById: {},
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
     * Hibaüzenet, ha a rejtvény betöltése vagy mentése közben hiba történt. Ha null, akkor nincs hiba.
     * 
     * @type {string|null}
     */
    error: null,
  }),

  getters: {
    isCompleted(state) {
      return Array.isArray(state.words) &&
        state.words.length >0 &&
        state.words.every(entry => state.entryStatus[String(entry.placement_id)]?.correct) === true
    }
  },

  actions: {
    /**
     * Törli a store-ból az összes rejtvény adatot és játékállapotot.
     *
     * @returns {void}
     */
    resetState() {
      if (this.saveTimer !== null) {
        clearTimeout(this.saveTimer)
      }

      this.saveTimer = null

      this.id = null
      this.grid = null
      this.title = null
      this.creator = null
      this.mainSolution = null
      this.words = null
      this.width = null
      this.height = null

      this.cellInputs = {}
      this.cellEntries = {}
      this.entriesById = {}
      this.entryStatus = {}

      this.activeEntryId = null
      this.activeCellKey = null
      this.activeDirection = null

      this.loading = false
      this.error = null
    },

    /**
     * Visszaállítja a vendég felhasználó játékállapotát, azaz törli a cella inputokat és a szavak állapotát.
     * 
     * @returns {void}
     */
    guestReset() {
      this.cellInputs = {}
      this.entryStatus = {}
      this.activeEntryId = null
      this.activeCellKey = null
      this.activeDirection = null
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

      this.buildEntryMaps()
    },

    /**
     * A rejtvény szavainak adataiból felépíti az entriesById és cellEntries objektumokat.
     * Ez a metódus a rejtvény betöltésekor hívódik meg, hogy a store-ban tárolt adatokból gyorsan elérhető legyen, hogy melyik cellában melyik szó található.
     *
     * @returns {void}
     */
    buildEntryMaps() {
      this.entriesById = Object.fromEntries(
        this.words.map(entry => [
          String(entry.placement_id),
          entry,
        ])
      )

      const cellEntries = {}

      this.words.forEach(entry => {
        entry.cells.forEach(cell => {
          const key = this.cellKey(cell.row, cell.col)

          if (!cellEntries[key]) {
            cellEntries[key] = []
          }

          cellEntries[key].push(String(entry.placement_id))
        })
      })

      this.cellEntries = cellEntries
    },
    /**
     * Létrehoz egy kulcsot a cella pozíciója alapján.
     * 
     * @param {number} row 
     * @param {number} col 
     * @returns {string}
     */
    cellKey(row, col) {
      return `${row}:${col}`
    },
    /**
     * Egy adott cella értékét adja vissza.
     * 
     * @param {number} row 
     * @param {number} col 
     * @returns {string}
     */
    getCellValue(row, col) {
      return this.cellInputs[this.cellKey(row, col)] ?? ''
    },
    /**
     * Egy adott szó adatait adja vissza.
     * 
     * @param {string} entryId 
     * @returns {object|null}
     */
    getEntryById(entryId) {
      return this.entriesById[String(entryId)] ?? null
    },
    /**
     * Egy adott cellához tartozó szavak id-jeit adja vissza. Ha a cella nem tartozik egyetlen szóhoz sem, üres tömböt ad vissza.
     * 
     * @param {number} row 
     * @param {number} col 
     * @returns {Array}
     */
    getEntriesForCell(row, col) {
      return this.cellEntries[this.cellKey(row, col)] ?? []
    },
    /**
     * Visszaállítja a próbálkozás állapotát a backendről érkező adatok alapján. A cella inputokat és a helyes bejegyzések id-jeit frissíti.
     * 
     * @param {Object} param0 
     */
    applyAttemptState({ cellInputs = {}, correctEntryIds = [] }) {
      const validCellKeys = new Set(Object.keys(this.cellEntries))

      this.cellInputs = Object.fromEntries(
        Object.entries(cellInputs)
          .filter(([key]) => validCellKeys.has(key))
          .map(([key, values]) => [
            key,
            normalizeLetter(values),
          ])
      )

      this.updateEntryStatuses(correctEntryIds)
    },
    /**
     * Visszaadja egy adott szóhoz tartozó cellákból összerakott felhasználói inputot. Ha a szó nem található, üres stringet ad vissza.
     * 
     * @param {string} entryId 
     * @returns {string}
     */
    getEntryInput(entryId) {
      const entry = this.getEntryById(String(entryId))

      if (!entry) {
        return ''
      }

      return entry.cells.map(cell => this.getCellValue(cell.row, cell.col)).join('')
    },
    /**
     * Frissíti a szavak állapotát a helyes bejegyzések alapján.
     * 
     * @param {Array<number>} correctEntryIds 
     */
    updateEntryStatuses(correctEntryIds) {
      const correctSet = new Set(correctEntryIds.map(id => String(id)))

      this.entryStatus = Object.fromEntries(
        this.words.map(entry => {
          const values = entry.cells.map(cell => this.getCellValue(cell.row, cell.col))

          return [
            String(entry.placement_id),
            {
              filled: values.every(value => value !== ''),
              correct: correctSet.has(String(entry.placement_id)),
              pending: false,
            }
          ]
        })
      )
    },
    /**
     * Egy adott cellához tartozó szavak állapotát frissíti a cella pozíciója alapján. A szavak állapotát a cellák értékei alapján határozza meg.
     * 
     * @param {number} row 
     * @param {number} col 
     */
    refreshEntriesAtCell(row, col) {
      this.getEntriesForCell(row, col).forEach(entryId => {
        const entry = this.getEntryById(entryId)

        if (!entry) {
          return
        }

        const values = entry.cells.map(cell => this.getCellValue(cell.row, cell.col))

        const key = String(entryId)

        this.entryStatus[key] = {
          filled: values.every(value => value !== ''),
          correct: false,
          pending: values.every(value => value !== ''),
        }
      })
    },
    /**
     * Frissíti egy adott cella tartalmát.
     * 
     * @param {number} row 
     * @param {number} col 
     * @param {string} value 
     * @returns {void}
     */
    updateCell(row, col, value) {
      const key = this.cellKey(row, col)

      if (!(key in this.cellEntries)) {
        return
      }

      const affectedEntryIds = this.getEntriesForCell(row, col)

      this.cellInputs[key] = normalizeLetter(value)
      this.refreshEntriesAtCell(row, col)

      const authStore = useAuthStore()

      if (!authStore.isLoggedIn) {
        affectedEntryIds.forEach(entryId => {
          const status = this.entryStatus[String(entryId)]

          if (status?.filled && !status?.correct) {
            this.validateGuestEntry(entryId).catch(error => {
              console.error('Hiba a szó ellenőrzése közben:', error)
            })
          }
        })

        return
      }

      const attemptStore = useAttemptStore()
      attemptStore.modified = true

      const hasFilledAffectedEntry = affectedEntryIds.some(entryId => { return this.entryStatus[String(entryId)]?.filled === true })

      if (hasFilledAffectedEntry) {
        void attemptStore.requestImmediateSave()
        return
      }

      this.scheduleSave()
    },
    /**
     * Törli egy adott cella tartalmát.
     * 
     * @param {number} row 
     * @param {number} col 
     * @returns {void}
     */
    deleteCell(row, col) {
      const key = this.cellKey(row, col)

      if (!(key in this.cellEntries)) {
        return
      }
      
      this.cellInputs[key] = ''
      this.refreshEntriesAtCell(row, col)

      if (!useAuthStore().isLoggedIn) {
        return
      }

      const attemptStore = useAttemptStore()
      attemptStore.modified = true

      this.scheduleSave()
    },
    /**
     * Kijelöli a megadott szót.
     * 
     * @param {string} entryId 
     * @returns 
     */
    selectEntry(entryId) {
      const normalizedEntryId = String(entryId)

      const entry = this.getEntryById(normalizedEntryId)

      if (!entry) {
        return
      }

      this.activeEntryId = normalizedEntryId
      this.activeDirection = entry.direction
    },
    /**
     * Kijelöli a megadott cellát.
     * 
     * @param {number} row 
     * @param {number} col 
     * @returns 
     */
    selectCell(row, col) {
      const entryIds = this.getEntriesForCell(row, col)

      if (!entryIds.length) {
        return
      }

      this.activeCellKey = this.cellKey(row, col)

      if (!entryIds.includes(this.activeEntryId)) {
        this.selectEntry(entryIds[0])
      }
    },
    /**
     * A metszéspontban lévő szavak között váltogat az aktív cella alapján. Ha nincs aktív cella, vagy a cellában csak egy szó van, akkor nem történik semmi.
     * 
     * @returns {void}
     */
    toggleEntryAtActiveCell() {
      if (!this.activeCellKey) {
        return
      }

      const entryIds = this.cellEntries[this.activeCellKey] ?? []

      if (entryIds.length < 2) {
        return
      }
      
      const currentIndex = entryIds.indexOf(this.activeEntryId)
      const nextIndex = currentIndex === -1 ? 0 : (currentIndex + 1) % entryIds.length

      this.selectEntry(entryIds[nextIndex])
    },
    /**
     * Megváltoztatja az aktív irányt, hogyha a megadott sor és oszlop cellája több szót is tartalmaz. Ha a cellában csak egy szó van, akkor nem történik semmi.
     * 
     * @param {number} row A sor indexe, amely alapján meghatározzuk, hogy a cella több szót tartalmaz-e.
     * @param {number} col Az oszlop indexe, amely alapján meghatározzuk, hogy a cella több szót tartalmaz-e. 
     */
    switchDirection(row, col) {
      if (this.getEntriesForCell(row, col).length > 1) {
        this.toggleEntryAtActiveCell()
      }
    },
    /**
     * Visszaadja a megadott cella indexét a megadott szóban.
     *
     * @param {string} entryId A szó azonosítója.
     * @param {string} cellKey A cella kulcsa.
     * @returns {number} A cella indexe a szóban, vagy -1, ha nem található.
     */
    getCellIndexInEntry(entryId, cellKey) {
      const entry = this.getEntryById(String(entryId))

      if (!entry) {
        return -1
      }

      return entry.cells.findIndex(cell => this.cellKey(cell.row, cell.col) === cellKey)
    },
    /**
     * Visszaadja a megadott szó következő cellájának kulcsát.
     *
     * @param {string} entryId A szó azonosítója.
     * @param {string} currentCellKey A jelenlegi cella kulcsa.
     * @returns {{ row: number, col: number }|null} A következő cella koordinátái, vagy null, ha nincs következő cella.
     */
    getNextCellInEntry(entryId, currentCellKey) {
      const entry = this.getEntryById(String(entryId))

      if (!entry) {
        return null
      }

      const currentIndex = this.getCellIndexInEntry(String(entryId), currentCellKey)

      if (currentIndex === -1 || currentIndex === entry.cells.length - 1) {
        return null
      }

      const nextCell = entry.cells[currentIndex + 1]
      return { row: nextCell.row, col: nextCell.col }
    },
    /**
     * Visszaadja a megadott szó előző cellájának kulcsát.
     *
     * @param {string} entryId A szó azonosítója.
     * @param {string} currentCellKey A jelenlegi cella kulcsa.
     * @returns {{ row: number, col: number }|null} Az előző cella koordinátái, vagy null, ha nincs előző cella.
     */
    getPreviousCellInEntry(entryId, currentCellKey) {
      const entry = this.getEntryById(String(entryId))

      if (!entry) {
        return null
      }

      const currentIndex = this.getCellIndexInEntry(String(entryId), currentCellKey)

      if (currentIndex <= 0) {
        return null
      }

      const previousCell = entry.cells[currentIndex - 1]
      return { row: previousCell.row, col: previousCell.col }
    },
    /**
     * Visszaadja a megadott szó első üres cellájának kulcsát.
     *
     * @param {string} entryId A szó azonosítója.
     * @returns {{ row: number, col: number }|null} Az első üres cella koordinátái, vagy null, ha nincs üres cella.
     */
    getFirstEmptyCellInEntry(entryId) {
      const entry = this.getEntryById(String(entryId))

      if (!entry) {
        return null
      }
      
      for (const cell of entry.cells) {
        const key = this.cellKey(cell.row, cell.col)

        if (!(key in this.cellInputs) || this.cellInputs[key] === '') {
          return { row: cell.row, col: cell.col }
        }
      }
      return null
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
        this.error = error?.message ?? 'Hiba a rejtvény betöltése közben.'
      } finally {
        this.loading = false
      }
    },

    /**
     * Elkészíti a mentési payloadot a backendnek a cellainputokból. Csak akkor van jelentősége, ha a user be van jelentkezve.
     * 
     * @returns {Object} Mentési payload a backendnek.
     */
    createProgressPayload() {
      return { ...this.cellInputs }
    },
    /**
     * Ütemezi a rejtvény mentését 1,5 másodperccel a legutóbbi változtatás után.
     * 
     * @returns {void}
     */
    scheduleSave() {
      const authStore = useAuthStore()

      if (!authStore.isLoggedIn) {
        return
      }

      if (this.saveTimer !== null) {
        clearTimeout(this.saveTimer)
      }
      
      this.saveTimer = setTimeout(() => {
        this.saveTimer = null
        useAttemptStore().saveProgress()
      }, 1500)
    },
    /**
     * Törli az ütemezett mentést, ha van. Ha nincs ütemezett mentés, akkor nem történik semmi.
     * 
     * @returns {void}
     */
    cancelScheduledSave() {
      if (this.saveTimer !== null) {
        clearTimeout(this.saveTimer)
        this.saveTimer = null
      }
    },
    /**
     * A vendégek számára validálja a szavakat.
     * 
     * @param {string} entryId A szó azonosítója.
     * @returns {Promise<void>}
     */
    async validateGuestEntry(entryId) {
      const key = String(entryId)
      const userInput = this.getEntryInput(key)

      if (!this.entryStatus[key]?.filled) {
        return
      }

      const submittedInput = userInput

      this.entryStatus[key].pending = true

      try {
        const isCorrect = await validateEntryForGuest(this.id, key, submittedInput)

        // Ha a felhasználó közben megváltoztatta a cellák értékét, akkor nem frissítjük az állapotot, mert az már nem releváns.
        if (this.getEntryInput(key) !== submittedInput) {
          return
        }

        this.entryStatus[key] = {
          filled: true,
          correct: isCorrect,
          pending: false,
        }
        
      } catch (error) {
        if (this.getEntryInput(key) === submittedInput) {
          this.entryStatus[key].pending = false
        }

        console.error('Hiba a szó ellenőrzése közben:', error)
        throw new Error(error?.message ?? 'Nem sikerült ellenőrizni a szót.')
      }
    },
  },
})