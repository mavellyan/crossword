import { defineStore } from 'pinia'
import { fetchCrosswordById } from '@/services/crosswordApi'

function createUserGrid(grid) {
  return grid.map((row) => row.map((cell) => (cell === '#' ? '#' : '')))
}

export const useCrosswordStore = defineStore('crossword', {
  state: () => ({
    id: null,
    grid: null,
    mainSolution: null,
    words: null,
    width: null,
    height: null,
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
      this.words = null
      this.width = null
      this.height = null
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
        this.words = crossword.words ?? []
        this.width = crossword.width ?? null
        this.height = crossword.height ?? null

        this.userGrid = createUserGrid(crossword.grid)
      } catch (error) {
        this.error = error?.message ?? 'Hiba a rejtvény betoltese kozben.'
      } finally {
        this.loading = false
      }
    },
  },
})
