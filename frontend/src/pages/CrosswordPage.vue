<template>
  <h1>Rejtvény</h1>
  <div class="layout justify-content-center">
    <div v-if="grid !== null" class="grid">
      <div v-for="(row, rowIndex) in grid" :key="rowIndex" class="grid-row">
        <div v-for="(cell, cellIndex) in row" :key="cellIndex" class="cell">
          <div v-if="cell === '#'" class="black"></div>
          <input
            v-else
            type="text"
            maxlength="1"
            class="input"
            name="cell"
            v-model="userGrid[rowIndex][cellIndex]"
            :ref="el => setInputRef(el, rowIndex, cellIndex)"
            @input="handleInput(rowIndex, cellIndex)"
            @keydown="handleKeydown($event, rowIndex, cellIndex)"
          >
        </div>
      </div>
    </div>
    <div v-if="definitions !== null" class="definitions">
      <h3>Definíciók</h3>
      <div v-for="(definition, index) in definitions" :key="index">
        <strong>{{ index+1 }}.</strong> {{ definition }}
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'CrosswordPage',
  props: {
    id: {
      type: String,
      required: true
    }
  },
  data() {
    return {
      grid: null,
      main_solution: null,
      definitions: null,
      solutions: null,
      userGrid: [],
      inputRefs: {},
    };
  },
  mounted() {
    axios.get(`/crossword/${this.id}`)
      .then(response => {
        console.log('Rejtvény adatai:', response.data)
        this.grid = response.data.crossword.grid
        this.main_solution = response.data.crossword.main_solution
        this.definitions = response.data.crossword.definitions
        this.solutions = response.data.crossword.solutions

        this.userGrid = this.grid.map(row => row.map(cell => cell === '#' ? '#' : ''))
      })
      .catch(error => {
        console.error('Hiba a rejtvény betöltésekor:', error)
      });
  },
  methods: {
    /**
     * Beállítja a cellák input elemeinek a referenciáit, így később könnyen hozzáférhetünk és fókuszálhatunk rájuk
     * 
     * @param el 
     * @param rowIndex 
     * @param cellIndex 
     */
    setInputRef(el, rowIndex, cellIndex) {
      if (!el) return

      this.inputRefs[`${rowIndex}-${cellIndex}`] = el
    },
    /**
     * Kezeli a cellákba való beírást, nagybetűssé alakítja a karaktereket, és automatikusan a következő cellára helyezi a fókuszt
     * 
     * @param rowIndex 
     * @param cellIndex 
     */
    handleInput(rowIndex, cellIndex) {
      let val = this.userGrid[rowIndex][cellIndex]

      if (!val) {
        return
      }

      val = val.slice(0, 1).toUpperCase()
      this.userGrid[rowIndex][cellIndex] = val

      this.focusNext(rowIndex, cellIndex)
    },
    /**
     * Kezeli a billentyűleütéseket a cellákban, lehetővé téve a Backspace és a nyílbillentyűk használatát a navigációhoz
     * 
     * @param event 
     * @param rowIndex 
     * @param cellIndex 
     */
    handleKeydown(event, rowIndex, cellIndex) {
      if (event.key === 'Backspace' && !this.userGrid[rowIndex][cellIndex]) {
        this.focusPrev(rowIndex, cellIndex)
      }

      if (event.key === 'ArrowRight') {
        event.preventDefault()
        this.focusNext(rowIndex, cellIndex)
      }

      if (event.key === 'ArrowLeft') {
        event.preventDefault()
        this.focusPrev(rowIndex, cellIndex)
      }

      if (event.key === 'ArrowDown') {
        event.preventDefault()
        this.focusNext(rowIndex, cellIndex, 'down')
      }

      if (event.key === 'ArrowUp') {
        event.preventDefault()
        this.focusPrev(rowIndex, cellIndex, 'up')
      }
    },
    /**
     * Lefelé vagy jobbra helyezi a fókuszt a következő cellára, kihagyva a fekete cellákat
     * 
     * @param rowIndex 
     * @param cellIndex 
     * @param direction 
     */
    focusNext(rowIndex, cellIndex, direction = 'right') {
      if (direction === 'down') {
        let nextRow = rowIndex + 1

        while (nextRow < this.grid.length) {
          if (this.grid[nextRow][cellIndex] !== '#') {
            this.inputRefs[`${nextRow}-${cellIndex}`]?.focus()
            return
          }
          nextRow++
        }

        return
      } 

      let nextCol = cellIndex + 1

        while (nextCol < this.grid[rowIndex].length) {
          if (this.grid[rowIndex][nextCol] !== '#') {
            this.inputRefs[`${rowIndex}-${nextCol}`]?.focus()
            return
          }
          nextCol++
        }
    },
    /**
     * Felfelé vagy balra helyezi a fókuszt az előző cellára, kihagyva a fekete cellákat
     * 
     * @param rowIndex 
     * @param cellIndex 
     * @param direction 
     */
    focusPrev(rowIndex, cellIndex, direction = 'left') {
      if (direction === 'up') {
        let prevRow = rowIndex - 1

        while (prevRow >= 0) {
          if (this.grid[prevRow][cellIndex] !== '#') {
            this.inputRefs[`${prevRow}-${cellIndex}`]?.focus()
            return
          }
          prevRow--
        }

        return
      }

      let prevCol = cellIndex - 1

      while (prevCol >= 0) {
        if (this.grid[rowIndex][prevCol] !== '#') {
          this.inputRefs[`${rowIndex}-${prevCol}`]?.focus()
          return
        }
        prevCol--
      }
    },
  },
}
</script>

<style scoped>
.layout {
  display: flex;
  gap: 100px;
}

.grid {
  display: flex;
  flex-direction: column;
  border: 3px solid black;
}

.grid-row {
  display: flex;
}

.cell {
  width: 50px;
  height: 50px;
  border: 1px solid black;
}

.black {
  width: 100%;
  height: 100%;
  background: black;
}

.input {
  width: 100%;
  height: 100%;
  border: none;
  text-align: center;
  font-weight: bold;
  font-size: 22px;
  text-transform: uppercase;
}

.definitions {
  min-width: 200px;
}
</style>