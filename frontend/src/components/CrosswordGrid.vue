<template>
  <div class="crossword-wrapper position-relative">
    <div
      v-if="!isCrosswordStarted && isLoggedIn"
      class="position-absolute top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center z-1 gap-3"
    >
      <button
        class="btn btn-success btn-lg shadow text-uppercase fw-bold"
        @click="attemptStore.status === 'completed' ? this.$emit('abandon-attempt') : startGame()"
        :disabled="isCrosswordResetting"
      >
        {{ startButtonText }}
      </button>
      <button
        v-if="attemptStore.status === 'in_progress'"
        class="btn btn-danger btn-lg shadow text-uppercase fw-bold"
        @click="this.$emit('abandon-attempt')"
        :disabled="isCrosswordResetting"
        v-tooltip.hover="'Törli a korábbi próbálkozást és visszaállítja a játékot a kezdeti állapotba.'"
      >
        Újrakezdés
      </button>
    </div>
    <div
      class="layout justify-content-center"
      :class="{'blur-background': !isCrosswordStarted && isLoggedIn}"
    >
      <div class="grid">
        <div
          v-for="(word, wordIndex) in words"
          :key="wordIndex"
          class="grid-row"
          :class="{ 'active-row': crosswordStore.activeWordIndex === wordIndex }"
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
              :is-pending="isWordPending(wordIndex)"
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
        <h3>Definíciók</h3>
        <div
          v-for="(word, index) in words"
          :key="index"
          class="definition"
          :class="{ 'active-row': crosswordStore.activeWordIndex === index }"
          @click="handleDefinitionClick(index)"
        >
          <strong>{{ index + 1 }}.</strong> {{ word.definition }}
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { useCrosswordStore } from '../stores/crossword'
import { useAttemptStore } from '../stores/attempt'
import CrosswordCell from './CrosswordCell.vue'
import { useAuthStore } from '../stores/auth'
import { mapState } from 'pinia'

export default {
  name: 'CrosswordGrid',
  emits: ['start-game', 'abandon-attempt'],
  components: {
    CrosswordCell,
  },
  props: {
    words: {
      type: Array,
      required: true,
    },
    width: {
      type: Number,
      required: true,
    },
    isCrosswordResetting: {
      type: Boolean,
      required: true,
    },
  },
  data() {
    return {
      inputRefs: {},
      /**
       * A felhasználó elindította-e a rejtvény kitöltését.
       * 
       * @type {boolean}
       */
      isCrosswordStarted: false,
    }
  },
  computed: {
    ...mapState(useAuthStore, ['isLoggedIn']),
    crosswordStore() {
      return useCrosswordStore()
    },
    attemptStore() {
      return useAttemptStore()
    },
    startButtonText() {
      if (this.attemptStore.status === 'in_progress') {
        return 'Folytatás'
      } else if (this.attemptStore.status === 'completed') {
        return 'Újraindítás'
      } else {
        return 'Játék indítása'
      }
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
      return this.crosswordStore.wordInputs[wordIndex]?.[cellIndex] ?? ''
    },
    /**
     * Visszaadja, hogy egy szó minden cellája ki van-e töltve.
     * A Boolean wrapper akkor is szigorúan boolean értéket ad, ha hiányzik a state.
     *
     * @param {number} wordIndex A szó indexe.
     * @returns {boolean} True, ha a szó teljesen ki van töltve.
     */
    isWordFilled(wordIndex) {
      return Boolean(this.crosswordStore.wordStatus[wordIndex]?.filled)
    },
    /**
     * Visszaadja, hogy az adott szó jelenleg helyesre van-e validálva.
     * A Boolean wrapper akkor is szigorúan boolean értéket ad, ha hiányzik a state.
     *
     * @param {number} wordIndex A szó indexe.
     * @returns {boolean} True, ha a szó helyes.
     */
    isWordCorrect(wordIndex) {
      return Boolean(this.crosswordStore.wordStatus[wordIndex]?.correct)
    },
    /**
     * Visszaadja, hogy az adott szó jelenleg függőben van-e.
     * A Boolean wrapper akkor is szigorúan boolean értéket ad, ha hiányzik a state.
     *
     * @param {number} wordIndex A szó indexe.
     * @returns {boolean} True, ha a szó függőben van.
     */
    isWordPending(wordIndex) {
      return Boolean(this.crosswordStore.wordStatus[wordIndex]?.pending)
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
      this.crosswordStore.setActiveWord(wordIndex)
      this.crosswordStore.setActiveCellInWord(wordIndex, cellIndex)
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

      const inputs = this.crosswordStore.wordInputs[wordIndex] ?? []
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
      this.crosswordStore.setActiveWord(wordIndex)
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
        this.crosswordStore.setActiveWord(nextWordIndex)
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
      this.crosswordStore.updateWordCell(wordIndex, cellIndex, value)

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
          this.crosswordStore.deleteWordCell(wordIndex, cellIndex)
          return
        }

        this.focusPrevInWord(wordIndex, cellIndex)
      }
    },
    /**
     * Elindítja a próbálkozást.
     *
     * @returns {void}
     */
    startGame() {
      this.isCrosswordStarted = true
      this.$emit('start-game')
    },
  },
}
</script>

<style scoped>
@import '../styles/crosswordPage.scss';

.blur-background {
  filter: blur(6px);
  pointer-events: none;
}
</style>