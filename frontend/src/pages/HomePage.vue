<template>
  <div class="home-page">
    <section class="hero-section" aria-labelledby="hero-title">
      <div class="hero-content">
        <p class="hero-eyebrow">Keresztrejtvény-készítő és -fejtő</p>
        <h1 id="hero-title">Készíts, ossz meg és fejts keresztrejtvényeket!</h1>
        <p class="hero-description">
          Építs saját rejtvényt hagyományos vagy szabad elhelyezésű módban,
          fedezz fel mások által készített feladványokat, és kövesd az eredményeidet.
        </p>

        <div class="hero-actions">
          <RouterLink :to="{ name: 'crosswordlist' }" class="btn btn-light btn-lg">
            <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
            Rejtvények böngészése
          </RouterLink>
          <RouterLink :to="creationTarget" class="btn btn-outline-light btn-lg">
            <i class="bi bi-pencil-square" aria-hidden="true"></i>
            Új rejtvény készítése
          </RouterLink>
        </div>

        <p v-if="!auth.isLoggedIn" class="hero-login-note">
          A rejtvénykészítéshez bejelentkezés szükséges.
        </p>
      </div>

      <div class="hero-visual" aria-hidden="true">
        <div class="visual-glow"></div>
        <div class="crossword-preview">
          <img :src="rejtvenySrc" alt="" draggable="false" />
        </div>
      </div>
    </section>

    <section class="home-section" aria-labelledby="features-title">
      <div class="section-heading">
        <span class="section-kicker">Minden egy helyen</span>
        <h2 id="features-title">Több mint egyszerű rejtvényfejtés</h2>
        <p>Készíts saját feladványokat, mérd az idődet, és térj vissza bármikor a megkezdett rejtvényekhez.</p>
      </div>

      <div class="feature-grid">
        <article class="feature-card">
          <div class="feature-icon"><i class="bi bi-pencil-square" aria-hidden="true"></i></div>
          <h3>Készíts rejtvényt</h3>
          <p>Hagyományos és szabad elhelyezésű szerkesztés automatikus elrendezés-ellenőrzéssel.</p>
        </article>

        <article class="feature-card">
          <div class="feature-icon"><i class="bi bi-stopwatch" aria-hidden="true"></i></div>
          <h3>Fejts és versenyezz</h3>
          <p>Időzített próbálkozások, mentett haladás, vendégként is elérhető fejtés és legjobb eredmények.</p>
        </article>

        <article class="feature-card">
          <div class="feature-icon"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></div>
          <h3>Kövesd a fejlődésed</h3>
          <p>A profilodon áttekintheted a befejezett és félbehagyott próbálkozásaidat, valamint a legjobb időidet.</p>
        </article>
      </div>
    </section>

    <section class="home-section featured-section" aria-labelledby="featured-title">
      <div class="section-heading section-heading-row">
        <div>
          <span class="section-kicker">Legfrissebb feladványok</span>
          <h2 id="featured-title">Kezdj bele egy rejtvénybe!</h2>
        </div>
        <RouterLink :to="{ name: 'crosswordlist' }" class="browse-all-link">
          Összes rejtvény
          <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </RouterLink>
      </div>

      <div v-if="loading" class="crossword-card-grid" aria-label="Rejtvények betöltése">
        <div v-for="index in 3" :key="index" class="crossword-card crossword-card-skeleton" aria-hidden="true">
          <span></span><span></span><span></span>
        </div>
      </div>

      <div v-else-if="error" class="home-state home-state-error" role="alert">
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
        <div>
          <h3>A rejtvényeket most nem sikerült betölteni</h3>
          <p>{{ error }}</p>
        </div>
        <button type="button" class="btn btn-outline-danger" @click="loadFeaturedCrosswords">
          Újrapróbálás
        </button>
      </div>

      <div v-else-if="featuredCrosswords.length" class="crossword-card-grid">
        <RouterLink
          v-for="crossword in featuredCrosswords"
          :key="crossword.id"
          :to="{ name: 'crossword', params: { id: crossword.id } }"
          class="crossword-card"
        >
          <div class="crossword-card-topline">
            <span class="difficulty-badge" :class="`difficulty-${crossword.difficulty}`">
              {{ difficultyLabel(crossword.difficulty) }}
            </span>
            <time v-if="crossword.created_at">{{ formatDate(crossword.created_at) }}</time>
          </div>

          <h3>{{ crossword.title }}</h3>

          <p class="crossword-meta">
            <span><i class="bi bi-list-ol" aria-hidden="true"></i> {{ crossword.words_count }} szó</span>
            <span v-if="crossword.creator">
              <i class="bi bi-person" aria-hidden="true"></i> {{ crossword.creator.username }}
            </span>
          </p>

          <div class="topic-list">
            <span v-if="!crossword.topics?.length" class="topic-badge">Általános</span>
            <span v-for="topic in crossword.topics" :key="topic.id" class="topic-badge">
              {{ topic.name }}
            </span>
          </div>

          <span class="card-action">Fejtés indítása <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
        </RouterLink>
      </div>

      <div v-else class="home-state">
        <i class="bi bi-grid-3x3" aria-hidden="true"></i>
        <div>
          <h3>Még nincs elérhető rejtvény</h3>
          <p>Ha bejelentkezel, te készítheted el az elsőt.</p>
        </div>
      </div>
    </section>

    <section class="home-section steps-section" aria-labelledby="steps-title">
      <div class="section-heading">
        <span class="section-kicker">Egyszerűen használható</span>
        <h2 id="steps-title">Hogyan működik?</h2>
      </div>

      <ol class="steps-list">
        <li>
          <span class="step-number">1</span>
          <div><h3>Válassz rejtvényt</h3><p>Böngéssz témakör, nehézség vagy készítő alapján.</p></div>
        </li>
        <li>
          <span class="step-number">2</span>
          <div><h3>Kezdj el fejteni</h3><p>A haladásod bejelentkezve automatikusan mentésre kerül.</p></div>
        </li>
        <li>
          <span class="step-number">3</span>
          <div><h3>Javítsd az eredményed</h3><p>Fejtsd meg újra, és próbáld megdönteni a legjobb idődet.</p></div>
        </li>
      </ol>
    </section>

    <section class="final-cta" aria-labelledby="cta-title">
      <div>
        <span class="section-kicker">Készen állsz?</span>
        <h2 id="cta-title">A következő rejtvény csak rád vár.</h2>
        <p>Válassz egy feladványt, vagy készíts egy teljesen sajátot.</p>
      </div>
      <div class="final-cta-actions">
        <RouterLink :to="{ name: 'crosswordlist' }" class="btn btn-primary btn-lg">Böngészés</RouterLink>
        <RouterLink :to="creationTarget" class="btn btn-outline-primary btn-lg">Rejtvény készítése</RouterLink>
      </div>
    </section>
  </div>
