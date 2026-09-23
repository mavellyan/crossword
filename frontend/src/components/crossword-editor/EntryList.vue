<template>
  <div class="card p-3">
    <h5 class="card-title text-center">Elhelyezett szavak</h5>

    <p v-if="editor.entries.length === 0" class="text-muted">
      Még nincs elhelyezett szó.
    </p>

    <button
      v-for="entry in editor.entries"
      :key="entry.clientId"
      type="button"
      class="entry-row mb-2 rounded"
      :class="{ active: entry.clientId === editor.activeEntryId }"
      @click="editor.selectEntry(entry.clientId)"
    >
      <span>
        {{ directionLabel(entry.direction) }}
        — {{ entry.solution }}
      </span>
      <br>
      <span>
        {{ entry.startRow + 1 }}.sor, {{ entry.startCol + 1 }}.oszlop
      </span>

      <div
        v-if="!isReadOnly"
        class="entry-delete"
        @click.stop="editor.removeEntry(entry.clientId)"
      >
        Törlés
      </div>
    </button>

    <div
      v-if="editor.entries.length >= 2 && !editor.layoutValidation.isValid"
      class="alert alert-warning mt-3"
    >
      A jelenlegi elrendezés nem menthető.
      <div v-if="editor.layoutValidation.errors?.length > 0" class="mt-2">
        {{ editor.layoutValidation.errors?.length }} hibát találtunk.
        <ul class="mb-0 ps-3">
          <li
            v-for="(error, index) in editor.layoutValidation.errors"
            :key="`${error.code}-${error.row}-${error.col}-${index}`"
          >
            {{ error.message }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
import { useCrosswordEditorStore } from '../../stores/crosswordEditor.js'

export default {
  name: 'EntryList',
  props: {
    isReadOnly: Boolean,
  },
  computed: {
    editor() {
      return useCrosswordEditorStore()
    },
  },
  methods: {
    directionLabel(direction) {
      return direction === 'horizontal' ? 'Vízszintes' : 'Függőleges'
    },
  },
}
</script>

<style scoped lang="scss">
.entry-row {
  width: 100%;
  padding: 0.5rem;
  background-color: #f8f9fa;
  cursor: pointer;
  text-align: center;

  &.active {
    background-color: #e2e6ea;
    border-color: #007bff;
  }

  .entry-delete {
    margin-top: 0.5rem;
    color: #dc3545;
    font-size: 0.875rem;
    text-align: center;

    &:hover {
      text-decoration: underline;
    }
  }
}
</style>