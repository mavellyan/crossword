import axios from 'axios'

export async function listCreators() {
    const response = await axios.get('/creators')

    if (!response.data.success || !Array.isArray(response.data.creators)) {
        console.log('Érvénytelen API válasz:', response.data)
        throw new Error(response.data.message ?? 'Nem sikerült betölteni a rejtvénykészítőket.')
    }

    return response.data.creators
}