<template>
  <input
    type="text"
    maxlength="1"
    v-model="userInput"
    @input="$emit(userInput)"
  />
</template>

<script>
export default {
  name: 'CrosswordCell',
  props: {
    letter: {
      type: String,
      required: true,
    },
    isCorrect: {
      type: Boolean,
      default: false,
    },
    isDisabled: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      userInput: '',
    }
  },
}
</script>