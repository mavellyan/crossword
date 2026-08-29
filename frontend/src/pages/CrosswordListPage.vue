<template>
  <div>
    <h1 class="text-center mb-5">
      Rejtvények
    </h1>

    <div class="d-flex">
      <div class="d-flex flex-column align-items-center w-25 bg-light mx-3 p-3 rounded sticky-top border">
        <h3>
          Szűrők
        </h3>
        <div v-if="authStore.isLoggedIn" class="mb-3 w-75 text-center">
          <label class="form-label mb-1">Státusz:</label>
          <div class="d-flex justify-content-center gap-2">
            <div
              class="badge pill border border-secondary border-2 bg-light text-dark px-2 hover-effect"
              :class="{'opacity-50': selectedStatus !== 'status_all'}"
              @click="setSelectedStatus('status_all')"
            >
              Mind
            </div>
            <div
              class="badge pill border border-secondary border-2 bg-info text-dark px-2 hover-effect"
              :class="{'opacity-50': selectedStatus !== 'status_new'}"
              @click="setSelectedStatus('status_new')"
            >
              Új
            </div>
            <div
              class="badge pill border border-secondary border-2 bg-warning text-dark px-2 hover-effect"
              :class="{'opacity-50': selectedStatus !== 'status_in_progress'}"
              @click="setSelectedStatus('status_in_progress')"
           >
              Folyamatban
            </div>
            <div
              class="badge pill border border-secondary border-2 bg-success text-dark px-2 hover-effect"
              :class="{'opacity-50': selectedStatus !== 'status_completed'}"
              @click="setSelectedStatus('status_completed')"
            >
              Befejezett
            </div>
          </div>
        </div>
        <div class="mb-3 w-75 text-center">
          <label class="form-label mb-1">Témakör:</label>
          <v-select
            v-model="selectedTopics"
            name="topic-v-select"
            class="rounded"
            label="name"
            :placeholder="'Válassz témakört...'"
            :options="topicOptions"
            multiple
            @update:modelValue="loadCrosswords"
          ></v-select>
        </div>
        <div class="mb-3 w-75 text-center">
          <label class="form-label mb-1">Nehézség:</label>
          <div>
            <div
              class="form-check form-check-inline"
              v-for="difficulty in difficultyOptions"
              :key="difficulty.value"
            >
              <input
                v-model="selectedDifficulties"
                class="form-check-input"
                type="checkbox"
                :id="'difficulty-' + difficulty.value"
                :value="difficulty.value"
                @change="loadCrosswords"
              />
              <label class="form-check-label" :for="'difficulty-' + difficulty.value">
                {{ difficulty.label }}
              </label>
            </div>
          </div>
        </div>
        <div class="mb-3 w-75 text-center">
          <label class="form-label mb-1">Készítő:</label>
          <v-select
            v-model="selectedCreators"
            name="creator-v-select"
            class="rounded"
            label="username"
            :placeholder="'Válassz készítőt...'"
            :options="creatorOptions"
            multiple
            @update:modelValue="loadCrosswords"
          ></v-select>
        </div>
        <div class="mt-3 w-75 text-center">
          <button
            type="button"
            class="btn btn-secondary w-100 d-flex justify-content-center align-items-center gap-2"
            @click="clearFilters"
          >
            Szűrők törlése
            <font-awesome-icon icon="fa-solid fa-trash-can" />
          </button>
        </div>
      </div>
      <div class="w-75">
        <div class="d-flex align-items-center justify-content-center mb-3">
          <input
            v-model="search"
            type="text"
            class="form-control w-75 mx-auto mb-3"
            placeholder="Keresés cím alapján..."
            @input="loadCrosswords"
          />
          <select
            v-model="sortOrder"
            class="form-control w-auto mx-auto mb-3"
            @change="loadCrosswords"
          >
            <option value="dateDesc" selected>Legújabb</option>
            <option value="dateAsc">Legrégebbi</option>
            <option value="titleAsc">Cím szerint (A-Z)</option>
            <option value="titleDesc">Cím szerint (Z-A)</option>
          </select>
          </div>
        <div v-if="isListVisible" class="list-group w-100">
          <RouterLink
            v-for="crossword in crosswords"
            :key="crossword.id"
            :to="{ name: 'crossword', params: { id: crossword.id } }"
            class="list-group-item list-group-item-action w-50 mx-auto"
          >
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <h5 class="mb-1">
                  {{ crossword.title }}
                </h5>

                <small>
                  Szavak száma: {{ crossword.words_count }}
                  <span v-if="crossword.topics && crossword.topics.length > 0">
                    | {{ crossword.topics.map(topic => topic.name).join(', ') }}
                  </span>
                  <span v-else>
                    | ÁLTALÁNOS
                  </span>
                  <span v-if="crossword.creator">
                    | Készítő: {{ crossword.creator.username }}
                  </span>
                </small>
              </div>

              <div class="d-flex flex-column gap-2 align-items-end">
                <small class="text-muted">
                  {{ crossword.created_at }}
                </small>
                <small>
                  <span v-if="crossword.status === 'in_progress'" class="badge bg-warning">
                    Megkezdve
                  </span>
                  <span v-else-if="crossword.status === 'completed'" class="badge bg-success">
                    Befejezve
                  </span>
                </small>
              </div>
            </div>
          </RouterLink>
        </div>
        <div v-else class="text-center">
          <p v-if="loading">Betöltés...</p>
          <p v-else-if="error" class="alert alert-danger">
            {{ error }}
          </p>
          <p v-else-if="crosswords.length === 0 && listLoaded" class="text-muted">
            Még nincs elérhető rejtvény.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { listCrosswords, listTopics } from '@/services/crosswordApi'
