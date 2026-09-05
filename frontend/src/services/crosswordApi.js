import axios from 'axios'

export async function loadCrossword(id) {
  const response = await axios.get('/getCrossword', {
    params: {
      id: id,
    }
  })

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

export async function listCrosswords(params = {}) {
  const response = await axios.get('/listCrosswords', {
    params: {
      ...params,
    },
  })

  if (!response.data.success || !Array.isArray(response.data.crosswords)) {
    console.log('Hibás API válasz:', response.data)
    throw new Error('Hibás API válasz a rejtvénylista betöltésekor.')
  }

  return response.data.crosswords
}


export async function listTopics() {
  const response = await axios.get('/topics')

  if (!response.data.success || !Array.isArray(response.data.topics)) {
    console.log('Érvénytelen API válasz:', response.data)
    throw new Error(response.data.message ?? 'Nem sikerült betölteni a témákat.')
  }

  return response.data.topics
}

export async function validateWordForGuest(crosswordId, wordIndex, userInput) {
  const response = await axios.post('/validateWordForGuest', {
    crossword_id: crosswordId,
    word_index: wordIndex,
    user_input: userInput,
  })

  if (!response.data.success) {
    console.log('Hibás API válasz:', response.data)
    throw new Error(response.data.message ?? 'Nem sikerült ellenőrizni a szót.')
  }

  return response.data.is_correct
}