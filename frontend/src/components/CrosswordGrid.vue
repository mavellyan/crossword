<template>
  <div v-if="words !== null" class="layout justify-content-center">
    <div class="grid">
      <div
        v-for="(word, wordIndex) in words"
        :key="wordIndex"
        class="grid-row"
        :class="{ 'active-row': store.activeWordIndex === wordIndex }"
      >
        <div
          v-for="(cell, visibleCellIndex) in getCellsForWord(word)"
          :key="visibleCellIndex"
          class="cell"
        >
          <div v-if="cell.type === 'black'" class="black" />
          <CrosswordCell
            v-else
            class="input"
            :model-value="getCellValue(wordIndex, cell.cellIndex)"
            :is-correct="isWordCorrect(wordIndex)"
            :is-row-filled="isWordFilled(wordIndex)"
            :ref="(el) => setInputRef(el, wordIndex, cell.cellIndex)"
            @update:modelValue="(value) => handleCellInput(wordIndex, cell.cellIndex, value)"
            @keydown="(event) => handleKeydown(event, wordIndex, cell.cellIndex)"
            @click="setActiveWordAndCell(wordIndex, cell.cellIndex)"
          />
        </div>
      </div>
    </div>

    <div class="definitions">
      <h3>Definiciok</h3>
      <div
        v-for="(word, index) in words"
        :key="index"
        class="definition"
        :class="{ 'active-row': store.activeWordIndex === index }"
        @click="handleDefinitionClick(index)"
      >
        <strong>{{ index + 1 }}.</strong> {{ word.definition }}
      </div>
    </div>
  </div>
</template>

<script>
import { useCrosswordStore } from '../stores/crossword'
import CrosswordCell from './CrosswordCell.vue'

