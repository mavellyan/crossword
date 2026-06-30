import axios from 'axios'

export async function fetchCrosswordById(id) {
  const response = await axios.get(`/crossword/${id}`)

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
  const response = await axios.get('/list-crosswords', {
    params,
  })

  if (!response.data.success || !Array.isArray(response.data.crosswords)) {
    console.log('Hibás API válasz:', response.data)
    throw new Error('Hibás API válasz a rejtvénylista betöltésekor.')
  }

  return response.data.crosswords
}