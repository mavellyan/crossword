<template>
  <div class="crossword-wrapper position-relative">
    <div
      class="solver-layout"
      :class="{'blur-background':  !attemptStore.justFinished && !isCrosswordStarted && isLoggedIn }"
    >
      <div class="solver-sidebar">
        <ClueList
          :horizontal-clues="horizontalClueList"
          :vertical-clues="verticalClueList"
          @clue-clicked="clueClicked"
        />
      </div>

      <div class="grid-container">
        <div class="grid-scroll">
          <div
            class="solver-grid"
            :style="{
              '--cols': crosswordStore.width,
              '--rows': crosswordStore.height,
            }"
          >
            <SolverCell
              v-for="cell in gridCells"
              :model-value="crosswordStore.getCellValue(cell.row, cell.col)"
              :key="cell.key"
              :cell="cell"
              :ref="el => setInputRef(cell.key, el)"
              @click="setCellAndEntryActive(cell.row, cell.col, cell.entryIds)"
              @update:modelValue="onCellModelValueUpdate(cell.key, $event)"
              @keydown="onCellKeydown(cell.key, $event)"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { useCrosswordStore } from '../../stores/crossword';
import { useAttemptStore } from '../../stores/attempt';
import { useAuthStore } from '../../stores/auth';
import { mapState } from 'pinia';
import SolverCell from './SolverCell.vue';
import ClueList from './ClueList.vue';

