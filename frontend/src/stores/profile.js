import { defineStore } from 'pinia'
import { getProfile } from '@/services/userApi'
import { setVisibility } from '@/services/crosswordCreatorApi'

export const useProfileStore = defineStore('profile', {
  state: () => ({
    /**
     * A felhasználó felhasználóneve, email címe és szerepköre.
     */
    user: null,
    /**
     * A felhasználó által létrehozott rejtvények listája. Minden rejtvény tartalmazza az azonosítóját, címét, hogy nyilvános-e és a létrehozás dátumát.
     */
    crosswords: [],
    /**
     * A felhasználó által megkezdett rejtvények listája.
     * Minden megkezdett rejtvény tartalmazza az azonosítóját, a rejtvény azonosítóját, a státuszát, az eltelt időt, a rejtvény címét és a nehézségét.
     */
    attempts: [],
    /**
     * A profil betöltésének állapota. Ha true, akkor a profil betöltése folyamatban van.
     */
    loading: true,
    /**
     * A profil betöltése során fellépő hibaüzenet. Ha null, akkor nincs hiba.
     */
    error: null,
    /**
     * A rejtvény láthatóságának váltása során fellépő hibaüzenet. Ha null, akkor nincs hiba.
     */
    visibilityError: null,
  }),

  actions: {
    /**
     * Betölti a felhasználói profilt és a hozzá tartozó rejtvényeket az API-ból.
     */
    async getProfile() {
      this.loading = true
      this.error = null

      try {
        const data = await getProfile()
        this.user = data.user
        this.crosswords = data.crosswords
        this.attempts = data.attempts

      } catch (error) {
        console.error('Hiba a profil betöltésekor:', error)
        this.error = error.message || 'Hiba történt a profil betöltésekor.'

      } finally {
        this.loading = false
      }
    },
    async setVisibility(crossword, isPublic) {
      crossword.is_updating = true
      this.visibilityError = null

      try {
        const response = await setVisibility(crossword.id, isPublic)
        crossword.is_public = response.is_public
        return true

      } catch (error) {
        this.visibilityError = error?.response?.data?.message ?? 'Hiba történt a rejtvény láthatóságának váltásakor.'
        return false

      } finally {
        crossword.is_updating = false
      }
    },
  },
})