export default {
  name: 'CrosswordGrid',
  components: {
    CrosswordCell,
  },
  props: {
    grid: {
      type: Array,
      required: true,
    },
    words: {
      type: Array,
      required: true,
    },
    mainSolution: {
      type: String,
      required: false,
      default: '',
    },
    width: {
      type: Number,
      required: true,
    },
    height: {
      type: Number,
      required: true,
    },
  },
  data() {
    return {
      inputRefs: {},
    }
  },
  computed: {
    /**
     * Központi rejtvény játékállapotot ad a komponensnek.
     *
     * @returns {import('../stores/crossword').useCrosswordStore}
     */
    store() {
      return useCrosswordStore()
    },
  },
  methods: {
    /**
     * Egy szóhoz vizuális sort épít a teljes grid szélesség használatával.
     * A nem használt oszlopok fekete cellaként jelennek meg.
     *
     * @param {{ cells: Array<{ col: number }> }} word API-ból érkező szó payload.
     * @returns {Array<{ type: 'black'|'input', cellIndex: number|null }>} Cella leírók a rendereléshez.
     */
    getCellsForWord(word) {
      const cells = Array.from({ length: this.width }, () => ({ type: 'black', cellIndex: null }))

      word.cells.forEach((cell, cellIndex) => {
        cells[cell.col] = {
          type: 'input',
          cellIndex,
        }
      })

      return cells
    },
    /**
     * Kiolvassa egy szó adott cellájának aktuális felhasználói értékét.
     *
     * @param {number} wordIndex A szó indexe.
     * @param {number} cellIndex A cella indexe a szóban.
     * @returns {string} Az aktuális cella értéke.
     */
    getCellValue(wordIndex, cellIndex) {
      return this.store.wordInputs[wordIndex]?.[cellIndex] ?? ''
    },
    /**
     * Visszaadja, hogy egy szó minden cellája ki van-e töltve.
     * A Boolean wrapper akkor is szigorúan boolean értéket ad, ha hiányzik a state.
     *
     * @param {number} wordIndex A szó indexe.
     * @returns {boolean} True, ha a szó teljesen ki van töltve.
     */
    isWordFilled(wordIndex) {
      return Boolean(this.store.wordStatus[wordIndex]?.filled)
    },
    /**
     * Visszaadja, hogy az adott szó jelenleg helyesre van-e validálva.
     * A Boolean wrapper akkor is szigorúan boolean értéket ad, ha hiányzik a state.
     *
     * @param {number} wordIndex A szó indexe.
     * @returns {boolean} True, ha a szó helyes.
     */
    isWordCorrect(wordIndex) {
      return Boolean(this.store.wordStatus[wordIndex]?.correct)
    },
    /**
     * Visszaadja a szó karakterszámú cellahosszát.
     *
     * @param {number} wordIndex A szó indexe.
     * @returns {number} A szerkeszthető cellák száma a szóban.
     */
    getWordLength(wordIndex) {
      return this.words?.[wordIndex]?.cells?.length ?? 0
    },
    /**
     * Eltárolja a komponens refeket, hogy később konkret szó/cella párra lehessen fókuszálni.
     *
     * @param {unknown} el Komponens példány vagy DOM elem.
     * @param {number} wordIndex A szó indexe.
     * @param {number} cellIndex A cella indexe.
     * @returns {void}
     */
    setInputRef(el, wordIndex, cellIndex) {
      if (!el) {
        return
      }

      this.inputRefs[`${wordIndex}-${cellIndex}`] = el
    },
    /**
     * Egyszerre frissíti az aktív szót es az aktív cellát a store állapotban.
     *
     * @param {number} wordIndex A szó indexe.
     * @param {number} cellIndex A cella indexe.
     * @returns {void}
     */
    setActiveWordAndCell(wordIndex, cellIndex) {
      this.store.setActiveWord(wordIndex)
      this.store.setActiveCellInWord(wordIndex, cellIndex)
    },
    /**
     * Ref alapján fókuszál egy konkrét input cellára.
     *
     * @param {number} wordIndex A szó indexe.
     * @param {number} cellIndex A cella indexe.
     * @returns {void}
     */
    focusCell(wordIndex, cellIndex) {
      this.setActiveWordAndCell(wordIndex, cellIndex)

      const ref = this.inputRefs[`${wordIndex}-${cellIndex}`]
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
    },
    /**
     * Kiszámolja, melyik cellára kell fókuszálni definícióra kattintás után.
     * Az első üres cellát választja, különben az elsőt, és kihagyja a már teljesen megoldott szavakat.
     *
     * @param {number} wordIndex A kattintott szó indexe.
     * @returns {number|null} Cella index célpont, vagy null, ha nem kell fókusz.
     */
    getDefinitionFocusTarget(wordIndex) {
      if (this.isWordCorrect(wordIndex)) {
        return null
      }

      const inputs = this.store.wordInputs[wordIndex] ?? []
      const firstEmptyIndex = inputs.findIndex((value) => value === '')

      if (firstEmptyIndex !== -1) {
        return firstEmptyIndex
      }

      return 0
    },
    /**
     * Definíció kattintást kezel: aktiválja a szót es hasznos cellára viszi a fókuszt.
     *
     * @param {number} wordIndex A kattintott definíció indexe.
     * @returns {void}
     */
    handleDefinitionClick(wordIndex) {
      this.store.setActiveWord(wordIndex)
      const targetCellIndex = this.getDefinitionFocusTarget(wordIndex)

      if (targetCellIndex !== null) {
        this.focusCell(wordIndex, targetCellIndex)
      }
    },
    /**
     * A fókuszt a következő cellára mozgatja ugyanabban a szóban.
     *
     * @param {number} wordIndex A szó indexe.
     * @param {number} cellIndex Aktuális cella index.
     * @returns {void}
     */
    focusNextInWord(wordIndex, cellIndex) {
      const wordLength = this.getWordLength(wordIndex)
      if (cellIndex >= wordLength - 1) {
        return
      }

      this.focusCell(wordIndex, cellIndex + 1)
    },
    /**
     * A fókuszt az előző cellára mozgatja ugyanabban a szóban.
     *
     * @param {number} wordIndex A szó indexe.
     * @param {number} cellIndex Aktuális cella index.
     * @returns {void}
     */
    focusPrevInWord(wordIndex, cellIndex) {
      if (cellIndex <= 0) {
        return
      }

      this.focusCell(wordIndex, cellIndex - 1)
    },
    /**
     * A fókuszt relatív eltolással egy szomszédos szóra mozgatja.
     *
     * @param {number} wordIndex Aktuális szó index.
     * @param {number} offset Relatív mozgás (-1 fel, +1 le).
     * @returns {void}
     */
    focusWordByOffset(wordIndex, offset) {
      const nextWordIndex = wordIndex + offset
      if (nextWordIndex < 0 || nextWordIndex >= this.words.length) {
        return
      }

      const targetCellIndex = this.getDefinitionFocusTarget(nextWordIndex)
      if (targetCellIndex === null) {
        this.store.setActiveWord(nextWordIndex)
        return
      }

      this.focusCell(nextWordIndex, targetCellIndex)
    },
    /**
     * Bemeneti értéket ír a store-ba, és ha kell, továbblépteti a fókuszt.
     *
     * @param {number} wordIndex A szó indexe.
     * @param {number} cellIndex A cella indexe.
     * @param {string} value Normalizált input érték.
     * @returns {void}
     */
    handleCellInput(wordIndex, cellIndex, value) {
      this.setActiveWordAndCell(wordIndex, cellIndex)
      this.store.updateWordCell(wordIndex, cellIndex, value)

      if (value && !this.isWordCorrect(wordIndex)) {
        this.focusNextInWord(wordIndex, cellIndex)
      }
    },
    /**
     * Kezeli a billentyűzetes navigációt és a törlés viselkedést.
     *
     * @param {KeyboardEvent} event Cella billentyűzet esemény.
     * @param {number} wordIndex Az aktív szó indexe.
     * @param {number} cellIndex Az aktív cella indexe.
     * @returns {void}
     */
    handleKeydown(event, wordIndex, cellIndex) {
      const key = event.key

      if (key === 'ArrowRight') {
        event.preventDefault()
        this.focusNextInWord(wordIndex, cellIndex)
        return
      }

      if (key === 'ArrowLeft') {
        event.preventDefault()
        this.focusPrevInWord(wordIndex, cellIndex)
        return
      }

      if (key === 'ArrowDown') {
        event.preventDefault()
        this.focusWordByOffset(wordIndex, 1)
        return
      }

      if (key === 'ArrowUp') {
        event.preventDefault()
        this.focusWordByOffset(wordIndex, -1)
        return
      }

      if (key === 'Backspace') {
        event.preventDefault()

        const currentValue = this.getCellValue(wordIndex, cellIndex)
        if (currentValue) {
          this.store.deleteWordCell(wordIndex, cellIndex)
          return
        }

        this.focusPrevInWord(wordIndex, cellIndex)
      }
    },
  },
}
</script>

<style scoped>
@import '../styles/crosswordPage.scss';
</style>