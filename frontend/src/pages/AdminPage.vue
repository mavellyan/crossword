<template>
  <main class="admin-page container py-4">
    <header class="admin-header mb-4">
      <div>
        <p class="admin-eyebrow">Adminisztráció</p>
        <h1>Admin vezérlőpult</h1>
        <p class="mb-0">
          Felhasználók, rejtvények és szavak kezelése.
        </p>
      </div>

      <i class="bi bi-shield-lock admin-header-icon" aria-hidden="true"></i>
    </header>

    <div
      v-if="successMessage"
      class="alert alert-success alert-dismissible"
      role="status"
    >
      {{ successMessage }}
      <button
        type="button"
        class="btn-close"
        aria-label="Bezárás"
        @click="successMessage = null"
      ></button>
    </div>

    <div
      v-if="errorMessage"
      class="alert alert-danger alert-dismissible"
      role="alert"
    >
      {{ errorMessage }}
      <button
        type="button"
        class="btn-close"
        aria-label="Bezárás"
        @click="errorMessage = null"
      ></button>
    </div>

    <nav class="admin-tabs nav nav-pills mb-4" aria-label="Admin menü">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        class="nav-link"
        :class="{ active: activeTab === tab.id }"
        @click="selectTab(tab.id)"
      >
        <i :class="tab.icon" aria-hidden="true"></i>
        {{ tab.label }}
      </button>
    </nav>

    <section v-if="activeTab === 'overview'">
      <div v-if="loading" class="admin-loading">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Betöltés...</span>
        </div>
      </div>

      <div v-else class="statistics-grid">
        <article
          v-for="statistic in statisticCards"
          :key="statistic.key"
          class="statistic-card"
        >
          <div class="statistic-icon">
            <i :class="statistic.icon" aria-hidden="true"></i>
          </div>
          <div>
            <span class="statistic-value">
              {{ statistic.value }}
            </span>
            <span class="statistic-label">
              {{ statistic.label }}
            </span>
          </div>
        </article>
      </div>
    </section>

    <section v-else-if="activeTab === 'users'">
      <div class="section-toolbar">
        <div>
          <h2>Felhasználók</h2>
          <p>Felhasználói fiókok áttekintése és letiltása.</p>
        </div>

        <form class="admin-search" @submit.prevent="loadUsers(1)">
          <input
            v-model.trim="search.users"
            type="search"
            class="form-control"
            placeholder="Név vagy email..."
            aria-label="Felhasználó keresése"
          />
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-search" aria-hidden="true"></i>
            Keresés
          </button>
        </form>
      </div>

      <div class="admin-table-card">
        <div v-if="loading" class="admin-loading">
          <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Felhasználó</th>
                <th>Szerepkör</th>
                <th>Rejtvények</th>
                <th>Próbálkozások</th>
                <th>Állapot</th>
                <th class="text-end">Művelet</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in users" :key="user.id">
                <td>
                  <strong>{{ user.username }}</strong>
                  <div class="text-muted small">{{ user.email }}</div>
                </td>
                <td>
                  <span
                    class="badge"
                    :class="user.role === 'admin'
                      ? 'text-bg-primary'
                      : 'text-bg-secondary'"
                  >
                    {{ user.role === 'admin' ? 'Admin' : 'Felhasználó' }}
                  </span>
                </td>
                <td>{{ user.crosswords_count }}</td>
                <td>{{ user.attempts_count }}</td>
                <td>
                  <span
                    class="badge"
                    :class="user.is_active
                      ? 'text-bg-success'
                      : 'text-bg-danger'"
                  >
                    {{ user.is_active ? 'Aktív' : 'Letiltva' }}
                  </span>
                </td>
                <td class="text-end">
                  <button
                    type="button"
                    class="btn btn-sm"
                    :class="user.is_active
                      ? 'btn-outline-danger'
                      : 'btn-outline-success'"
                    @click="toggleUserStatus(user)"
                  >
                    {{ user.is_active ? 'Letiltás' : 'Aktiválás' }}
                  </button>
                </td>
              </tr>

              <tr v-if="!users.length">
                <td colspan="6" class="empty-table">
                  Nem található felhasználó.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="pagination-row">
        <button
          type="button"
          class="btn btn-outline-primary"
          :disabled="userPagination.currentPage <= 1 || loading"
          @click="loadUsers(userPagination.currentPage - 1)"
        >
          Előző
        </button>

        <span>
          {{ userPagination.currentPage }} /
          {{ userPagination.lastPage }}
        </span>

        <button
          type="button"
          class="btn btn-outline-primary"
          :disabled="
            userPagination.currentPage >= userPagination.lastPage || loading
          "
          @click="loadUsers(userPagination.currentPage + 1)"
        >
          Következő
        </button>
      </div>
    </section>

    <section v-else-if="activeTab === 'crosswords'">
      <div class="section-toolbar">
        <div>
          <h2>Rejtvények</h2>
          <p>A közzétett és privát rejtvények moderálása.</p>
        </div>

        <form class="admin-search" @submit.prevent="loadCrosswords(1)">
          <input
            v-model.trim="search.crosswords"
            type="search"
            class="form-control"
            placeholder="Rejtvény címe..."
            aria-label="Rejtvény keresése"
          />
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-search" aria-hidden="true"></i>
            Keresés
          </button>
        </form>
      </div>

      <div class="admin-table-card">
        <div v-if="loading" class="admin-loading">
          <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Cím</th>
                <th>Készítő</th>
                <th>Szavak</th>
                <th>Próbálkozások</th>
                <th>Állapot</th>
                <th class="text-end">Művelet</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="crossword in crosswords" :key="crossword.id">
                <td>
                  <strong>{{ crossword.title }}</strong>
                  <div class="text-muted small">
                    {{ formatDate(crossword.created_at) }}
                  </div>
                </td>
                <td>{{ crossword.creator || 'Ismeretlen' }}</td>
                <td>{{ crossword.words_count }}</td>
                <td>{{ crossword.attempts_count }}</td>
                <td>
                  <span
                    class="badge"
                    :class="crossword.is_public
                      ? 'text-bg-success'
                      : 'text-bg-secondary'"
                  >
                    {{ crossword.is_public ? 'Nyilvános' : 'Privát' }}
                  </span>
                </td>
                <td class="text-end action-buttons">
                  <RouterLink
                    v-if="crossword.is_public"
                    :to="{
                      name: 'crossword',
                      params: { id: crossword.id },
                    }"
                    class="btn btn-sm btn-outline-primary"
                  >
                    Megnyitás
                  </RouterLink>

                  <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    @click="removeCrossword(crossword)"
                  >
                    Törlés
                  </button>
                </td>
              </tr>

              <tr v-if="!crosswords.length">
                <td colspan="6" class="empty-table">
                  Nem található rejtvény.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="pagination-row">
        <button
          type="button"
          class="btn btn-outline-primary"
          :disabled="crosswordPagination.currentPage <= 1 || loading"
          @click="loadCrosswords(crosswordPagination.currentPage - 1)"
        >
          Előző
        </button>

        <span>
          {{ crosswordPagination.currentPage }} /
          {{ crosswordPagination.lastPage }}
        </span>

        <button
          type="button"
          class="btn btn-outline-primary"
          :disabled="
            crosswordPagination.currentPage >=
              crosswordPagination.lastPage || loading
          "
          @click="loadCrosswords(crosswordPagination.currentPage + 1)"
        >
          Következő
        </button>
      </div>
    </section>

    <section v-else-if="activeTab === 'clues'">
      <div class="section-toolbar">
        <div>
          <h2>Szavak és meghatározások</h2>
          <p>A szótárban található bejegyzések kezelése.</p>
        </div>

        <form class="admin-search" @submit.prevent="loadClues(1)">
          <input
            v-model.trim="search.clues"
            type="search"
            class="form-control"
            placeholder="Megoldás vagy meghatározás..."
            aria-label="Szó keresése"
          />
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-search" aria-hidden="true"></i>
            Keresés
          </button>
        </form>
      </div>

      <div class="admin-table-card">
        <div v-if="loading" class="admin-loading">
          <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Megoldás</th>
                <th>Meghatározás</th>
                <th>Témák</th>
                <th>Felhasználás</th>
                <th class="text-end">Művelet</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="clue in clues" :key="clue.id">
                <td>
                  <strong>{{ clue.solution }}</strong>
                </td>
                <td>{{ clue.definition }}</td>
                <td>
                  <span
                    v-for="topic in clue.topics"
                    :key="topic.id"
                    class="badge text-bg-light me-1"
                  >
                    {{ topic.name }}
                  </span>

                  <span
                    v-if="!clue.topics.length"
                    class="text-muted small"
                  >
                    Általános
                  </span>
                </td>
                <td>{{ clue.placements_count }}</td>
                <td class="text-end">
                  <span
                    class="d-inline-block"
                    tabindex="0"
                    v-tooltip.hover.right="
                      clue.placements_count > 0
                        ? 'Ez a szó jelenleg egy vagy több rejtvényben szerepel, ezért nem törölhető.'
                        : 'Szó törlése'
                    "
                  >
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-danger"
                      :disabled="clue.placements_count > 0"
                      :class="{ 'no-pointer-events': clue.placements_count > 0 }"
                      @click="removeClue(clue)"
                    >
                      Törlés
                    </button>
                  </span>
                </td>
              </tr>

              <tr v-if="!clues.length">
                <td colspan="5" class="empty-table">
                  Nem található szó.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="pagination-row">
        <button
          type="button"
          class="btn btn-outline-primary"
          :disabled="cluePagination.currentPage <= 1 || loading"
          @click="loadClues(cluePagination.currentPage - 1)"
        >
          Előző
        </button>

        <span>
          {{ cluePagination.currentPage }} /
          {{ cluePagination.lastPage }}
        </span>

        <button
          type="button"
          class="btn btn-outline-primary"
          :disabled="
            cluePagination.currentPage >= cluePagination.lastPage || loading
          "
          @click="loadClues(cluePagination.currentPage + 1)"
        >
          Következő
        </button>
      </div>
    </section>
  </main>
