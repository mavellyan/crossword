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
            :class="{
              'active-row': activeRow === rowIndex,
              'correct': correctCells[rowIndex][cellIndex],
              'incorrect': !correctCells[rowIndex][cellIndex] && !userGrid[rowIndex].includes('')
            }"
            :disabled="correctCells[rowIndex][cellIndex]"
            name="cell"
            v-model="userGrid[rowIndex][cellIndex]"
            :ref="el => setInputRef(el, rowIndex, cellIndex)"
            @input="handleInput(rowIndex, cellIndex)"
            @keydown="handleKeydown($event, rowIndex, cellIndex)"
            @click="setActiveRow(rowIndex)"
          >
        </div>
      </div>
    </div>
    <div v-if="definitions !== null" class="definitions">
      <h3>Definíciók</h3>
      <div v-for="(definition, index) in definitions" :key="index"
        class="definition"
        :class="{ 'active-row': activeRow === index}"
        @click="setActiveRow(index, true)"
      >
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
      correctCells: [],
      inputRefs: {},
      activeRow: null,
    };
  },
  mounted() {
    axios.get(`/crossword/${this.id}`)
      .then(response => {
        // TODO: ez már egyáltalán nem így jön vissza, át kell írni az egészet
        // valamint a gridet kiszervezni egy külön komponensbe
        // és talán (valószínűleg úgy lehet a legjobb? nem biztos még) a definíciók/cellák külön is külön komponenst kapnak a griden belül
        // nyilakkal navigálás be van fosva ha lockolt a cella (??) de ezt talan meg lehet oldani az uj rendszerezessel??
        // rejtvény generálás külön service fileba kiszervezni jó ötlet lehet (már van is file csak valamiert nem abban csinaltam meg???XD)
        // id-ket hozzáadni a cluekhoz
        // törölhető a crosswordapijs, crosswordjs, demosimplecrosswordservicephp, a scandinavian generator is valszeg
        // KURVA NAGY REFAKT KELL XD ÄÄÄÄÄÄÄÄÄ
        console.log('Rejtvény adatai:', response.data)
        this.grid = response.data.crossword.grid
        this.main_solution = response.data.crossword.main_solution
        this.definitions = response.data.crossword.definitions
        this.solutions = response.data.crossword.solutions

        this.userGrid = this.grid.map(row => row.map(cell => cell === '#' ? '#' : ''))
        this.correctCells = this.grid.map(row => row.map(cell => false))
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

      if (!this.userGrid[rowIndex].includes('')) {
        this.checkCompletion(rowIndex)
      }
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
            this.setActiveRow(nextRow)
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
            this.setActiveRow(prevRow)
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
    /**
     * Beállítja az aktív sort, amelyre a definíciók vonatkoznak, így vizuálisan is kiemelve azt, amelyiken éppen dolgozunk
     * 
     * @param rowIndex 
     */
    setActiveRow(rowIndex, definitionClick = false) {
      this.activeRow = rowIndex

      if (definitionClick) {
        // Ha a definícióra kattintottunk, akkor az első cellára helyezzük a fókuszt
        for (let cellIndex = 0; cellIndex < this.grid[rowIndex].length; cellIndex++) {
          if (this.grid[rowIndex][cellIndex] !== '#') {
            this.inputRefs[`${rowIndex}-${cellIndex}`]?.focus()
            break
          }
        }
      }
    },
    checkCompletion(rowIndex) {
      this.userGrid[rowIndex].map((cell, cellIndex) => {
        if (cell !== '#' && cell === this.grid[rowIndex][cellIndex]) {
          this.correctCells[rowIndex][cellIndex] = true
        } else {
          this.correctCells[rowIndex][cellIndex] = false
        }
      })
    }
  },
}
</script>

<style scoped>
@import '../styles/crosswordPage.scss';
</style>