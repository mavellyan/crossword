import { defineStore } from 'pinia'
import { fetchCrosswordById } from '@/services/crosswordApi'

function createUserGrid(grid) {
  return grid.map((row) => row.map((cell) => (cell === '#' ? '#' : '')))
}

function createCorrectCells(grid) {
  return grid.map((row) => row.map(() => false))
}

export const useCrosswordStore = defineStore('crossword', {
  state: () => ({
    id: null,
    grid: null,
    mainSolution: null,
    definitions: [],
    solutions: [],
    userGrid: [],
    correctCells: [],
    loading: false,
    error: null,
  }),

  actions: {
    resetState() {
      this.id = null
      this.grid = null
      this.mainSolution = null
      this.definitions = []
      this.solutions = []
      this.userGrid = []
      this.correctCells = []
      this.loading = false
      this.error = null
    },

    async loadCrossword(id) {
      this.loading = true
      this.error = null

      try {
        const crossword = await fetchCrosswordById(id)

        this.id = id
        this.grid = crossword.grid
        this.mainSolution = crossword.main_solution ?? null
        this.definitions = crossword.definitions ?? []
        this.solutions = crossword.solutions ?? []

        this.userGrid = createUserGrid(crossword.grid)
        this.correctCells = createCorrectCells(crossword.grid)
      } catch (error) {
        this.error = error?.message ?? 'Hiba a rejtvény betoltese kozben.'
      } finally {
        this.loading = false
      }
    },

    updateCell(rowIndex, cellIndex, value) {
      if (!this.userGrid[rowIndex] || this.userGrid[rowIndex][cellIndex] === '#') {
        return
      }

      this.userGrid[rowIndex][cellIndex] = value
    },

    isRowFilled(rowIndex) {
      if (!this.userGrid[rowIndex]) {
        return false
      }

      return this.userGrid[rowIndex].every((cell) => cell === '#' || cell !== '')
    },

    validateRow(rowIndex) {
      if (!this.grid || !this.userGrid[rowIndex]) {
        return
      }

      this.userGrid[rowIndex].forEach((cell, cellIndex) => {
        const isBlackCell = this.grid[rowIndex][cellIndex] === '#'

        if (isBlackCell) {
          this.correctCells[rowIndex][cellIndex] = false
          return
        }

        this.correctCells[rowIndex][cellIndex] =
          cell !== '' && cell === this.grid[rowIndex][cellIndex]
      })
    },
  },
})
