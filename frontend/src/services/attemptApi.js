import axios from 'axios'

export async function loadAttempt(id) {
  const response = await axios.get('/getAttempt', {
    params: {
      crossword_id: id,
    }
  })

  if (!response.data.success) {
    throw new Error(response.data.message ?? 'Hiba a rejtvény betöltése közben.')
  }

  const attempt = response.data.attempt
  const bestTime = response.data.best_time

  if (!attempt) {
    console.log('Hibás API válasz:', response.data)
    throw new Error('Hibás API válasz a rejtvény betöltésekor.')
  }

  return { attempt, bestTime }
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

export async function abandonAttempt(attemptId) {
  const response = await axios.post('/abandonAttempt', {
    attempt_id: attemptId,
  })
  
  if (!response.data.success) {
    console.log('Hibás API válasz:', response.data)
    throw new Error(response.data.message ?? 'Nem sikerült feladni a rejtvény próbálkozást.')
  }
  
  return response.data
}


export async function saveProgress(attemptId, wordInputs, stateVersion) {
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

export function saveAndStopBeacon(attemptId, wordInputs, stateVersion) {
  return fetch(axios.defaults.baseURL + '/saveAndStopBeacon', {
    method: 'POST',
    keepalive: true,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...(localStorage.getItem('token') ? { 'Authorization': `Bearer ${localStorage.getItem('token')}` } : {}),
    },
    body: JSON.stringify({
      attempt_id: attemptId,
      word_inputs: wordInputs,
      state_version: stateVersion,
    }),
  })
}