import axios from 'axios'

export async function createCrossword(payload) {
  const response = await axios.post('/crossword', payload)

  if (!response.data.success || !response.data.crossword) {
    console.log('Érvénytelen API válasz:', response.data)
    throw new Error(response.data.message ?? 'Nem sikerült létrehozni a rejtvényt.')
  }

  return response.data.crossword
}

export async function createClue(payload) {
  const response = await axios.post('/createClue', payload)

  if (!response.data.success || !response.data.clue) {
    console.log('Érvénytelen API válasz:', response.data)
    throw new Error(response.data.message ?? 'Nem sikerült létrehozni a szót.')
  }

  return response.data.clue
}

export async function loadTopics() {
  const response = await axios.get('/topics')

  if (!response.data.success || !Array.isArray(response.data.topics)) {
    console.log('Érvénytelen API válasz:', response.data)
    throw new Error(response.data.message ?? 'Nem sikerült betölteni a témákat.')
  }

  return response.data.topics
}