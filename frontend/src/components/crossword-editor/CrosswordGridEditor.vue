<template>
  <div class="editor-layout">
    <div class="grid-container">
      <div class="grid-scroll">
        <div class="editor-grid">
          <EditorCell
            v-for="cell in gridCells"
            :key="cell.key"
            :cell="cell"
            :disabled="isReadOnly"
            @select="handleCellSelect"
          />
        </div>
      </div>
    </div>

    <div class="editor-sidebar">
      <EntryPlacementPanel
        :words="words"
        :words-loading="wordsLoading"
        :words-error="wordsError"
        :is-read-only="isReadOnly"
        @add-clue="$emit('add-clue')"
      />

      <EntryList :is-read-only="isReadOnly" />
    </div>
  </div>
</template>

<script>
import EditorCell from './EditorCell.vue'
import EntryPlacementPanel from './EntryPlacementPanel.vue'
import EntryList from './EntryList.vue'
import { useCrosswordEditorStore } from '../../stores/crosswordEditor.js'
import { cellsForEntry } from '../../domain/crosswordLayout.js'

export default {
  name: 'CrosswordGridEditor',
  components: {
    EditorCell,
    EntryPlacementPanel,
    EntryList,
  },
  props: {
    words: {
      type: Array,
      required: true,
    },
    wordsLoading: Boolean,
    wordsError: String,
    isReadOnly: Boolean,
  },
  emits: ['add-clue'],
  computed: {
    editor() {
      return useCrosswordEditorStore()
    },
    /**
     * Visszaadja a rács celláinak listáját, amelyeket a szerkesztő jelenleg megjelenít.
     * Minden cella objektum tartalmazza a következő tulajdonságokat:
     * - key: Egyedi azonosító a cellához ("row:col").
     * - row: A cella sora.
     * - col: A cella oszlopa.
     * - letter: A cellában lévő betű.
     * - entryIds: Az entry ID-k, amelyek a cellában szerepelnek.
     * - isOccupied: Igaz, ha a cella foglalt.
     * - isIntersection: Igaz, ha a cellában két entry metszi egymást.
     * - isSelected: Igaz, ha a cella jelenleg ki van választva.
     * - isActive: Igaz, ha a cella aktív.
     * - isCandidate: Igaz, ha a cella jelölt.
     * - isCandidateValid: Igaz, ha a cella jelölt és érvényes.
     * - isCandidateInvalid: Igaz, ha a cella jelölt és érvénytelen.
     */
    gridCells() {
      const candidateCells = Object.fromEntries(
        this.editor.candidateCells.map(cell => [
          `${cell.row}:${cell.col}`,
          cell,
        ]),
      )

      const activeCells = new Set(
        this.editor.activeEntry
          ? cellsForEntry(this.editor.activeEntry)
              .map(cell => `${cell.row}:${cell.col}`)
          : [],
      )

      const candidateValid = this.editor.candidateValidation.isValid

      const cells = []

      for (let row = 0; row < this.editor.rows; row++) {
        for (let col = 0; col < this.editor.cols; col++) {
          const key = `${row}:${col}`
          const occupied = this.editor.occupancy[key]
          const candidate = candidateCells[key]

          cells.push({
            key,
            row,
            col,
            letter: occupied?.letter ?? candidate?.letter ?? '',
            entryIds: occupied?.entries ?? [],
            isOccupied: Boolean(occupied),
            isIntersection: (occupied?.entries?.length ?? 0) > 1,
            isSelected: this.editor.selectedCell?.row === row && this.editor.selectedCell?.col === col,
            isActive: activeCells.has(key),
            isCandidate: Boolean(candidate),
            isCandidateValid: Boolean(candidate) && candidateValid,
            isCandidateInvalid: Boolean(candidate) && !candidateValid,
          })
        }
      }

      return cells
    },
  },
  methods: {
    /**
     * Kezeli a cella kiválasztását a felhasználó által. Ha a szerkesztő írásvédett módban van, akkor nem történik semmi.
     * Ellenkező esetben a kiválasztott cella koordinátáit átadja a szerkesztőnek, hogy frissítse a kiválasztott cellát.
     * 
     * @param cell - A user által választott cella
     */
    handleCellSelect(cell) {
      if (this.isReadOnly) {
        return
      }

      this.editor.selectCell(cell.row, cell.col)
    },
  },
}
</script>

<style scoped lang="scss">
.editor-layout {
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

.editor-grid {
  display: grid;
  grid-template-columns: repeat(20, 34px);
  grid-template-rows: repeat(20, 34px);
  width: max-content;
  border-top: 1px solid #333;
  border-left: 1px solid #333;
}

.editor-sidebar {
  width: 100%;
  max-width: 420px;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}
</style>