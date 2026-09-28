<template>
  <div class="card p-3">
    <h5 class="card-title text-center">Vízszintes szavak</h5>
    <p v-if="horizontalClues.length === 0" class="text-muted">
      Nincsenek vízszintes szavak.
    </p>
    <button
      v-for="clue in horizontalClues"
      :key="clue.placement_id"
      type="button"
      class="entry-row mb-2 rounded p-2"
      :class="{ 'active': clue.isActive }"
      @click="$emit('clue-clicked', clue.placement_id)"
    >
      <span>
        {{ clue.definition }}
      </span>
      <br>
      <span class="fst-italic">
        {{ clue.startRow + 1 }}.sor, {{ clue.startCol + 1 }}.oszlop
      </span>
    </button>

    <h5 class="card-title text-center">Függőleges szavak</h5>
    <p v-if="verticalClues.length === 0" class="text-muted">
      Nincsenek függőleges szavak.
    </p>
    <button
      v-for="clue in verticalClues"
      :key="clue.placement_id"
      type="button"
      :class="{ 'active': clue.isActive }"
      class="entry-row mb-2 rounded p-2"
      @click="$emit('clue-clicked', clue.placement_id)"
    >
      <span>
        {{ clue.definition }}
      </span>
      <br>
      <span class="fst-italic">
        {{ clue.startRow + 1 }}.sor, {{ clue.startCol + 1 }}.oszlop
      </span>
    </button>
  </div>
</template>

<script>
export default {
  name: 'ClueList',
  props: {
    horizontalClues: {
      type: Array,
      required: true,
    },
    verticalClues: {
      type: Array,
      required: true,
    },
  },
  emits: ['clue-clicked'],
}
</script>

<style scoped lang="scss">
.active {
  border: 2px solid #2563eb;
  transition: scale 0.3s ease-in-out;
  transform: scale(1.02);
}
</style>