</template>

<script>
import {
  deleteAdminClue,
  deleteAdminCrossword,
  listAdminClues,
  listAdminCrosswords,
  listAdminUsers,
  loadAdminDashboard,
  setAdminUserStatus,
} from '@/services/adminApi'

const emptyPagination = () => ({
  currentPage: 1,
  lastPage: 1,
  total: 0,
})

export default {
  name: 'AdminPage',

  data() {
    return {
      activeTab: 'overview',
      loading: false,
      errorMessage: null,
      successMessage: null,

      statistics: {
        users: 0,
        active_users: 0,
        crosswords: 0,
        public_crosswords: 0,
        clues: 0,
        completed_attempts: 0,
      },

      users: [],
      crosswords: [],
      clues: [],

      search: {
        users: '',
        crosswords: '',
        clues: '',
      },

      userPagination: emptyPagination(),
      crosswordPagination: emptyPagination(),
      cluePagination: emptyPagination(),

      tabs: [
        {
          id: 'overview',
          label: 'Áttekintés',
          icon: 'bi bi-speedometer2',
        },
        {
          id: 'users',
          label: 'Felhasználók',
          icon: 'bi bi-people',
        },
        {
          id: 'crosswords',
          label: 'Rejtvények',
          icon: 'bi bi-grid-3x3-gap',
        },
        {
          id: 'clues',
          label: 'Szavak',
          icon: 'bi bi-card-text',
        },
      ],
    }
  },

  computed: {
    statisticCards() {
      return [
        {
          key: 'users',
          label: 'Felhasználók',
          value: this.statistics.users,
          icon: 'bi bi-people',
        },
        {
          key: 'active_users',
          label: 'Aktív felhasználók',
          value: this.statistics.active_users,
          icon: 'bi bi-person-check',
        },
        {
          key: 'crosswords',
          label: 'Rejtvények',
          value: this.statistics.crosswords,
          icon: 'bi bi-grid-3x3-gap',
        },
        {
          key: 'public_crosswords',
          label: 'Nyilvános rejtvények',
          value: this.statistics.public_crosswords,
          icon: 'bi bi-eye',
        },
        {
          key: 'clues',
          label: 'Szavak',
          value: this.statistics.clues,
          icon: 'bi bi-card-text',
        },
        {
          key: 'completed_attempts',
          label: 'Befejezett próbálkozások',
          value: this.statistics.completed_attempts,
          icon: 'bi bi-trophy',
        },
      ]
    },
  },

  mounted() {
    this.loadStatistics()
  },

  methods: {
    /**
     * Törli a korábbi sikeres és hibás műveletek üzeneteit.
     *
     * @returns {void}
     */
    clearMessages() {
      this.errorMessage = null
      this.successMessage = null
    },
    /**
     * Kinyeri az API-hibából a megjeleníthető hibaüzenetet.
     *
     * @param {object} error A bekövetkezett hiba.
     * @param {string} fallback Az alapértelmezett hibaüzenet.
     * @returns {string} A felhasználónak megjelenítendő üzenet.
     */
    errorText(error, fallback) {
      return error?.response?.data?.message
        ?? error?.message
        ?? fallback
    },
    /**
     * Lapozási állapot frissítése a Laravel által visszaadott lapozási objektum alapján.
     *
     * @param {object} target A módosítandó lapozási állapot.
     * @param {object} paginator A Laravel által visszaadott lapozási objektum.
     * @returns {void}
     */
    applyPagination(target, paginator) {
      target.currentPage = paginator.current_page ?? 1
      target.lastPage = paginator.last_page ?? 1
      target.total = paginator.total ?? 0
    },
    /**
     * Kiválasztja és betölti az adminfelület egyik lapját.
     *
     * @param {string} tab A kiválasztott lap azonosítója.
     * @returns {Promise<void>}
     */
    async selectTab(tab) {
      this.activeTab = tab
      this.clearMessages()

      if (tab === 'overview') {
        await this.loadStatistics()
      } else if (tab === 'users') {
        await this.loadUsers(1)
      } else if (tab === 'crosswords') {
        await this.loadCrosswords(1)
      } else if (tab === 'clues') {
        await this.loadClues(1)
      }
    },
    /**
     * Betölti az admin vezérlőpult összesített statisztikáit.
     *
     * @returns {Promise<void>}
     */
    async loadStatistics() {
      this.loading = true
      this.errorMessage = null

      try {
        this.statistics = await loadAdminDashboard()
      } catch (error) {
        this.errorMessage = this.errorText(
          error,
          'A statisztikák betöltése sikertelen.',
        )
      } finally {
        this.loading = false
      }
    },
    /**
     * Háttérben frissíti a statisztikákat a jelenlegi nézet megszakítása nélkül.
     *
     * @returns {Promise<void>}
     */
    async refreshStatisticsSilently() {
      try {
        this.statistics = await loadAdminDashboard()
      } catch {
        // A táblaművelet sikerét ne írja felül egy háttérben frissülő
        // statisztikai kérés hibája.
      }
    },
    /**
     * Betölti a felhasználók megadott oldalát az aktuális keresési feltétellel.
     *
     * @param {number} page A betöltendő oldalszám.
     * @returns {Promise<void>}
     */
    async loadUsers(page = 1) {
      this.loading = true
      this.errorMessage = null

      try {
        const result = await listAdminUsers({
          page,
          search: this.search.users || undefined,
        })

        this.users = result.data ?? []
        this.applyPagination(this.userPagination, result)
      } catch (error) {
        this.users = []
        this.errorMessage = this.errorText(
          error,
          'A felhasználók betöltése sikertelen.',
        )
      } finally {
        this.loading = false
      }
    },
    /**
     * Betölti a rejtvények megadott oldalát az aktuális keresési feltétellel.
     *
     * @param {number} page A betöltendő oldalszám.
     * @returns {Promise<void>}
     */
    async loadCrosswords(page = 1) {
      this.loading = true
      this.errorMessage = null

      try {
        const result = await listAdminCrosswords({
          page,
          search: this.search.crosswords || undefined,
        })

        this.crosswords = result.data ?? []
        this.applyPagination(this.crosswordPagination, result)
      } catch (error) {
        this.crosswords = []
        this.errorMessage = this.errorText(
          error,
          'A rejtvények betöltése sikertelen.',
        )
      } finally {
        this.loading = false
      }
    },
    /**
     * Betölti a szavak megadott oldalát az aktuális keresési feltétellel.
     *
     * @param {number} page A betöltendő oldalszám.
     * @returns {Promise<void>}
     */
    async loadClues(page = 1) {
      this.loading = true
      this.errorMessage = null

      try {
        const result = await listAdminClues({
          page,
          search: this.search.clues || undefined,
        })

        this.clues = result.data ?? []
        this.applyPagination(this.cluePagination, result)
      } catch (error) {
        this.clues = []
        this.errorMessage = this.errorText(
          error,
          'A szavak betöltése sikertelen.',
        )
      } finally {
        this.loading = false
      }
    },
    /**
     * Megerősítés után aktiválja vagy letiltja a kiválasztott felhasználót.
     *
     * @param {object} user A módosítandó felhasználó.
     * @returns {Promise<void>}
     */
    async toggleUserStatus(user) {
      const action = user.is_active ? 'letiltani' : 'aktiválni'

      if (!window.confirm(`Biztosan szeretnéd ${action}: ${user.username}?`)) {
        return
      }

      this.clearMessages()

      try {
        const result = await setAdminUserStatus(user.id, !user.is_active)
        user.is_active = result.user.is_active
        this.successMessage = result.message
        await this.refreshStatisticsSilently()
      } catch (error) {
        this.errorMessage = this.errorText(
          error,
          'A felhasználó állapotának módosítása sikertelen.',
        )
      }
    },
    /**
     * Megerősítés után adminisztrátorként törli a kiválasztott rejtvényt.
     *
     * @param {object} crossword A törlendő rejtvény.
     * @returns {Promise<void>}
     */
    async removeCrossword(crossword) {
      if (!window.confirm(
        `Biztosan törölni szeretnéd a(z) „${crossword.title}” rejtvényt?`,
      )) {
        return
      }

      this.clearMessages()

      try {
        const result = await deleteAdminCrossword(crossword.id)
        this.successMessage = result.message

        const page = this.crosswords.length === 1
          && this.crosswordPagination.currentPage > 1
          ? this.crosswordPagination.currentPage - 1
          : this.crosswordPagination.currentPage

        await this.loadCrosswords(page)
        await this.refreshStatisticsSilently()
      } catch (error) {
        this.errorMessage = this.errorText(
          error,
          'A rejtvény törlése sikertelen.',
        )
      }
    },
    /**
     * Megerősítés után törli a más rejtvényben nem használt szót.
     *
     * @param {object} clue A törlendő szó és meghatározás.
     * @returns {Promise<void>}
     */
    async removeClue(clue) {
      if (clue.placements_count > 0) {
        return
      }

      if (!window.confirm(
        `Biztosan törölni szeretnéd ezt a szót: ${clue.solution}?`,
      )) {
        return
      }

      this.clearMessages()

      try {
        const result = await deleteAdminClue(clue.id)
        this.successMessage = result.message

        const page = this.clues.length === 1
          && this.cluePagination.currentPage > 1
          ? this.cluePagination.currentPage - 1
          : this.cluePagination.currentPage

        await this.loadClues(page)
        await this.refreshStatisticsSilently()
      } catch (error) {
        this.errorMessage = this.errorText(
          error,
          'A szó törlése sikertelen.',
        )
      }
    },
    /**
     * Magyar formátumban jeleníti meg a megadott dátumot.
     *
     * @param {string|null} date A formázandó dátum.
     * @returns {string} A formázott dátum vagy az eredeti érték.
     */
    formatDate(date) {
      if (!date) {
        return ''
      }

      const parsed = new Date(date)

      if (Number.isNaN(parsed.getTime())) {
        return date
      }

      return new Intl.DateTimeFormat('hu-HU', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      }).format(parsed)
    },
  },
}
</script>

<style lang="scss" scoped>
@import '@/styles/adminPage.scss';
</style>