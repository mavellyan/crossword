import { defineStore } from 'pinia'
import { getProfile } from '@/services/userApi'
import { toggleVisibility } from '@/services/crosswordCreatorApi'

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
  }),

  actions: {
    /**
     * Betölti a felhasználói profilt és a hozzá tartozó rejtvényeket az API-ból.
     */
    async getProfile() {

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
    async toggleVisibility(crossword) {
      const prevState = crossword.is_public
      crossword.is_public = !prevState
      crossword.is_updating = true

      try {
        const response = await toggleVisibility(crossword.id)

      } catch (error) {
        // Hiba esetén visszaállítjuk az előző állapotot
        crossword.is_public = prevState
        console.error('Hiba a rejtvény láthatóságának váltásakor:', error)

      } finally {
        crossword.is_updating = false
      }
    },
  },
})