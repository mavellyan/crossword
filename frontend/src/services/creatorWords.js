import axios from 'axios'

export async function fetchCreatorWords() {
  const response = await axios.get('/creator-words')

  if (!response.data.success || !Array.isArray(response.data.words)) {
    console.log('Érvénytelen válasz:', response.data)
    throw new Error('Nem sikerült betölteni a választható szavakat.')
  }

  return response.data.words.map((word) => ({
    id: word.id,
    solution: String(word.solution ?? '').toUpperCase(),
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