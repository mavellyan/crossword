<template>
</template>

<script>
import CrosswordCell from './CrosswordCell.vue';

export default {
    name: 'CrosswordRow',
    components: {
      CrosswordCell,
    },
    props: {
      word: {
        type: Object,
        required: true,
      },
      width: {
        type: Number,
        required: true,
      },
    },
    data() {
      return {
        cells: [],
      };
    },
    mounted() {
      this.cells = Array(this.width).fill(null).map((_, i) => {
        return this.word.cells.find(cell => cell.col === i) || null
      })
    }
}
</script>