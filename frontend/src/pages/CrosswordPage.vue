<template>
  <h1>Rejtvény</h1>
  <p>Megoldó nézet váz.</p>
</template>

<script>
export default {
  name: 'CrosswordPage',
  props: {
    id: {
      type: String,
      required: true
    }
  },
  mounted() {
    console.log('id: ', this.id)
  }
}
</script>