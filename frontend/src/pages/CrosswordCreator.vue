<template>
  <div>
    <h1 class="text-center pb-5">Hozz létre saját rejtvényt!</h1>
    <div class="d-flex align-items-center justify-content-center">
        <label>Mi legyen a rejtvényed főmegoldása?</label>
        <input v-model="mainSolution" class="form-control w-auto mx-3 border border-primary border-2" maxlength="20" />
    </div>
    <div v-if="hasMainSolution">

    </div>
  </div>
</template>

<script>
export default {
  name: 'CrosswordCreator',
  computed: {
    hasMainSolution() {
      return this.mainSolution.trim().length > 0
    },
    /**
     * A főmegoldás karaktereit egy tömbbé alakítja, hogy a CrosswordGrid komponens használni tudja.
     *
     * @returns {string[]} A főmegoldás karakterei tömbben.
     */
    mainSolutionArray() {
      return this.mainSolution.split('')
    },
  },
  data() {
    return {
      mainSolution: ''
    }
  }
}
</script>