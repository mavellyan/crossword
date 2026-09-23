<template>
  <button
    type="button"
    class="editor-cell"
    :class="{
      'occupied': cell.isOccupied,
      'intersection': cell.isIntersection,
      'selected': cell.isSelected,
      'active': cell.isActive,
      'candidate-valid': cell.isCandidateValid,
      'candidate-invalid': cell.isCandidateInvalid,
    }"
    :disabled="disabled"
    @click="$emit('select', cell)"
  >
    {{ cell.letter }}
  </button>
</template>

<script>
export default {
  name: 'EditorCell',
  emits: ['select'],

  props: {
    cell: {
      type: Object,
      required: true,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
  },
}
</script>

<style scoped lang="scss">
.editor-cell {
  width: 34px;
  height: 34px;
  padding: 0;
  border: 0;
  border-right: 1px solid #333;
  border-bottom: 1px solid #333;
  background: #e5e7eb;
  font-weight: 600;
}

.editor-cell.occupied {
  background: white;
}

.editor-cell.intersection {
  background: #dbeafe;
}

.editor-cell.selected {
  outline: 3px solid #2563eb;
  outline-offset: -3px;
}

.editor-cell.active {
  background: #bfdbfe;
}

.editor-cell.candidate-valid {
  background: #bbf7d0;
}

.editor-cell.candidate-invalid {
  background: #fecaca;
}
</style>