import { listCreators } from '@/services/userApi'
import { useAuthStore } from '@/stores/auth';

export default {
  name: 'CrosswordListPage',
  data() {
    return {
      authStore: useAuthStore(),
      /**
       * A betöltött rejtvények listája.
       * 
       * @type {Array}
       */
      crosswords: [],
      /**
       * Betöltés alatt áll-e a rejtvények listája.
       * 
       * @type {boolean}
       */
      loading: false,
      /**
       * A rejtvények listája betöltve van-e. Ennek segítségével tudjuk eldönteni, hogy a "Még nincs elérhető rejtvény" üzenetet megjelenítsük-e.
       * 
       * @type {boolean}
       */
      listLoaded: false,
      /**
       * A rejtvények listájának betöltése közben fellépő hibaüzenet.
       * 
       * @type {string|null}
       */
      error: null,
      /**
       * A keresőmezőben megadott keresési kifejezés.
       * 
       * @type {string}
       */
      search: '',
      /**
       * A kereséshez használt időzítő az input események kezelésére.
       * Ez lehetővé teszi, hogy a felhasználó gépelése közben ne történjen azonnali keresés, hanem csak egy rövid késleltetés után.
       * 
       * @type {number|null}
       */
      searchTimeout: null,
      /**
       * Az elérhető témakörök listája.
       * 
       * @type {Array}
       */
      topicOptions: [],
      /**
       * A kiválasztott témakörök a szűréshez.
       * 
       * @type {Array}
       */
      selectedTopics: [],
      /**
       * Az elérhető nehézségi szintek listája.
       * 
       * @type {Array}
       */
      difficultyOptions: [
        { value: 'easy', label: 'Könnyű' },
        { value: 'medium', label: 'Közepes' },
        { value: 'hard', label: 'Nehéz' },
      ],
      /**
       * A kiválasztott nehézségi szintek a szűréshez.
       * 
       * @type {Array}
       */
      selectedDifficulties: [],
      /**
       * Az elérhető készítők listája.
       * 
       * @type {Array}
       */
      creatorOptions: [],
      /**
       * A kiválasztott szerzők a szűréshez.
       * 
       * @type {Array}
       */
      selectedCreators: [],
      /**
       * A rejtvények listájának rendezési sorrendje. Alapértelmezetten a legújabb van legelől.
       * 
       * @type {string}
       */
      sortOrder: 'dateDesc',
      /**
       * A kiválasztott rejtvény státusz a szűréshez. Alapértelmezetten minden státusz megjelenik.
       * Csak bejelentkezett felhasználóknál van jelentősége, mivel vendégeknél nincs eltárolva a megfejtés.
       * 
       * @type {string}
       */
      selectedStatus: 'status_all',
    }
  },
  mounted() {
    this.loadCrosswords()
    this.loadTopics()
    this.loadCreators()
  },
  computed: {
    isListVisible() {
      return this.crosswords.length > 0 && !this.loading && !this.error && this.listLoaded
    },
  },
  methods: {
    loadCrosswords() {
      clearTimeout(this.searchTimeout)

      this.searchTimeout = setTimeout(async () => {
        this.loading = true
        this.error = null

        try {
          this.crosswords = await listCrosswords({
            search: this.search || undefined,
            topics: this.selectedTopics.length > 0 ? this.selectedTopics.map(topic => topic.id) : undefined,
            difficulties: this.selectedDifficulties.length > 0 ? this.selectedDifficulties : undefined,
            creators: this.selectedCreators.length > 0 ? this.selectedCreators.map(creator => creator.id) : undefined,
            sortOrder: this.sortOrder,
            status: this.selectedStatus,
          })
        } catch (error) {
          console.log('crossword list error:', error)
          this.error = error?.message ?? 'Hiba a rejtvények betöltése közben.'
          this.crosswords = []
        } finally {
          this.loading = false
          this.listLoaded = true
        }
      }, 300)
    },
    loadTopics() {
      listTopics()
        .then(topics => {
          this.topicOptions = topics
        })
        .catch(error => {
          console.error('Hiba a témakörök betöltése közben:', error)
        })
    },
    loadCreators() {
      listCreators()
        .then(creators => {
          this.creatorOptions = creators
        })
        .catch(error => {
          console.error('Hiba a készítők betöltése közben:', error)
        })
    },
    clearFilters() {
      this.selectedTopics = []
      this.selectedDifficulties = []
      this.selectedCreators = []
      this.search = ''
      this.sortOrder = 'dateDesc'
      this.selectedStatus = 'status_all'
      this.loadCrosswords()
    },
    setSelectedStatus(status) {
      this.selectedStatus = status
      this.loadCrosswords()
    },
  },
}
</script>

<style lang="scss" scoped>

.hover-effect {
  cursor: pointer;
  transition: opacity 0.3s;

  &:hover {
    opacity: 1 !important;
  }
}

</style>