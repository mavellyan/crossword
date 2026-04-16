import axios from 'axios'

export async function fetchCrosswordById(id) {
  const response = await axios.get(`/crossword/${id}`)

  const crossword = response?.data?.crossword

  if (!crossword || !Array.isArray(crossword.grid) || !Array.isArray(crossword.definitions)) {
    throw new Error('Invalid crossword payload from API')
  }

  return crossword
}
