<template>
  <input
    ref="inputEl"
    type="text"
    maxlength="1"
    :value="modelValue"
    @input="handleInput"
    @keydown="handleKeydown"
    :disabled="isDisabled"
    :class="{
      'correct': isRowFilled && isCorrect,
      'incorrect': isRowFilled && !isCorrect,
    }"
  />
</template>

<script>
export default {
  name: 'CrosswordCell',
  emits: ['update:modelValue', 'keydown'],
  props: {
    modelValue: {
      type: String,
      default: '',
    },
    isCorrect: {
      type: Boolean,
      default: false,
    },
    isRowFilled: {
      type: Boolean,
      default: false,
    },
  },
  computed: {
    /**
     * Lezárja az inputot, ha a szülő szó ki van töltve és helyesre van validálva.
     *
     * @returns {boolean} True, ha a cellát tiltani kell.
     */
    isDisabled() {
      return this.isRowFilled && this.isCorrect
    },
  },
  methods: {
    /**
     * Egy cella értéket egyetlen nagybetűs karakterre normalizál.
     *
     * @param {unknown} value Az input elemből érkező nyers érték.
     * @returns {string} Normalizált karakter vagy üres string.
     */
    normalizeValue(value) {
      if (!value) {
        return ''
      }

      return String(value).replace(/\s/g, '').slice(0, 1).toUpperCase()
    },
    /**
     * A normalizált input értéket emitálja.
     *
     * @param {InputEvent & { target: HTMLInputElement }} event Natív input esemény.
     * @returns {void}
     */
    handleInput(event) {
      const normalized = this.normalizeValue(event?.target?.value)
      this.$emit('update:modelValue', normalized)
    },
    /**
     * Továbbítja a keydown eseményeket, hogy a billentyűzet navigációt a szülő kezelhesse.
     *
     * @param {KeyboardEvent} event Billentyűzet esemény.
     * @returns {void}
     */
    handleKeydown(event) {
      this.$emit('keydown', event)
    },
    /**
     * Imperatív fókusz metódust ad a szülő szintű navigációhoz.
     *
     * @returns {void}
     */
    focus() {
      this.$refs.inputEl?.focus()
    },
  },
}
</script>