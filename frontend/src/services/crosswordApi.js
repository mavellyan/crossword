import axios from 'axios'

export async function loadCrosswordById(id) {
  const response = await axios.get('/crossword', {
    params: {
      id: id,
    }
  })

  if (!response.data.success) {
    throw new Error(response.data.message ?? 'Hiba a rejtvény betöltése közben.')
  }

  const crossword = response.data.crossword
  const attempt = response.data.attempt

  if (!crossword || !Array.isArray(crossword.grid)) {
    console.log('Hibás API válasz:', response.data)
    throw new Error('Hibás API válasz a rejtvény betöltésekor.')
  }

  return { crossword, attempt }
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

export async function saveCrosswordProgress(attemptId, wordInputs, stateVersion) {
  const response = await axios.post('/saveProgress', {
    attempt_id: attemptId,
    word_inputs: wordInputs,
    state_version: stateVersion,
  })

  if (!response.data.success) {
    console.log('Hibás API válasz:', response.data)
    throw new Error(response.data.message ?? 'Hiba a rejtvény mentése közben.')
  }

  return response.data
}

export async function startAttempt(attemptId) {
  const response = await axios.post('/startAttempt', {
    attempt_id: attemptId,
  })

  if (!response.data.success) {
    console.log('Hibás API válasz:', response.data)
    throw new Error(response.data.message ?? 'Nem sikerült elindítani a rejtvény próbálkozást.')
  }

  return response.data
}

export async function stopAttempt(attemptId) {
  const response = await axios.post('/stopAttempt', {
    attempt_id: attemptId,
  })
  
  if (!response.data.success) {
    console.log('Hibás API válasz:', response.data)
    throw new Error(response.data.message ?? 'Nem sikerült leállítani a rejtvény próbálkozást.')
  }
  
  return response.data
}