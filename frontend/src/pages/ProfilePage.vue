<template>
  <div>
    <div v-if="!isPageLoaded" class="d-flex justify-center items-center h-screen">
      <div>Betöltés folyamatban...</div>
    </div>

    <div v-else-if="profileStore.error" class="alert alert-danger my-4">
      <div>Hiba történt a profil betöltése során: {{ profileStore.error }}</div>
    </div>

    <div v-else
      :class="{'blur-background': isPopupVisible}"
    >
      <h1 class="text-center mb-5">
        Üdv újra, <strong>{{ profileStore.user.username }}</strong>!
      </h1>

      <div class="row mx-3 g-4">
        <div class="col-12 col-md-6">
          <h2 class="h5 mb-3 text-center">Az általad létrehozott rejtvények:</h2>
    
          <div v-if="profileStore.crosswords.length === 0" class="text-muted">
            Még nem hoztál létre rejtvényt.
            <router-link to="/create">Itt</router-link> tudsz újat létrehozni.
          </div>

          <div v-else class="row row-cols-1 row-cols-md-2 g-3">
            <div v-for="crossword in profileStore.crosswords" :key="crossword.id" class="col">
              <div
                class="card h-100 shadow-sm pointer hover"
                @click="handleCrosswordClick(crossword.id, true)"
              >
                <div class="card-body d-flex flex-column justify-content-between">
                  <div>
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                      <h5 class="card-title fs-6">{{ crossword.title }}</h5>
                      <div class="form-check form-switch mb-0" @click.stop>
                        <input
                          class="form-check-input pointer"
                          :class="crossword.is_public ? 'bg-success' : 'bg-danger'"
                          type="checkbox"
                          role="switch"
                          :id="'toggle-public-' + crossword.id"
                          :checked="crossword.is_public"
                          :disabled="crossword.is_updating || (crossword.is_public && crossword.attempts_count > 0)"
                          @click.prevent.stop="onToggleClick(crossword)"
                        />
                      </div>
                    </div>
                    <span class="badge" :class="crossword.is_public ? 'bg-success' : 'bg-danger'">
                      {{ crossword.is_public ? 'Nyilvános' : 'Privát' }}
                    </span>
                  </div>
                  <div class="mt-2">
                    <div v-if="crossword.topics && crossword.topics.length > 0" class="text-muted small">
                      {{ crossword.topics.map(topic => topic.name).join(', ') }}
                    </div>
                    <div v-else class="text-muted small">
                      ÁLTALÁNOS
                    </div>
                  </div>

                  <div class="text-muted small mt-2">
                    {{ crossword.attempts_count > 0 ? 'Próbálkozások száma: ' + crossword.attempts_count : 'Még nincs próbálkozás.' }}
                    | Legjobb idő: {{  crossword.best_time !== null ? displayElapsedTime(crossword.best_time) : 'N/A' }}
                  </div>
                  <div class="text-muted small mt-2">
                    Létrehozva: {{ new Date(crossword.created_at).toLocaleDateString() }}
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

        <div class="col-12 col-md-6">
          <h2 class="h5 mb-3 text-center">Az általad megkezdett rejtvények:</h2>

          <div v-if="profileStore.attempts.length === 0" class="text-muted text-center">
            Még nem kezdtél meg rejtvényt.
            <router-link to="/crosswordlist">Itt</router-link> tudsz egyet választani és elkezdeni.
          </div>

          <div v-else class="row row-cols-1 row-cols-md-2 g-3">
            <div v-for="attempt in profileStore.attempts" :key="attempt.id" class="col">
              <div
                class="card h-100 shadow-sm pointer hover"
                @click="handleCrosswordClick(attempt.crossword_id, false)"
              >
                <div class="card-body d-flex flex-column justify-content-between">
                  <div>
                    <h5 class="card-title fs-6">{{ attempt.crossword_title }}</h5>
                    <span
                      class="badge"
                      :class="{
                        'easy': 'bg-success',
                        'medium': 'bg-warning text-dark',
                        'hard': 'bg-danger'
                      }[attempt.crossword_difficulty]"
                    >
                      {{ displayDifficulty(attempt.crossword_difficulty) }}
                    </span>
                  </div>
                  <div
                    class="mt-2 small"
                    :class="{
                      'abandoned': 'text-danger',
                      'in_progress': 'text-warning',
                      'completed': 'text-success'
                    }[attempt.status]"
                  >
                    {{ displayStatus(attempt.status) }}
                  </div>
                  <div class="mt-2 text-muted small">
                    Eltelt idő: {{ displayElapsedTime(attempt.elapsed_time) }}
                  </div>
                  <div class="mt-2 text-muted small">
                    Készítő: {{attempt.creator_username }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div
    v-if="isPageLoaded && isPopupVisible"
    class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-flex justify-content-center align-items-center z-3 p-3"
    @click.self="cancelPublish"
  >
    <div class="card shadow-lg p-4 p-md-5 text-center" style="max-width: 520px; width: 100%;">
      <h3 class="fw-bold mb-3">Biztosan nyilvánossá szeretnéd tenni a rejtvényed?</h3>
    
      <p class="text-muted mb-3">
        Ha nyilvánossá teszed a rejtvényed, az azt jelenti, hogy mindenki számára elérhetővé válik.
        Amennyiben valaki elkezdi megfejteni a rejtvényed, utána már <strong>nem tudod visszaállítani</strong> privát állapotba.
      </p>

      <div class="d-grid gap-2 col-11 mx-auto mt-2">
        <button 
          type="button" 
          class="btn btn-primary fw-bold text-uppercase shadow-sm" 
          @click="confirmPublish"
        >
          <span v-if="targetCrossword?.isUpdating" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
          Publikálás
        </button>
        
        <button 
          type="button" 
          class="btn btn-outline-secondary fw-bold text-uppercase shadow-sm" 
          :disabled="targetCrossword?.isUpdating"
          @click="cancelPublish"
        >
          Mégsem
        </button>
      </div>
    </div>
</div>
</template>

<script>
import { useProfileStore } from '../stores/profile';

export default {
  name: 'ProfilePage',
  data() {
    return {
      targetCrossword: null,
      isPopupVisible: false,
    }
  },
  computed: {
    profileStore() {
      return useProfileStore()
    },
    isPageLoaded() {
      return this.profileStore.user !== null && !this.profileStore.loading && !this.profileStore.error;
    },
  },
  methods: {
    /**
     * A kiválasztott rejtvény szerkesztése vagy megtekintése.
     * 
     * @param {number} crosswordId - a kiválasztott rejtvény azonosítója
     * @param {boolean} isEditorMode - jelzi, hogy a rejtvényt szerkesztő módban nyitjuk-e fel (saját rejtvény esetén)
     *                                 vagy megtekintő módban (más felhasználó által készített rejtvény esetén)
     */
    handleCrosswordClick(crosswordId, isEditorMode) {
      if (isEditorMode) {
        this.$router.push({ name: 'crosswordcreator', query: { id: crosswordId } })
        return
      } else {
        this.$router.push({ name: 'crossword', params: { id: crosswordId } })
      }
    },
    /**
     * Megjeleníti a rejtvény nehézségét magyar nyelven.
     * 
     * @param difficulty - a rejtvény nehézsége
     */
    displayDifficulty(difficulty) {
      switch (difficulty) {
        case 'medium':
          return 'Közepes';
        case 'hard':
          return 'Nehéz';
        default:
          return 'Könnyű';
      }
    },
    /**
     * Megjeleníti a próbálkozás állapotát magyar nyelven. A lehetséges állapotok: 'abandoned' (Félbehagyott), 'completed' (Befejezett), vagy 'in_progress' (Folyamatban).
     * 
     * @param status - a próbálkozás állapota
     */
    displayStatus(status) {
      switch (status) {
        case 'abandoned':
          return 'Félbehagyott';
        case 'completed':
          return 'Befejezett';
        default:
          return 'Folyamatban';
      }
    },
    /**
     * A próbálkozás közben eltelt idő megjelenítése. Ha az eltelt idő több mint 1 óra, akkor HH:MM:SS formátumban jeleníti meg, különben MM:SS formátumban.
     * 
     * @param elapsedTime - a próbálkozás közben eltelt idő másodpercben
     */
    displayElapsedTime(elapsedTime) {
      if (elapsedTime >= 3600) {
        const hours = Math.floor(elapsedTime / 3600);
        const minutes = Math.floor((elapsedTime % 3600) / 60);
        const seconds = elapsedTime % 60;

        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
      } else{
        const minutes = Math.floor(elapsedTime / 60);
        const seconds = elapsedTime % 60;

        return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
      }
    },
    /**
     * A felhasználó által készített rejtvény esetén amennyiben publikus, és már van megkezdett próbálkozás, nem engedélyezi a priváttá tételt.
     * Ha publikus, de még nincs megkezdett próbálkozás, akkor egyszerűen priváttá teszi a rejtvényt.
     * Viszont ha privát a rejtvény, akkor megjelenít egy megerősítő popup-ot, hogy biztosan nyilvánossá szeretné-e tenni a felhasználó a rejtvényt.
     * 
     * @param crossword - a rejtvény amelyet nyilvánossá/priváttá kíván tenni a felhasználó
     */
    onToggleClick(crossword) {
      if (crossword.is_public && crossword.attempts_count > 0) {
        return
      }

      if (!crossword.is_public) {
        this.targetCrossword = crossword
        this.isPopupVisible = true
        return;
      }

      this.profileStore.toggleVisibility(crossword)
    },
    /**
     * A felhasználó megerősítette, hogy nyilvánossá szeretné tenni a rejtvényt. Meghívja a store toggleVisibility metódusát, majd bezárja a popup-ot.
     */
    async confirmPublish() {
      if (this.targetCrossword) {
        await this.profileStore.toggleVisibility(this.targetCrossword)
      }
      this.isPopupVisible = false
      this.targetCrossword = null
    },
    /**
     * A felhasználó 'Mégsem'-re kattintott a nyilvánossá tételt megerősítő popup-on.
     */
    cancelPublish() {
      this.isPopupVisible = false
      this.targetCrossword = null
    },
  },
  async mounted() {
    await this.profileStore.getProfile()
  },
}
</script>

<style scoped lang="scss">
.pointer {
  cursor: pointer;
}

.hover:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.35) !important;
}

.blur-background {
  filter: blur(3px);
  pointer-events: none;
}
</style>