export default {
  name: 'CrosswordGridSolver',
  components: {
    SolverCell,
    ClueList,
  },
  data() {
    return {
      inputRefs: {},
    }
  },
  props: {
    /**
     * A felhasználó elindította-e a rejtvény kitöltését.
     * 
     * @type {boolean}
     */
    isCrosswordStarted: {
      type: Boolean,
      required: true,
    },
  },
  methods: {
    /**
     * Beállítja a megadott cella referenciáját.
     * 
     * @param key - A cella kulcsa.
     * @param el - A cella elem.
     */
    setInputRef(key, el) {
      if (!el) {
        delete this.inputRefs[key]
        return
      }

      this.inputRefs[key] = el
    },
    /**
     * Beállítja a megadott cellát és a kapcsolódó bejegyzést aktívként. Amennyiben a cella több bejegyzéshez is tartozik, váltogat a bejegyzések között.
     * 
     * @param row - A cella sora.
     * @param col - A cella oszlopa.
     * @param entryIds - A cellához tartozó bejegyzésazonosítók tömbje.
     */
    setCellAndEntryActive(row, col, entryIds) {
      const key = this.crosswordStore.cellKey(row, col)
      const wasActive = this.crosswordStore.activeCellKey === key
      
      this.crosswordStore.selectCell(row, col)

      if (entryIds.length === 1) {
        this.crosswordStore.selectEntry(entryIds[0])
        return
      } else {
        // Ha több bejegyzés van, azaz metszéspont, akkor váltogatunk a függőleges és vízszintes bejegyzés között
        if (wasActive) {
          this.crosswordStore.toggleEntryAtActiveCell()
        }
      }
    },
    /**
     * Kiválasztja a megadott bejegyzést, és kiválasztja az első üres cellát a bejegyzésben, vagy ha nincs üres cella, akkor az első cellát.
     * 
     * @param placementId - A kiválasztott bejegyzés azonosítója.
     */
    clueClicked(placementId) {
      this.crosswordStore.selectEntry(placementId)

      const entry = this.crosswordStore.getEntryById(placementId)

      if (!entry?.cells?.length) {
        return
      }

      const target = this.crosswordStore.getFirstEmptyCellInEntry(placementId) ?? entry.cells[0]

      this.crosswordStore.selectCell(target.row, target.col)
      this.$nextTick(() => {
        this.focusInput(target.row, target.col)
      })
    },
    /**
     * Frissíti a cella értékét a store-ban, amikor a SolverCell komponensben változik az érték.
     * 
     * @param cellKey A cella kulcsa, amelyet a store-ban való frissítéshez használunk.
     * @param newValue Az új érték, amelyet a cellához rendelünk.
     */
    onCellModelValueUpdate(cellKey, newValue) {
      if (newValue === '') {
        return
      }

      const [row, col] = cellKey.split(':').map(Number)
      this.crosswordStore.updateCell(row, col, newValue)
      this.focusNextCell(cellKey)
    },
    /**
     * Billentyűleütés események kezelése a cellákban, beleértve a navigációt és a törlést.
     * 
     * @param cellKey A cella kulcsa, amelyet a billentyűleütés kezeléséhez használunk.
     * @param event A billentyűleütés eseménye.
     */
    onCellKeydown(cellKey, event) {
      const [row, col] = cellKey.split(':').map(Number);
      
      if (['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
        event.preventDefault();

        switch (event.key) {
          case 'Delete':
            this.crosswordStore.deleteCell(row, col)
            break
          case 'Backspace':
            if (this.crosswordStore.getCellValue(row, col)) {
              this.crosswordStore.deleteCell(row, col)
              return
            }

            this.focusPreviousCell(cellKey)
            break
          case 'ArrowLeft':
            if (this.crosswordStore.activeDirection === 'vertical') {
              this.crosswordStore.switchDirection(row, col)
              break
            }

            this.focusPreviousCell(cellKey)
            break
          case 'ArrowRight':
            if (this.crosswordStore.activeDirection === 'vertical') {
              this.crosswordStore.switchDirection(row, col)
              break
            }

            this.focusNextCell(cellKey)
            break
          case 'ArrowUp':
            if (this.crosswordStore.activeDirection === 'horizontal') {
              this.crosswordStore.switchDirection(row, col)
              break
            }

            this.focusPreviousCell(cellKey)
            break
          case 'ArrowDown':
            if (this.crosswordStore.activeDirection === 'horizontal') {
              this.crosswordStore.switchDirection(row, col)
              break
            }

            this.focusNextCell(cellKey)
            break
        }
      }
    },
    /**
     * Fókuszál a következő cellára az aktív bejegyzésben, ha van ilyen.
     * 
     * @param cellKey A cella kulcsa, amelyet a következő cellára való fókuszáláshoz használunk.
     */
    focusNextCell(cellKey) {
      const next = this.crosswordStore.getNextCellInEntry(this.crosswordStore.activeEntryId, cellKey)

      if (next) {
        this.crosswordStore.selectCell(next.row, next.col)
        this.focusInput(next.row, next.col)
      }
    },
    /**
     * Fókuszál a korábbi cellára az aktív bejegyzésben, ha van ilyen.
     * 
     * @param cellKey A cella kulcsa, amelyet a korábbi cellára való fókuszáláshoz használunk.
     */
    focusPreviousCell(cellKey) {
      const previous = this.crosswordStore.getPreviousCellInEntry(this.crosswordStore.activeEntryId, cellKey)

      if (previous) {
        this.crosswordStore.selectCell(previous.row, previous.col)
        this.focusInput(previous.row, previous.col)
      }
    },
    /**
     * Fókuszál a megadott cellára a sor és oszlop alapján.
     * 
     * @param row A cella sora, amelyre fókuszálni szeretnénk.
     * @param col A cella oszlopa, amelyre fókuszálni szeretnénk.
     */
    focusInput(row, col) {
      const key = `${row}:${col}`

      if (this.inputRefs[key]) {
        const ref = this.inputRefs[key]

        if (!ref) {
          return
        }

        if (typeof ref.focus === 'function') {
          ref.focus()
          return
        }

        if (typeof ref?.$el?.focus === 'function') {
          ref.$el.focus()
        }
      }
    },
  },
  computed: {
    ...mapState(useAuthStore, ['isLoggedIn']),
    crosswordStore() {
      return useCrosswordStore()
    },
    attemptStore() {
      return useAttemptStore()
    },
    /**
     * A megadott magasság és szélesség alapján létrehoz egy tömböt, amely tartalmazza a rács celláit.
     * 
     * Minden cella objektum tartalmazza a következő tulajdonságokat:
     * - key: A cella egyedi kulcsa ("sor:oszlop" formátumban).
     * - row: A cella sora.
     * - col: A cella oszlopa.
     * - entryIds: A cellához tartozó bejegyzésazonosítók tömbje.
     * - isBlocked: Boolean érték, amely jelzi, hogy a cella blokkolt-e (nincs hozzá bejegyzés).
     * - isIntersection: Boolean érték, amely jelzi, hogy a cella metszéspont-e (több bejegyzéshez tartozik).
     * - isActive: Boolean érték, amely jelzi, hogy a cella aktív-e (a felhasználó éppen ezen a cellán van).
     * - isInActiveEntry: Boolean érték, amely jelzi, hogy a cella az aktív bejegyzés része-e (a felhasználó éppen ezen a bejegyzésen dolgozik)
     * - isFilled: Boolean érték, amely jelzi, hogy a cellához tartozó bejegyzés ki van-e töltve.
     * - isCorrect: Boolean érték, amely jelzi, hogy a cellához tartozó bejegyzés helyes-e.
     * - isPending: Boolean érték, amely jelzi, hogy a cellához tartozó bejegyzés validálása függőben van-e.
     * - isLocked: Boolean érték, amely jelzi, hogy a cellához zárolt-e, azaz valamelyik hozzátartozó bejegyzés helyes-e.
     * - value: A cella aktuális értéke (karakter).
     * 
     * @return {{
     *  key: string,
     *  row: number,
     *  col: number,
     *  entryIds: string[],
     *  isBlocked: boolean,
     *  isIntersection: boolean,
     *  isActive: boolean,
     *  isInActiveEntry: boolean,
     *  isFilled: boolean,
     *  isCorrect: boolean,
     *  isPending: boolean,
     *  isLocked: boolean,
     *  value: string
     * }[]} A rács celláinak tömbje.
     */
    gridCells() {
      const cells = [];

      for (let row = 0; row < this.crosswordStore.height; row++) {
        for (let col = 0; col < this.crosswordStore.width; col++) {
          const key = this.crosswordStore.cellKey(row, col)
          const entryIds = this.crosswordStore.getEntriesForCell(row, col)

          const statusEntryId = this.crosswordStore.activeEntryId !== null &&
                                entryIds.includes(this.crosswordStore.activeEntryId) ?
                                this.crosswordStore.activeEntryId : (entryIds[0] ?? null)
          
          const status = statusEntryId ? this.crosswordStore.entryStatus[statusEntryId] : null

          cells.push({
            key,
            row,
            col,
            entryIds,
            isBlocked: entryIds.length === 0,
            isIntersection: entryIds.length > 1,
            isActive: this.crosswordStore.activeCellKey === key,
            isInActiveEntry: this.crosswordStore.activeEntryId !== null && entryIds.includes(this.crosswordStore.activeEntryId),
            isFilled: Boolean(status?.filled),
            isCorrect: Boolean(status?.correct),
            isPending: Boolean(status?.pending),
            isLocked: entryIds.some(entryId => this.crosswordStore.entryStatus[entryId]?.correct),
            value: this.crosswordStore.getCellValue(row, col),
          })
        }
      }

      return cells;
    },
    /**
     * Visszaadja a vízszintes szavak listáját, amelyeket a ClueList komponens használ.
     * 
     * @return {{
     *  placement_id: string,
     *  definition: string,
     *  startRow: number,
     *  startCol: number,
     *  isActive: boolean
     * }[]} A vízszintes szavak listája.
     */
    horizontalClueList() {
      const horizontalClues = []

      this.crosswordStore.words.forEach(word => {
        if (word.direction === 'horizontal') {
          horizontalClues.push({
            placement_id: String(word.placement_id),
            definition: word.definition,
            startRow: word.start_row,
            startCol: word.start_col,
            isActive: this.crosswordStore.activeEntryId === String(word.placement_id),
          })
        }
      })

      return horizontalClues
    },
    /**
     * Visszaadja a függőleges szavak listáját, amelyeket a ClueList komponens használ.
     *
     * @return {{
     *  placement_id: string,
     *  definition: string,
     *  startRow: number,
     *  startCol: number,
     *  isActive: boolean
     * }[]} A függőleges szavak listája.
     */
    verticalClueList() {
      const verticalClues = []

      this.crosswordStore.words.forEach(word => {
        if (word.direction === 'vertical') {
          verticalClues.push({
            placement_id: String(word.placement_id),
            definition: word.definition,
            startRow: word.start_row,
            startCol: word.start_col,
            isActive: this.crosswordStore.activeEntryId === String(word.placement_id),
          })
        }
      })

      return verticalClues
    },
  },
}
</script>

<style scoped lang="scss">
.solver-layout {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 2rem;
  align-items: flex-start;
  padding: 1rem;
}

.grid-container {
  flex: 0 1 auto;
  max-width: 100%;
  background: #ffffff;
  padding: 1rem;
  border-radius: 0.5rem;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  border: 1px solid #dee2e6;
}

.grid-scroll {
  overflow: auto;
}

.solver-grid {
  display: grid;
  grid-template-columns: repeat(var(--cols), 40px);
  grid-template-rows: repeat(var(--rows), 40px);
  width: max-content;
  border-top: 1px solid #333;
  border-left: 1px solid #333;
}

.solver-sidebar {
  width: 100%;
  max-width: 300px;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.blur-background {
  filter: blur(6px);
  pointer-events: none;
}
</style>