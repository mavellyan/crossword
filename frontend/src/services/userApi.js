import axios from 'axios'

export async function listCreators() {
    const response = await axios.get('/creators')

    if (!response.data.success || !Array.isArray(response.data.creators)) {
        throw new Error(response.data.message ?? 'Nem sikerült betölteni a rejtvénykészítőket.')
    }

    return response.data.creators
}

export async function getProfile() {
    const response = await axios.get('/profile')

    if (!response.data.success || !response.data.user) {
        throw new Error(response.data.message ?? 'Nem sikerült betölteni a felhasználói profilt.')
    }

    return response.data
}