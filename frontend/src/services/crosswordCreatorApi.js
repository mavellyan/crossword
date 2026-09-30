import axios from 'axios'

export async function createCrossword(payload) {
  const response = await axios.post('/createCrossword', payload)

  if (!response.data.success || !response.data.crossword) {
    throw new Error(response.data.message ?? 'Nem sikerült létrehozni a rejtvényt.')
  }

  return response.data.crossword
}

export async function getCrosswordForEdit(crosswordId) {
  const response = await axios.get('/getCrosswordForEdit', {
    params: { id: crosswordId }
  })

  if (!response.data.success || !response.data.crossword) {
    throw new Error(response.data.message ?? 'Nem sikerült betölteni a rejtvényt szerkesztéshez.')
  }

  return response.data.crossword
}

export async function updateCrossword(crosswordId, payload) {
  const response = await axios.put('/updateCrossword', { id: crosswordId, ...payload })

  if (!response.data.success || !response.data.crossword) {
    throw new Error(response.data.message ?? 'Nem sikerült frissíteni a rejtvényt.')
  }

  return response.data.crossword
}

export async function createClue(payload) {
  const response = await axios.post('/createClue', payload)

  if (!response.data.success || !response.data.clue) {
    throw new Error(response.data.message ?? 'Nem sikerült létrehozni a szót.')
  }

  return response.data.clue
}

export async function listCreatorWords(topicIds = null) {
  const response = await axios.get('/clues', {
    params: {
      topic_ids: topicIds
    }
  })

  if (!response.data.success || !Array.isArray(response.data.words)) {
    throw new Error('Nem sikerült betölteni a választható szavakat.')
  }

  return response.data.words.map((word) => ({
    id: word.id,
    solution: word.solution ?? '',
    definition: word.definition ?? '',
    length: word.length ?? String(word.solution ?? '').length,
  }))
}

export function getWordsForLetterFromList(words, letter) {
  if (!letter) {
    return []
  }

  const normalizedLetter = letter.toUpperCase()

  return words.filter((word) =>
    String(word.solution ?? '').toUpperCase().includes(normalizedLetter)
  )
}

export async function setVisibility(crosswordId, isPublic) {
  try {
    const response = await axios.patch('/setVisibility', { 
      id: crosswordId,
      is_public: isPublic
    })

    if (!response.data.success) {
      throw new Error(response.data.message ?? 'Nem sikerült váltani a rejtvény láthatóságát.')
    }

    return response.data
  } catch (error) {
    console.error('Hiba a rejtvény láthatóságának váltásakor:', error)
    throw error
  }
}

export async function deleteCrossword(crosswordId) {
  try {
    const response = await axios.delete('/deleteCrossword', 
      { params: { id: crosswordId }
    })

    if (!response.data.success) {
      throw new Error(response.data.message ?? 'Nem sikerült törölni a rejtvényt.')
    }

    return response.data.success
  } catch (error) {
    console.error('Hiba a rejtvény törlésekor:', error)
    throw error
  }
}