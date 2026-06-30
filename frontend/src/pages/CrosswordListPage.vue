<template>
  <div>
    <h1 class="text-center mb-5">
      Rejtvények
    </h1>

    <div class="mb-3">
      <input
        v-model="search"
        type="text"
        class="form-control w-75 mx-auto mb-3"
        placeholder="Keresés cím vagy főmegoldás alapján..."
        @input="loadCrosswords"
      />
    </div>

    <div v-if="isListVisible" class="list-group">
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

            <p class="mb-1 text-muted">
              Főmegoldás: {{ crossword.main_solution }}
            </p>

            <small>
              Szavak száma: {{ crossword.words_count }}
              <span v-if="crossword.creator">
                | Készítő: {{ crossword.creator.username }}
              </span>
            </small>
          </div>

          <small class="text-muted">
            {{ crossword.created_at }}
          </small>
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
</template>

<script>
import { fetchCrosswords } from '@/services/crosswordApi'

export default {
  name: 'CrosswordListPage',
  data() {
    return {
      crosswords: [],
      loading: false,
      listLoaded: false,
      error: null,
      search: '',
      searchTimeout: null,
    }
  },
  mounted() {
    this.loadCrosswords()
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
          this.crosswords = await fetchCrosswords({
            search: this.search || undefined,
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
  },
}
</script>