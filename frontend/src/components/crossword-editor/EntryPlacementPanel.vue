<template>
  <div class="d-flex flex-column p-4 rounded shadow-sm border bg-white box-background">
    <h4 class="text-center mb-4 border-bottom pb-2">Szó elhelyezése</h4>

    <p v-if="wordsLoading" class="text-muted text-center my-3">
      <font-awesome-icon icon="fa-solid fa-spinner" class="fa-spin me-2" /> Szavak betöltése...
    </p>

    <p v-else-if="wordsError" class="alert alert-danger text-center">
      {{ wordsError }}
    </p>

    <div v-else class="d-flex flex-column gap-4 w-100">
      
      <div>
        <label class="form-label fw-bold">Keresendő szó:</label>
        
        <v-select
          v-model="selectedWord"
          name="clue-v-select"
          class="w-100 bg-white border border-primary rounded clue-select mb-2"
          label="solution"
          :placeholder="'Válassz egy szót a listából...'"
          :options="availableWords"
          :disabled="isReadOnly || wordsLoading || !!wordsError"
        >
          <template #no-options="{ search, searching }">
            <template v-if="searching">
              Sajnos a(z) "<strong>{{ search }}</strong>" keresésre nincs találat. <br>
              Add hozzá az alábbi gombbal!
            </template>
            <template v-else>
              A lista jelenleg üres. <br> 
              Adj hozzá új szavakat az alábbi gombbal!
            </template>
          </template>
        </v-select>

        <button
          v-if="!isReadOnly"
          type="button"
          class="btn btn-sm btn-outline-secondary w-100"
          @click="$emit('add-clue')"
        >
          <font-awesome-icon icon="fa-solid fa-plus" class="me-1" /> Új szó hozzáadása
        </button>

        <div 
          v-if="selectedWord" 
          class="mt-3 p-3 border-start border-3 border-primary bg-light text-muted small rounded-end"
        >
          <strong>Definíció:</strong> {{ selectedWord.definition }}
        </div>
      </div>

      <div class="d-flex flex-column gap-3">
        
        <div>
          <label class="form-label fw-bold" for="direction-select">Irány:</label>
          <select
            v-model="direction"
            class="form-select cursor-pointer w-100"
            id="direction-select"
            :disabled="isReadOnly"
          >
            <option value="horizontal">Vízszintes (Jobbra)</option>
            <option value="vertical">Függőleges (Lefelé)</option>
          </select>
        </div>
        
        <div class="d-flex justify-content-between align-items-center p-3 border rounded bg-light">
          <span class="text-muted fw-bold text-center">Kiválasztott mező:</span>
          <span class="badge bg-primary fs-6 px-3 py-2 shadow-sm">
            {{ selectedCoordinate || 'Nincs' }}
          </span>
        </div>

      </div>

      <div v-if="editor.candidateValidation.errors.length" class="alert alert-danger mb-0 py-2">
        <ul class="mb-0 ps-3">
          <li
            v-for="(error, index) in editor.candidateValidation.errors"
            :key="`${error.code}-${error.row}-${error.col}-${index}`"
          >
            {{ error.message }}
          </li>
        </ul>
      </div>

      <button
        type="button"
        class="btn btn-primary btn-lg w-100 fw-bold mt-2"
        :disabled="isReadOnly || !editor.canPlaceCandidate"
        @click="editor.placeCandidate()"
      >
        Szó elhelyezése a hálóban
      </button>

    </div>
  </div>
</template>

<script>
import { useCrosswordEditorStore } from '../../stores/crosswordEditor.js'

export default {
  name: 'EntryPlacementPanel',
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

    selectedWord: {
      /**
       * Visszaadja a kiválasztott szót a listából, vagy null-t, ha nincs kiválasztva.
       * 
       * @return {Object|null} A kiválasztott szó objektuma, vagy null, ha nincs kiválasztva.
       */
      get() {
        return this.editor.selectedClue
      },
      /**
       * Beállítja a kiválasztott szót a listából, vagy null-t, ha nincs kiválasztva.
       * 
       * @param value - A kiválasztott szó objektuma, vagy null, ha nincs kiválasztva.
       */
      set(value) {
        this.editor.selectClue(value)
      },
    },

    direction: {
      /**
       * Visszaadja a kiválasztott irányt (vízszintes vagy függőleges).
       * 
       * @return {string} A kiválasztott irány ('horizontal' vagy 'vertical').
       */
      get() {
        return this.editor.selectedDirection
      },
      /**
       * Beállítja a kiválasztott irányt (vízszintes vagy függőleges).
       * 
       * @param value - Beállítja a kiválasztott irányt (vízszintes vagy függőleges).
       */
      set(value) {
        this.editor.setDirection(value)
      },
    },
    /**
     * Visszaadja az elérhető szavak listáját, amelyek még nincsenek elhelyezve a rejtvényben.
     * 
     * @return {Array} Az elérhető szavak listája.
     */
    availableWords() {
      return this.words.filter(
        word => !this.editor.usedClueIds.includes(word.id)
      )
    },
    /**
     * Visszaadja a kiválasztott cella koordinátáit
     * 
     * @return {string} A kiválasztott cella koordinátái vagy egy üzenet, ha nincs kiválasztva.
     */
    selectedCoordinate() {
      if (!this.editor.selectedCell) {
        return 'Nincs'
      }

      const { row, col } = this.editor.selectedCell
      return `${row + 1}. sor, ${col + 1}. oszlop`
    },
  }
}
</script>

<style scoped lang="scss">
:deep(.vs__dropdown-toggle) {
  border: none !important;
  padding: 0.375rem 0.75rem;
  background-color: #fff;
  border-radius: inherit;
}

:deep(.vs--searchable .vs__dropdown-toggle) {
  cursor: pointer;
}

:deep(.vs--open) {
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
  border-radius: 0.375rem;
}

.pointer {
  cursor: pointer;
}
</style>