</template>

<script>
import RejtvenyKep from '@/assets/rejtvenykep-no-bg-final-v2.png'
import { listCrosswords } from '@/services/crosswordApi'
import { useAuthStore } from '@/stores/auth'

export default {
  name: 'HomePage',
  data() {
    return {
      rejtvenySrc: RejtvenyKep,
      featuredCrosswords: [],
      loading: false,
      error: null,
    }
  },
  computed: {
    auth() {
      return useAuthStore()
    },
    creationTarget() {
      return this.auth.isLoggedIn
        ? { name: 'crosswordcreator' }
        : { name: 'login', query: { redirect: '/create' } }
    },
  },
  mounted() {
    this.loadFeaturedCrosswords()
  },
  methods: {
    /**
     * Betölti a főoldalon megjelenő hat legfrissebb publikus rejtvényt.
     * 
     * @returns {Promise<void>}
     */
    async loadFeaturedCrosswords() {
      this.loading = true
      this.error = null

      try {
        const crosswords = await listCrosswords({ sortOrder: 'dateDesc' })
        this.featuredCrosswords = crosswords.slice(0, 6)
      } catch (error) {
        console.error('A kiemelt rejtvények betöltése sikertelen:', error)
        this.featuredCrosswords = []
        this.error = error?.message ?? 'Ismeretlen hiba történt.'
      } finally {
        this.loading = false
      }
    },

    /**
     * Magyar feliratot ad az API nehézségi értékéhez.
     * 
     * @param {string} difficulty - Az nehézségi érték.
     * @returns {string} - A magyar felirat.
     */
    difficultyLabel(difficulty) {
      return {
        easy: 'Könnyű',
        medium: 'Közepes',
        hard: 'Nehéz',
      }[difficulty] ?? 'Ismeretlen'
    },

    /**
     * Rövid, magyar formátumban jeleníti meg a létrehozás dátumát.
     * 
     * @param {string} date - A dátum, amelyet formázni kell.
     * @returns {string} - A formázott dátum.
     */
    formatDate(date) {
      const parsedDate = new Date(date)

      if (Number.isNaN(parsedDate.getTime())) {
        return date
      }

      return new Intl.DateTimeFormat('hu-HU', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      }).format(parsedDate)
    },
  },
}
</script>

<style lang="scss" scoped>
@import '@/styles/home.scss';
</style>
