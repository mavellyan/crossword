<template>
  <input
    ref="inputEl"
    type="text"
    maxlength="1"
    :value="modelValue"
    @input="handleInput"
    @keydown="handleKeydown"
    class="solver-cell"
    :class="{
      'blocked': cell.isBlocked,
      'intersection': cell.isIntersection,
      'active': cell.isActive,
      'inActiveEntry': cell.isInActiveEntry,
      'pending': cell.isPending,
      'correct': cell.isCorrect,
      'incorrect': cell.isFilled && !cell.isPending && !cell.isCorrect,
    }"
    :disabled="cell.isBlocked"
    :readonly="cell.isLocked"
  />
</template>

<script>
export default {
  name: 'SolverCell',
  props: {
    cell: {
      type: Object,
      required: true
    },
    modelValue: {
      type: String,
      default: '',
    },
  },
  emits: ['update:modelValue', 'keydown'],
  methods: {
    /**
     * Nagybetűsíti a cella értékét, és regex segítségével eltávolítja a nem engedélyezett karaktereket. Csak az A-Z és a magyar ékezetes karakterek engedélyezettek.
     * Ezután emittálja az input eseményt a szülő komponensnek, hogy frissítse a cella értékét.
     * Amennyiben a cella zárolt (isLocked), az input esemény nem frissíti a cella értékét, és biztos ami biztos, visszaállítja az input mező értékét a modelValue-ra.
     * 
     * @param event Az input esemény, amely a cella értékének változását jelzi.
     */
    handleInput(event) {
      if (this.cell.isLocked) {
        event.preventDefault();
        event.target.value = this.modelValue;
        return;
      }

      let value = event.target.value.toUpperCase();

      value = value.replace(/[^A-ZÁÉÍÓÖŐÚÜŰ]/g, ''); // Csak a megengedett karakterek engedélyezése
      event.target.value = value; // Frissíti az input mező értékét

      this.$emit('update:modelValue', value);
    },
    /**
     * Emittálja a billentyűleütés eseményt a szülő komponensnek, hogy kezelje a navigációt és a törlést.
     * A zárolt cellák esetén a Backspace és Delete billentyűk nem engedélyezettek, így ezek az események megakadályozásra kerülnek.
     * A navigációs billentyűk (nyilak) továbbra is működnek a zárolt cellák esetén.
     * 
     * @param event Az esemény, amely a billentyűleütést jelzi.
     */
    handleKeydown(event) {
      if (this.cell.isLocked) {
        if (event.key === 'Backspace' || event.key === 'Delete') {
          event.preventDefault();
          return;
        }
      }

      this.$emit('keydown', event);
    },
  },
}
</script>

<style scoped lang="scss">
.solver-cell {
  width: 40px;
  height: 40px;
  padding: 0;
  border: 0;
  border-right: 1px solid #333;
  border-bottom: 1px solid #333;
  background: #e5e7eb;
  font-weight: 600;
  text-align: center;
  text-transform: uppercase;
}

.solver-cell.blocked {
  background: black;
}

.solver-cell.intersection {
  background: #dbeafe;
}

.solver-cell.inActiveEntry {
  background: #bfdbfe;
}

.solver-cell.active {
  background: #2563eb;
  color: white;
}

.solver-cell.incorrect {
  background: red;
}

.solver-cell.correct {
  background: green;
  color: black;
}

.solver-cell.pending {
  background: gray;
}
</style>
