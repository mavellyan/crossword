import axios from 'axios'

export async function createCrossword(payload) {
  const response = await axios.post('/crossword', payload)

  if (!response.data.success || !response.data.crossword) {
    console.log('Érvénytelen API válasz:', response.data)
    throw new Error(response.data.message ?? 'Nem sikerült létrehozni a rejtvényt.')
  }

  return response.data.crossword
}