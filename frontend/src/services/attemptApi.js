import axios from 'axios'

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