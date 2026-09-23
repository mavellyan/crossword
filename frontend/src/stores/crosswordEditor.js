import { defineStore } from 'pinia'
import { cellsForEntry, buildOccupancy, validateCandidate, validateLayout } from '@/domain/crosswordLayout'

export const useCrosswordEditorStore = defineStore('crosswordEditor', {
  state: () => ({
    /**
     * A rejtvény sávszáma
     * 
     * @type {number}
     */
    rows: 20,
    /**
     * A rejtvény oszlopszáma
     * 
     * @type {number}
     */
    cols: 20,
    /**
     * A rejtvényhez hozzáadott szavak
     * 
     * @type {array<Entry>}
     */
    entries: [],
    /**
     * A következő kliens azonosító, amelyet a szavakhoz rendelünk
     * 
     * @type {number}
     */
    nextClientId: 1,
    /**
     * A kiválasztott cella koordinátái
     * 
     * @type {{ row: number, col: number } | null}
     */
    selectedCell: null,
    /**
     * A kiválasztott szó
     * 
     * @type {number | null}
     */
    selectedClue: null,
    /**
     * A kiválasztott irány
     * 
     * @type {'horizontal' | 'vertical'}
     */
    selectedDirection: 'horizontal',
    /**
     * A szerkesztőben aktív szó azonosítója
     * 
     * @type {number | null}
     */
    activeEntryId: null,
    /**
     * A szerkesztő által észlelt hibák listája
     * 
     * @type {array}
     */
    placementErrors: [],
    /**
     * A szerver által visszaadott hibák listája
     * 
     * @type {array}
     */
    serverErrors: [],
  }),

  getters: {
    /**
     * A rejtvény foglaltsági táblázata az entryk alapján
     * 
     * @param state - A store állapota
     * @returns {Object} - A foglaltsági táblázat, ahol a kulcs a cella sor és oszlop koordinátája, az érték pedig a cella adatai
     */
    occupancy(state) {
      return buildOccupancy(state.entries)
    },
    /**
     * Az elhelyezett szót adja vissza, amennyiben van kiválasztott szó és cella, különben null-t ad vissza
     * 
     * @param state - A store állapota
     * @returns {Object | null} - Az elhelyezett szó, vagy null, ha nincs kiválasztott szó vagy cella
     */
    candidateEntry(state) {
      if (!state.selectedClue || !state.selectedCell) {
        return null
      }

      return {
        clientId: `candidate`,
        id: null,
        clueId: state.selectedClue.id,
        solution: state.selectedClue.solution,
        definition: state.selectedClue.definition,
        direction: state.selectedDirection,
        startRow: state.selectedCell.row,
        startCol: state.selectedCell.col,
      }
    },
    /**
     * Az elhelyezett szóhoz tartozó cellák listája, amennyiben van kiválasztott szó és cella, különben üres tömböt ad vissza
     * 
     * @param state - A store állapota
     * @returns {Array} - Az elhelyezett szóhoz tartozó cellák listája
     */
    candidateCells(state) {
      if (!state.selectedClue || !state.selectedCell) {
        return []
      }

      return cellsForEntry(this.candidateEntry)
    },
    /**
     * A kiválasztott szó validálása
     * 
     * @param state - A store állapota
     * @returns {Object} - A validálási eredmény
     */
    candidateValidation(state) {
      if (!state.selectedClue || !state.selectedCell) {
        return { isValid: true, errors: [], intersectionCount: 0 }
      }

      return validateCandidate(state.entries, this.candidateEntry, state.rows, state.cols)
    },
    /**
     * A rejtvény elrendezésének validálása
     * 
     * @param state - A store állapota
     * @returns {Object} - A validálási eredmény
     */
    layoutValidation(state) {
      return validateLayout(state.entries, state.rows, state.cols)
    },
    /**
     * A használt szóazonosítók listája
     * 
     * @param state - A store állapota
     * @returns {Array} - A használt szóazonosítók listája
     */
    usedClueIds(state) {
      return state.entries.map(entry => entry.clueId)
    },
    /**
     * Ellenőrzi, hogy a kiválasztott szó elhelyezhető-e
     * 
     * @returns {boolean} - Igaz, ha a szó elhelyezhető, hamis egyébként
     */
    canPlaceCandidate() {
      return this.candidateValidation.isValid && this.selectedCell !== null && this.selectedClue !== null
    },
    /**
     * Ellenőrzi, hogy a rejtvény elrendezése menthető-e
     * 
     * @returns {boolean} - Igaz, ha a rejtvény elrendezése menthető, hamis egyébként
     */
    canSaveLayout() {
      return this.layoutValidation.isValid
    },
    /**
     * A jelenleg aktív szó azonosítója alapján visszaadja a hozzá tartozó entry-t, vagy null-t, ha nincs aktív entry
     * 
     * @param state - A store állapota
     * @returns {Entry | null} - A jelenleg aktív entry, vagy null, ha nincs aktív entry
     */
    activeEntry(state) {
      return state.entries.find(entry => entry.clientId === state.activeEntryId) || null
    }
  },

  actions: {
    /**
     * Visszaállítja a szerkesztő állapotát az alapértelmezett értékekre
     */
    resetEditor() {
      this.rows = 20
      this.cols = 20
      this.entries = []
      this.nextClientId = 1
      this.selectedCell = null
      this.selectedClue = null
      this.selectedDirection = 'horizontal'
      this.activeEntryId = null
      this.placementErrors = []
      this.serverErrors = []
    },
    /**
     * Betölti a szavakat a szerkesztőbe
     * 
     * @param {array<Entry>} entries - A rejtvényhez hozzáadni kívánt szavak
     */
    loadEntries(entries) {
      this.resetEditor()

      this.entries = entries.map(entry => ({
        clientId: this.nextClientId++,
        id: entry.id,
        clueId: entry.clue_id,
        solution: entry.solution,
        definition: entry.definition,
        direction: entry.direction,
        startRow: entry.start_row,
        startCol: entry.start_col,
      }))
    },
    /**
     * Kiválaszt egy cellát a rejtvényben
     * 
     * @param {number} row - A kiválasztott sor
     * @param {number} col - A kiválasztott oszlop
     */
    selectCell(row, col) {
      this.selectedCell = { row, col }
    },
    /**
     * Kiválaszt egy szót a rejtvényben
     * 
     * @param {Object} clue
     */
    selectClue(clue) {
      this.selectedClue = clue
    },
    /**
     * Beállítja a kiválasztott irányt
     * 
     * @param {'horizontal' | 'vertical'} direction - A kiválasztott irány
     */
    setDirection(direction) {
      this.selectedDirection = direction
    },
    /**
     * Ellenőrzi, hogy a kiválasztott szó elhelyezhető-e, és ha igen, akkor hozzáadja a rejtvényhez
     * 
     * @returns {boolean} - Igaz, ha a szó elhelyezése sikeres volt, hamis egyébként
     */
    placeCandidate() {
      const candidate = this.candidateEntry

      if (!candidate) {
        return false
      }

      const result = validateCandidate(this.entries, candidate, this.rows, this.cols)

      if (!result.isValid) {
        this.placementErrors = result.errors
        return false
      }

      this.entries.push({
        ...candidate,
        clientId: this.nextClientId++,
      })

      this.selectedCell = null
      this.selectedClue = null
      this.placementErrors = []
      return true
    },
    /**
     * Törli a megadott azonosítóval rendelkező szót a rejtvényből
     * 
     * @param {number} clientId - A törölni kívánt szó azonosítója
     */
    removeEntry(clientId) {
      this.entries = this.entries.filter(entry => entry.clientId !== clientId)

      if (this.activeEntryId === clientId) {
        this.activeEntryId = null
      }

      this.serverErrors = []
      this.placementErrors = []
    },
    /**
     * Kiválaszt egy szót a rejtvényben
     * 
     * @param {number} clientId - A kiválasztani kívánt szó azonosítója
     */
    selectEntry(clientId) {
      this.activeEntryId = clientId
    },
    /**
     * Törli a kiválasztott cellát, szót és aktív entry-t
     */
    clearSelection() {
      this.selectedCell = null
      this.selectedClue = null
      this.activeEntryId = null
      this.placementErrors = []
    },
    /**
     * Átalakítja a rejtvény szavait a szerver által elvárt formátumba
     * 
     * @returns {array} - A rejtvény szavainak listája a szerver által elvárt formátumban
     */
    toApiEntries() {
      return this.entries.map(entry => ({
        clue_id: entry.clueId,
        direction: entry.direction,
        start_row: entry.startRow,
        start_col: entry.startCol,
      }))
    },
  }
})