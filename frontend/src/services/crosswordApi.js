import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

export async function fetchCrosswordById(id) {
  const response = await axios.get('/crossword', {
    params: {
      id: id,
      user_id: useAuthStore().userId,
    }
  })

  console.log('user_id:', useAuthStore().userId)

  if (!response.data.success) {
    throw new Error(response.data.message ?? 'Hiba a rejtvény betöltése közben.')
  }

  const crossword = response.data.crossword

  if (!crossword || !Array.isArray(crossword.grid)) {
    console.log('Hibás API válasz:', response.data)
    throw new Error('Hibás API válasz a rejtvény betöltésekor.')
  }

  return crossword
}

export async function fetchCrosswords(params = {}) {
  const response = await axios.get('/listCrosswords', {
    params: {
      ...params,
      user_id: useAuthStore().userId,
    },
  })

  if (!response.data.success || !Array.isArray(response.data.crosswords)) {
    console.log('Hibás API válasz:', response.data)
    throw new Error('Hibás API válasz a rejtvénylista betöltésekor.')
  }

  return response.data.crosswords
}

export async function saveCrosswordProgress(crosswordId, words, grid) {
  const response = await axios.post('/saveProgress', {
    crossword_id: crosswordId,
    user_id: useAuthStore().userId,
    words: words,
    grid: grid,
  })

  if (!response.data.success) {
    console.log('Hibás API válasz:', response.data)
    throw new Error(response.data.message ?? 'Hiba a rejtvény mentése közben.')
  }

  return response.data
}