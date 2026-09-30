<template>
  <div 
    :class="{'blur-background': isClueCreatorModalOpen || isPopupVisible}"
  >
    <div class="text-center mb-5">
      <h2 class="fw-bold text-dark mb-3">
        {{ isEditMode ? 'Rejtvény megtekintése / szerkesztése' : 'Hozz létre saját rejtvényt!' }}
      </h2>

      <div
        v-if="isReadOnly"
        class="alert alert-warning d-inline-block px-4 py-2 shadow-sm rounded-pill"
        role="alert" 
      >
        <i class="bi bi-lock-fill me-2"></i>
        Ez a rejtvény <strong>{{ isPublic ? 'nyilvános' : 'már rendelkezik megkezdett próbálkozással' }}</strong>, így nem szerkeszthető.
      </div>
    </div>

    <div class="mx-auto input-max-width">
      <div class="mb-4">
        <label class="form-label fw-bold text-muted small text-uppercase">Rejtvény címe</label>
        <input
          v-model="title"
          class="form-control form-control-lg bg-light"
          minlength="5"
          maxlength="255"
          placeholder="pl.: A világ legnehezebb rejtvénye"
          :disabled="isReadOnly"
        />
        <div v-if="hasTitle && !isTitleValid" class="text-danger small mt-2">
          <i class="bi bi-exclamation-circle me-1"></i>
          A címnek legalább 5 és legfeljebb 255 karakterből kell állnia.
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-bold text-muted small text-uppercase">Rejtvény témája (opcionális)</label>
        <v-select
          v-model="selectedTopics"
          name="topic-v-select"
          class="bg-light border border-secondary rounded"
          label="name"
          :placeholder="'Válassz egy vagy több témát...'"
          :options="topics"
          :disabled="isReadOnly"
          multiple
        ></v-select>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-sm-6">
          <label class="form-label fw-bold text-muted small text-uppercase">Nehézség</label>
          <select 
            v-model="difficulty" 
            class="form-select form-select-lg bg-light" 
            :disabled="isReadOnly"
          >
            <option value="easy">Könnyű</option>
            <option value="medium">Közepes</option>
            <option value="hard">Nehéz</option>
          </select>
        </div>

        <div class="col-sm-6">
          <label class="form-label fw-bold text-muted small text-uppercase">Láthatóság</label>
          <div 
            class="form-control form-control-lg bg-light d-flex align-items-center justify-content-between"
            :class="{ 'cursor-pointer': !isReadOnly, 'opacity-75': isReadOnly }"
            v-tooltip.hover.right="'A rejtvény láthatóságát a profil oldaladon tudod beállítani. Ez csak egy tájékoztató jellegű mező, a rejtvény létrehozásakor a láthatóság automatikusan privát lesz.'"
          >
            <span class="fw-bold" :class="setPublic ? 'text-primary' : 'text-secondary'">
              {{ setPublic ? 'Nyilvános' : 'Privát' }}
            </span>
            <div class="form-check form-switch mb-0 ps-0">
              <input 
                class="form-check-input custom-switch m-0 float-end" 
                type="checkbox" 
                role="switch" 
                id="visibilityToggle"
                v-model="setPublic"
                :disabled="true"
                style="cursor: inherit;"
              >
            </div>
          </div>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-bold text-muted small text-uppercase">Szerkesztő mód</label>
        <div 
          class="form-control form-control-lg bg-light d-flex align-items-center justify-content-between pointer"
          :class="{ 'cursor-pointer': !isReadOnly, 'opacity-75': isReadOnly }"
          @click="toggleEditorMode"
        >
          <span class="fw-bold" :class="freeFormMode ? 'text-primary' : 'text-secondary'">
            {{ freeFormMode ? 'Szabadkézi' : 'Egyszerűsített' }}
          </span>
          <div class="form-check form-switch mb-0 ps-0">
            <input 
              class="form-check-input custom-switch m-0 float-end" 
              type="checkbox" 
              role="switch" 
              id="freeFormModeToggle"
              v-model="freeFormMode"
              :disabled="isReadOnly || isEditMode"
              @click.stop
              style="cursor: inherit;"
            >
          </div>
        </div>
      </div>
    </div>

    <CrosswordGridEditor
      v-if="freeFormMode"
      :words="availableWords"
      :words-loading="wordsLoading"
      :words-error="wordsError"
      :is-read-only="isReadOnly"
      @add-clue="showClueCreatorModal"
    />

    <div v-else>
      <div class="mx-auto input-max-width">
        <div class="mb-4">
          <label class="form-label fw-bold text-muted small text-uppercase">Mi legyen a rejtvényed főmegoldása?</label>
          <input
            v-model="mainSolution"
            class="form-control form-control-lg bg-light text-uppercase"
            maxlength="20"
            placeholder="pl.: piros"
            :disabled="isReadOnly"
            @input="mainSolution = mainSolution.toUpperCase()"
          />
          <div v-if="hasMainSolution && !isMainSolutionValid" class="text-danger small mt-2">
            <i class="bi bi-exclamation-circle me-1"></i>
            A főmegoldás csak a magyar ábécé betűit tartalmazhatja, számok, szóköz és egyéb speciális karakterek nélkül.
          </div>
        </div>
      </div>

      <div v-if="isCrosswordVisible" class="mt-5 d-flex align-items-start">
        <div class="d-flex flex-column border border-secondary p-3 rounded w-100 mx-5 box-background align-items-center">
          <h4 class="text-center mb-4">Így fog kinézni a rejtvényed:</h4>
          <div v-for="(row, rowIndex) in previewRows"
            :key="rowIndex"
            class="d-flex justify-content-center"
          >
            <div v-for="(cell, cellIndex) in row"
              :key="cellIndex"
              class="preview-cell"
              :class="{
                'preview-cell-main': cell.type === 'main',
                'preview-cell-black': cell.type === 'black',
                'preview-cell-normal': cell.type === 'normal',
              }"
            >
              {{ cell.letter }}
            </div>
          </div>
        </div>

        <div class="d-flex flex-column border border-secondary p-3 rounded w-100 mx-5 box-background">
          <h4 class="text-center mb-4">Válaszd ki hozzá a szavakat:</h4>
          <p v-if="wordsLoading" class="text-muted text-center">
            Szavak betöltése...
          </p>

          <p v-else-if="wordsError" class="alert alert-danger text-center">
            {{ wordsError }}
          </p>

          <div v-else v-for="(char, charIndex) in mainSolutionChars" :key="charIndex" class="d-flex">
            <div class="d-flex mb-2 align-items-center w-100">
              <v-select
                v-model="selectedWords[charIndex]"
                name="clue-v-select"
                class="w-auto border border-primary rounded clue-select"
                label="solution"
                :placeholder="'Válassz egy szót'"
                :options="getWordsForCurrentLetter(char, charIndex)"
                :disabled="isReadOnly || !isMainSolutionValid || wordsLoading || !!wordsError"
              >

                <template #no-options="{ search, searching }">
                  <template v-if="searching">
                    Sajnos a(z) "<strong>{{ search }}</strong>" keresésre nincs találat. <br>
                    Add hozzá a <strong>+</strong> jelre kattintva!
                  </template>
                  <template v-else>
                    A lista jelenleg üres. <br> 
                    Adj hozzá új szavakat a <strong>+</strong> jelre kattintva!
                  </template>
                </template>

              </v-select>
              <span
                v-if="!wordExistsForLetter(char)"
                class="text-muted mx-2"
              >
                Nem található szó ilyen betűvel.
              </span>
              <span
                v-else
                class="text-muted mx-2"
              >
                {{ getDefinitionForSelectedWord(selectedWords[charIndex]) }}
              </span>
            </div>

            <div
              v-if="!isReadOnly && selectedWords[charIndex] == null"
              class="d-flex align-items-center justify-content-center mb-2 pointer"
              v-tooltip.hover="'Új szó hozzáadása a listához'"
            >
              <font-awesome-icon
                icon="fa-solid fa-plus"
                class="border border-primary rounded p-1 pointer"
                @click="showClueCreatorModal()"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="createError" class="mx-auto mb-4 input-max-width">
      <div class="alert alert-danger d-flex align-items-center py-2" role="alert">
        <font-awesome-icon icon="fa-solid fa-triangle-exclamation" class="me-2" />
        <div>{{ createError }}</div>
      </div>
    </div>

    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3 mt-4 mb-5 mx-auto input-max-width">
      <button
        type="button"
        class="btn btn-primary btn-lg px-4 flex-grow-1 fw-bold shadow-sm"
        :disabled="isReadOnly || !canCreateCrossword || wordsLoading || !!wordsError || creating"
        @click="submitCrossword"
      >

        <template v-if="creating">
          <font-awesome-icon icon="fa-solid fa-spinner" class="fa-spin me-2" /> Mentés folyamatban...
        </template>
        <template v-else>
          <font-awesome-icon :icon="isEditMode ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-check'" class="me-2" />
          {{ isEditMode ? 'Módosítások mentése' : 'Rejtvény létrehozása' }}
        </template>

      </button>

      <button
        v-if="isEditMode"
        type="button"
        class="btn btn-outline-danger btn-lg px-4 shadow-sm"
        @click="isPopupVisible = true"
        :disabled="isReadOnly"
      >
        <font-awesome-icon icon="fa-solid fa-trash-can" class="me-1" /> Törlés
      </button>
    </div>

    <div
      v-if="isEditMode && !isLoadingEditor && isPopupVisible"
      class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-flex justify-content-center align-items-center z-3 p-3"
      @click.self="cancelDelete"
    >
      <div class="card shadow-lg p-4 p-md-5 text-center" style="max-width: 520px; width: 100%;">
        <h3 class="fw-bold mb-3">Biztosan törölni szeretnéd a rejtvényed?</h3>
    
        <p class="text-muted mb-3">
          Vigyázz, ha törlöd a rejtvényt, az <strong>véglegesen</strong> eltűnik a rendszerből, és <strong>nem lehet visszaállítani</strong>.
        </p>

        <div class="d-grid gap-2 col-11 mx-auto mt-2">
          <button 
            type="button" 
            class="btn btn-danger fw-bold text-uppercase shadow-sm"
            :disabled="isDeleting"
            @click="confirmDelete"
          >
            <span v-if="isDeleting" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            Törlés
          </button>
        
          <button 
            type="button" 
            class="btn btn-outline-secondary fw-bold text-uppercase shadow-sm" 
            :disabled="isDeleting"
            @click="cancelDelete"
          >
            Mégsem
          </button>
        </div>
      </div>
    </div>
  </div>
  <ClueCreatorModal
    ref="clueCreatorModal"
    :topic-options="topics"
    :selected-topics="selectedTopics"
    @closed="hideClueCreatorModal"
    @clue-created="handleClueCreated"
  />
</template>

<script>
import { createCrossword, listCreatorWords, getWordsForLetterFromList, getCrosswordForEdit, updateCrossword, deleteCrossword } from '../services/crosswordCreatorApi'
import { listTopics } from '../services/crosswordApi'
import ClueCreatorModal from '../components/ClueCreatorModal.vue'
import CrosswordGridEditor from '../components/crossword-editor/CrosswordGridEditor.vue'
import { useCrosswordEditorStore } from '../stores/crosswordEditor.js'

export default {
  name: 'CrosswordCreator',
  components: {
    ClueCreatorModal,
    CrosswordGridEditor,
  },
  data() {
    return {
      /**
       * A rejtvény főmegoldása
       * 
       * @type {string}
       */
      mainSolution: '',
      /**
       * A főmegoldás minden betűjéhez kiválasztott szavak listája.
       * 
       * @type {Array<{ id: number, solution: string, definition: string }>}
       */
      selectedWords: [],
      /**
       * A rejtvény címe
       * 
       * @type {string}
       */
      title: '',
      /**
       * Az elérhető szavak listája, amikből a felhasználó kiválaszthatja a főmegoldás betűihez tartozó szavakat.
       * 
       * @type {Array<{ id: number, solution: string, definition: string, length: number }>}
       */
      availableWords: [],
      /**
       * Szavak betöltése folyamatban van-e
       * 
       * @type {boolean}
       */
      wordsLoading: false,
      /**
       * Hiba történt a szavak betöltése során, ezt a hibaüzenetet jelenítjük meg a felhasználónak.
       * 
       * @type {string|null}
       */
      wordsError: null,
      /**
       * A rejtvény létrehozása folyamatban van-e
       * 
       * @type {boolean}
       */
      creating: false,
      /**
       * Hiba történt a rejtvény létrehozása során, ezt a hibaüzenetet jelenítjük meg a felhasználónak.
       * 
       * @type {string|null}
       */
      createError: null,
      /**
       * A felugró ablak nyitva van-e, ahol a felhasználó új szót adhat hozzá a listához.
       * 
       * @type {boolean}
       */
      isClueCreatorModalOpen: false,
      /**
       * A felhasználó által kiválasztott téma a rejtvényhez.
       * 
       * @type {Array<{ id: number, name: string }>}
       */
      selectedTopics: [],
      /**
       * A backendről betöltött témák listája, amikből a felhasználó választhat.
       * 
       * @type {Array<{ id: number, name: string }>}
       */
      topics: [],
      /**
       * A rejtvény publikus vagy privát.
       * 
       * @type {boolean}
       */
      setPublic: false,
      /**
       * A rejtvény nehézségi szintje, ami lehet "easy", "medium" vagy "hard".
       * 
       * @type {string}
       */
      difficulty: 'easy',
      /**
       * Ha a felhasználó szerkesztés módban van, akkor itt tároljuk a szerkesztett rejtvény ID-ját, hogy a backendnek tudjuk jelezni, melyik rejtvényt kell frissíteni.
       * 
       * @type {number|null}
       */
      editCrosswordId: null,
      /**
       * A rejtvényhez már megkezdett próbálkozások száma, ami alapján eldönthetjük, hogy a felhasználó szerkesztheti-e a rejtvényt.
       * 
       * @type {number}
       */
      attemptsCount: 0,
      /**
       * A rejtvény publikus vagy privát állapotát jelző változó, ami alapján eldönthetjük, hogy a felhasználó szerkesztheti-e a rejtvényt.
       * 
       * @type {boolean}
       */
      isPublic: false,
      /**
       * Jelzi, hogy a felhasználó szerkesztés módban van-e, azaz egy már meglévő rejtvényt szerkeszt.
       * 
       * @type {boolean}
       */
      isEditMode: false,
      /**
       * Jelzi, hogy a rejtvény szerkesztő betöltése folyamatban van-e.
       * 
       * @type {boolean}
       */
      isLoadingEditor: false,
      /**
       * A rejtvény törlése folyamatban van-e
       * 
       * @type {boolean}
       */
      isDeleting: false,
      /**
       * A rejtvény törléséről szóló felugró ablak látszódik-e
       * 
       * @type {boolean}
       */
      isPopupVisible: false,
      /**
       * Jelzi, hogy a felhasználó szabadkézi vagy egyszerűsített szerkesztő módban van-e.
       * 
       * @type {boolean}
       */
      freeFormMode: true,
      editorStore: useCrosswordEditorStore(),
    }
  },
  async mounted() {
    await this.loadCreatorWords()
    await this.loadTopics()

    const crosswordId = this.$route.query.id
    if (crosswordId) {
      await this.initEditMode(crosswordId)
    }
  },
  computed: {
    editor() {
      return this.editorStore
    },
    /**
     * Visszaadja, hogy a felhasználó szabadkézi vagy egyszerűsített szerkesztő módban van-e.
     * 
     * @return {boolean} True, ha szabadkézi módban van, false ha egyszerűsített módban van.
     */
    isFreeFormMode() {
      return this.freeFormMode
    },
    /**
     * Átállítja a felhasználót szabadkézi vagy egyszerűsített szerkesztő módba.
     * Mivel jelenleg még nem publikálhatóak a szabadkézi rejtvények, ezért ha a felhasználó szabadkézi módba vált, akkor automatikusan privát rejtvényt hoz létre.
     */
    toggleEditorMode() {
      if (this.isReadOnly || this.isEditMode) {
        return
      }

      this.freeFormMode = !this.freeFormMode

      if (this.freeFormMode) {
        this.setPublic = false
      }
    },
    /**
     * Meghatározza, hogy az oldal szerkeszthető-e, vagy csak olvasható módban van.
     * 
     * Csak olvasható, ha:
     *  - Publikus a rejtvény, VAGY
     *  - Már van hozzá megkezdett próbálkozás (attemptsCount > 0)
     */
    isReadOnly() {
      if (!this.isEditMode) {
        return false
      }

      return this.isPublic || this.attemptsCount > 0
    },
    /**
     * Ellenőrzi, hogy ki van-e töltve a főmegoldás.
     * 
     * @returns {boolean} True, ha a főmegoldás nem üres és nem csak whitespace.
     */
    hasMainSolution() {
      return this.mainSolution.trim().length > 0
    },
    /**
     * Normalizálja a főmegoldást nagybetűssé, hogy egységesen kezelhető legyen a validáció és a megjelenítés során.
     * 
     * @return {string} A normalizált főmegoldás nagybetűkkel.
     */
    normalizeMainSolution() {
      return this.mainSolution.toUpperCase()
    },
    /**
     * Ellenőrzi, hogy a főmegoldás csak érvényes karaktereket tartalmaz-e.
     * Érvényesnek számít, ha csak a magyar ábécé betűit tartalmazza, számok, szóköz és egyéb speciális karakterek nélkül.
     * 
     * @return {boolean} True, ha a főmegoldás érvényes, false egyébként.
     */
    isMainSolutionValid() {
      return /^[A-ZÁÉÍÓÖŐÚÜŰ]+$/.test(this.normalizeMainSolution)
    },
    /**
     * Ellenőrzi, hogy ki van-e töltve a cím.
     * 
     * @returns {boolean} True, ha a cím nem üres és nem csak whitespace, false egyébként.
     */
    hasTitle() {
      return this.title.trim().length > 0
    },
    /**
     * Ellenőrzi, hogy a cím érvényes-e.
     * 
     * @returns {boolean} True, ha a cím érvényes, azaz 5 és 255 karakter közötti hosszúságú, false egyébként.
     */
    isTitleValid() {
      return this.title.trim().length >= 5 && this.title.trim().length <= 255
    },
    /**
     * Ellenőrzi, hogy a rejtvény előnézetét meg lehet-e jeleníteni, amihez szükséges, hogy legyen érvényes főmegoldás és cím is.
     * 
     * @returns {boolean} True, ha a rejtvény előnézete megjeleníthető, false egyébként.
     */
    isCrosswordVisible() {
      return this.hasMainSolution && this.isMainSolutionValid && this.hasTitle && this.isTitleValid
    },
    /**
     * A főmegoldás karaktereit egy tömbbé alakítja.
     *
     * @returns {string[]} A főmegoldás karakterei tömbben.
     */
    mainSolutionChars() {
      return this.normalizeMainSolution.split('')
    },
    /**
     * Előnézetet készít a rejtvényről a főmegoldás és a kiválasztott szavak alapján.
     * Kiszámolja, hogy a főmegoldás karaktereihez tartozó szavak hogyan helyezkednek el a rejtvényben
     * Visszaad egy olyan struktúrát, ami megmutatja, hogy melyik cella milyen típusú (fő, normál vagy fekete) és milyen betűt tartalmaz.
     * 
     * @returns {Array<Array<{ letter: string, type: 'main' | 'normal' | 'black' }>>} A rejtvény előnézete cellákra bontva.
     */
    previewRows() {
      // Végigmegyünk a főmegoldás összes betűjén és ezekből alkotunk sorokat
      const rows = this.mainSolutionChars.map((mainLetter, rowIndex) => {
        // Megnézzük, hogy van-e kiválasztott szó a jelenlegi betűhöz
        const selectedWord = this.selectedWords[rowIndex]

        // Ha nincs, akkor csak a főbetűt jelenítjük meg a sor közepén, a többi cella fekete lesz
        if (!selectedWord) {
          return {
            mainLetter,
            word: null,
            mainLetterIndexInWord: 0,
            lettersBeforeMain: 0,
            lettersAfterMain: 0,
          }
        }

        // Ha van kiválasztott szó, akkor megkeressük benne a főbetű helyét (a biztonság kedvéért nagybetűsítjük a megoldást)
        const solution = selectedWord.solution.toUpperCase()
        const mainLetterIndexInWord = solution.indexOf(mainLetter)

        // Ha nincs benne a szóban a főbetű, akkor ugyanúgy jelenítjük meg, mintha nem lenne kiválasztott szó
        // de érdemes lehet jelezni a felhasználónak, hogy ez egy érvénytelen választás lenne
        // Elvileg nem lehetséges ilyet választani, de a biztonság kedvéért
        if (mainLetterIndexInWord === -1) {
          return {
            mainLetter,
            word: null,
            mainLetterIndexInWord: 0,
            lettersBeforeMain: 0,
            lettersAfterMain: 0,
          }
        }

        // Ha minden rendben van, akkor visszaadjuk a szükséges információkat a sor megjelenítéséhez
        // Tartalmazza a főbetűt, a szót, a főbetű helyét a szóban, valamint hogy hány betű van a főbetű előtt és után, hogy ennek megfelelően tudjuk elhelyezni a cellákat
        return {
            mainLetter,
            word: solution,
            mainLetterIndexInWord,
            lettersBeforeMain: mainLetterIndexInWord,
            lettersAfterMain: solution.length - mainLetterIndexInWord - 1,
          }
      })

      // Megnézzük a max betűszámot a főbetű előtt és után
      const maxLettersBeforeMain = Math.max(0, ...rows.map(r => r.lettersBeforeMain))
      const maxLettersAfterMain = Math.max(0, ...rows.map(r => r.lettersAfterMain))

      // Majd ezek alapján kiszámoljuk a teljes szélességet és a főbetű oszlopindexét (kell +1 a főbetűnek, mivel az nincs se önmaga előtt, se önmaga után)
      // A főmegoldás pedig ott helyezkedik el, ahol az előtte lévő szükséges hely véget ér
      const totalWidth = maxLettersBeforeMain + 1 + maxLettersAfterMain
      const mainColumnIndex = maxLettersBeforeMain

      // A sorokat felbontjuk cellákra
      return rows.map(r => {
        const cells = []

        for (let colIndex = 0; colIndex < totalWidth; colIndex++) {
          // Ha az adott sorban nem szerepel szó, akkor csak a főmegoldás betűjét helyezzük el a megfelelő oszlopban, a többi cella fekete lesz
          if (!r.word) {
            cells.push({
              letter: colIndex === mainColumnIndex ? r.mainLetter : '',
              type: colIndex === mainColumnIndex ? 'main' : 'black',
            })

            continue
          }

          // Ha van szó, akkor kiszámoljuk, hogy melyik oszlopban kezdődjön ahhoz, hogy a főmegoldás a megfelelő oszlopba kerüljön
          const wordStartColumn = mainColumnIndex - r.mainLetterIndexInWord
          // Aztán megkeressük, hogy a jelenlegi oszlop a szó melyik betűjéhez tartozik
          const wordLetterIndex = colIndex - wordStartColumn

          // Ha az aktuális cella a szó határain kívül esik, akkor fekete cella lesz
          // Például, ha a rács szélesebb, mint a szó, akkor a szó eleje vagy vége után fekete cellák következnek
          if (wordLetterIndex < 0 || wordLetterIndex >= r.word.length) {
            cells.push({
              letter: '',
              type: 'black'
            })

            continue
          }

          // Ha az aktuális cella a szó határain belül esik, akkor megjelenítjük a szó megfelelő betűjét
          // és beállítjuk a cella típusát attól függően, hogy az főbetű-e vagy sem
          cells.push({
            letter: r.word[wordLetterIndex],
            type: colIndex === mainColumnIndex ? 'main' : 'normal',
          })
        }

        return cells
      })
    },
    /**
     * Visszaadja a jelenleg kiválasztott szavak ID-jait, hogy meg tudjuk akadályozni, hogy ugyanazt a szót több helyen is kiválasszák a főmegoldás különböző betűihez.
     * Ez egy segédszámítás a getWordsForCurrentLetter metódushoz, ahol kiszűrjük azokat a szavakat, amik már ki vannak választva egy másik betűhöz, kivéve ha éppen az adott betűhöz vannak kiválasztva.
     * 
     * @return {number[]} A jelenleg kiválasztott szavak ID-jainak listája.
     */
    usedWordIds() {
      return this.selectedWords
        .filter(word => word !== null)
        .map(word => word.id)
    },
    /**
     * Leellenőrzi, hogy a rejtvény létrehozható-e.
     * Ehhez szükséges, hogy a rejtvény előnézete látható legyen, ne legyen már folyamatban létrehozás,
     * minden betűhöz legyen kiválasztott szó, és a cím is érvényes legyen.
     * 
     * @return {boolean}
     */
    canCreateCrossword() {
      if (this.creating || !this.isTitleValid) {
        return false
      }

      if (this.freeFormMode) {
        return this.editorStore.layoutValidation.isValid
      }

      return this.guidedLayoutIsValid
    },
    /**
     * Megvizsgálja hogy egyszerűsített szerkesztő módban a felhasználó megadott-e helyes címet és főmegoldást,
     * valamint minden betűhöz kiválasztott-e szót, és hogy a kiválasztott szavak érvényesek-e.
     * 
     * @return {boolean} True, ha a rejtvény létrehozható, false egyébként.
     */
    guidedLayoutIsValid() {
      return this.isCrosswordVisible && this.selectedWords.length === this.mainSolutionChars.length && this.selectedWords.every(word => word !== null)
    },
  },
  watch: {
    /**
     * Figyeli a főmegoldás változásait és frissíti a kiválasztott szavakat, hogy azok illeszkedjenek az új főmegoldáshoz.
     * 
     * @param newVal Az új főmegoldás
     * @param oldVal A korábbi főmegoldás
     */
    mainSolution(newVal, oldVal) {
      if (this.isReadOnly || this.isLoadingEditor) {
        return
      }

      if (!this.isMainSolutionValid) {
        // Ha a főmegoldás érvénytelen, töröljük a kiválasztott szavakat
        this.selectedWords = []
        return
      }

      const oldChars = oldVal.split('')
      const newChars = newVal.split('')

      this.selectedWords = this.matchSelectedWordsToMainSolution(oldChars, newChars, this.selectedWords)
    },
    /**
     * Ha a felhasználó új témát választott, akkor frissítjük a szavak listáját, hogy azok a kiválasztott témához illeszkedjenek.
     * A kiválasztott szavakat is töröljük, mivel azok már nem biztos, hogy érvényesek az új témához.
     */
    selectedTopics() {
      if (this.isReadOnly || this.isLoadingEditor) {
        return
      }

      this.selectedWords = []
      this.editorStore.resetEditor()
      this.loadCreatorWords()
    },
  },
  beforeRouteLeave() {
    this.clearEditor()
  },
  async beforeRouteUpdate(to, from, next) {
    try {
      if (to.query.id === from.query.id) {
        next()
        return
      }

      this.clearEditor()
      await this.loadTopics()

      if (to.query.id) {
        await this.initEditMode(to.query.id)
      } else {
        await this.loadCreatorWords()
      }

      next()
    } catch (error) {
      console.error('Hiba történt a rejtvény betöltése során:', error)
      this.createError = 'Nem sikerült betölteni a rejtvényt szerkesztéshez. Kérlek próbáld újra később.'
      next(error)
    }
  },
  methods: {
    async initEditMode(id) {
      this.isLoadingEditor = true

      try {
        const data = await getCrosswordForEdit(id)

        this.isEditMode = true
        this.editCrosswordId = data.id
        this.title = data.title
        this.difficulty = data.difficulty || 'easy'
        this.isPublic = data.is_public
        this.setPublic = data.is_public
        this.attemptsCount = data.attempts_count ?? (data.has_attempts ? 1 : 0)

        this.selectedTopics = Array.isArray(data.topics) ? data.topics : []

        const topicIds = this.selectedTopics.map(topic => topic.id)
        await this.loadCreatorWords(topicIds.length ? topicIds : null)

        if (data.main_solution) {
          this.freeFormMode = false
          this.mainSolution = String(data.main_solution).toUpperCase()
          
          await this.$nextTick() // Várunk, hogy a mainSolutionChars frissüljön a DOM-ban

          if (data.clues && Array.isArray(data.clues)) {
            this.selectedWords = data.clues.map(clue => {
              const matchingWord = this.availableWords.find(word => word.id === clue.id)
              return matchingWord || {
                id: clue.id,
                solution: String(clue.solution ?? '').toUpperCase(),
                definition: clue.definition ?? '',
                length: clue.length ?? String(clue.solution ?? '').length,
              }
            })
          }
        } else {
          this.freeFormMode = true
          this.editorStore.loadEntries(data.entries || [])
        }
      } catch (error) {
        console.error('Hiba történt a rejtvény betöltése során:', error)
        this.createError = 'Nem sikerült betölteni a rejtvényt szerkesztéshez. Kérlek próbáld újra később.'
      } finally {
        await this.$nextTick() // Várunk, hogy a DOM frissüljön, mielőtt a betöltés állapotát false-ra állítjuk
        this.isLoadingEditor = false
      }
    },
    /**
     * Betölti a backendről a rejtvénykészítőben elérhető szavakat.
     * 
     * @param {Array<number>|null} topicIds Opcionális paraméter, ami a kiválasztott témakörök ID-jeit adja meg.
     *                                      Ha vannak választott témakörök, akkor csak az ezekben szereplő szavakat töltjük be.
     *                                      Lehet null is, ekkor az összes szót betöltjük.
     * @return {Promise<void>}
     */
    async loadCreatorWords(topicIds = this.selectedTopics?.map(topic => topic.id) ?? null) {
      this.wordsLoading = true
      this.wordsError = null

      try {
        this.availableWords = await listCreatorWords(topicIds)
      } catch (error) {
        this.wordsError = error?.message ?? 'Nem sikerült betölteni a választható szavakat.'
        this.availableWords = []
      } finally {
        this.wordsLoading = false
      }
    },
    /**
     * Betölti a backendről a témákat
     */
    async loadTopics() {
      try {
        this.topics = await listTopics()
      } catch (error) {

        this.$notify({
          type: 'error',
          title: 'Hiba',
          text: 'Nem sikerült betölteni a témákat.',
        })

        this.topics = []
      }
    },
    /**
     * User által létrehozott keresztrejtvény elküldése a backendnek, hogy elmentse az adatbázisba.
     * Ha sikeres, átirányítja a felhasználót a létrehozott rejtvény oldalára.
     * Kézi elhelyezést használ, a backend az itt megadott sorrendet tartja.
     */
    async submitCrossword() {
      if (this.isReadOnly) {
        return
      }

      this.creating = true
      this.createError = null

      const payload = {
        title: this.title.trim(),
        topic_ids: this.selectedTopics.map(topic => topic.id),
        difficulty: this.difficulty,
      }

      if (this.freeFormMode) {
        payload.entries = this.editorStore.toApiEntries()
      } else {
        payload.main_solution = this.normalizeMainSolution
        payload.clue_ids = this.selectedWords.map(word => word.id)
      }

      try {
        let result

        if (this.isEditMode) {
          result = await updateCrossword(this.editCrosswordId, payload)
        } else {
          result = await createCrossword(payload)
        }

        if (this.isEditMode) {
          this.$notify({
            type: 'success',
            title: 'Sikeres mentés',
            text: 'A rejtvényed sikeresen frissítve!',
          })
        } else {
          this.$notify({
            type: 'success',
            title: 'Sikeres mentés',
            text: 'A rejtvényed sikeresen létrehozva!',
          })
        }

        this.$router.push({ name: 'crosswordcreator', query: { id: result.id } })

      } catch (error) {
        this.editorStore.serverErrors = error?.response?.data?.errors ?? null

        this.$notify({
          type: 'error',
          title: 'Hiba történt',
          text: error?.response?.data?.message
            ?? error?.message
            ?? 'Nem sikerült létrehozni a rejtvényt. Kérjük, próbáld újra később.',
        })

        this.createError = error?.response?.data?.message
          ?? error?.message
          ?? 'Nem sikerült létrehozni a rejtvényt.'
      } finally {
        this.creating = false
      }
    },
    /**
     * Visszaadja az adott betűhöz tartozó szavakat.
     * 
     * @param letter A főmegoldás adott betűje
     * @param currentIndex A főmegoldás adott betűjének indexe, hogy ki tudjuk szűrni a már kiválasztott szavakat
     * @return {Array<{ id: number, solution: string, definition: string }>} Az adott betűhöz tartozó szavak listája.
     */
    getWordsForCurrentLetter(letter, currentIndex) {
      const currentWordId = this.selectedWords[currentIndex]?.id

      return getWordsForLetterFromList(this.availableWords, letter)
        .filter(word => !this.usedWordIds.includes(word.id) || word.id === currentWordId)
    },
    /**
     * Visszaadja az összes szót, ami a rejtvénykészítőben elérhető.
     * Ez jelenleg egy mock függvény, de később kicserélhető egy API hívásra, ha a backend támogatja majd a szavak lekérését.
     * 
     * @return {Array<{ id: number, solution: string, definition: string }>} Az összes elérhető szó listája.
     */
    getAllWords() {
      return this.availableWords
    },
    /**
     * Frissíti a kiválasztott szavakat, hogy azok illeszkedjenek az új főmegoldáshoz, miközben megőrzi a lehető legtöbb érvényes kiválasztást.
     * Összehasonlítja a régi és új főmegoldás karaktereit, és ahol csak lehetséges, megtartja a korábban kiválasztott szavakat, amennyiben azok még mindig érvényesek az új karakterhez.
     * 
     * @param oldChars A korábbi főmegoldás betűi
     * @param newChars Az új főmegoldás betűi
     * @param oldSelectedWords A korábbi főmegoldáshoz kiválasztott szavak
     * @return {Array<{ id: number, solution: string, definition: string } | null>} Az új főmegoldáshoz illeszkedő kiválasztott szavak listája, ahol a nem érvényes vagy új karakterhez tartozó szavak null értékűek.
     */
    matchSelectedWordsToMainSolution(oldChars, newChars, oldSelectedWords) {
      const newSelectedWords = new Array(newChars.length).fill(null)

      // Végigmegyünk a előlről és hátulról is, hogy megtaláljuk a közös prefixet és suffixet
      // a régi és új főmegoldás között, és megőrizzük a kiválasztott szavakat ezeken a pozíciókon, amennyiben azok még mindig érvényesek.
      let start = 0
      while (start < oldChars.length && start < newChars.length && oldChars[start] === newChars[start]) {
        newSelectedWords[start] = this.keepSelectedWordIfValid(oldSelectedWords[start], newChars[start])
        start++
      }

      let oldEnd = oldChars.length - 1
      let newEnd = newChars.length - 1

      while (oldEnd >= start && newEnd >= start && oldChars[oldEnd] === newChars[newEnd]) {
        newSelectedWords[newEnd] = this.keepSelectedWordIfValid(oldSelectedWords[oldEnd], newChars[newEnd])
        oldEnd--
        newEnd--
      }

      return newSelectedWords
    },
    /**
     * Megállapítja, hogy a kiválasztott szó érvényes-e az új karakterhez.
     * Egy szó érvényesnek számít, ha nem null és a megoldása tartalmazza a karaktert.
     * 
     * @param selectedWord A vizsgált kiválasztott szó
     * @param letter A betű amihez próbáljuk egyeztetni a szót
     * @return { { id: number, solution: string, definition: string } | null } Visszaadja a kiválasztott szót, ha az érvényes az új karakterhez, vagy null értéket, ha nem érvényes.
     */
    keepSelectedWordIfValid(selectedWord, letter) {
      if (!selectedWord) {
        return null
      }

      if (!selectedWord.solution.includes(letter)) {
        return null
      }

      return selectedWord
    },
    /**
     * Megállapítja, hogy létezik-e szó a megadott betűhöz.
     * 
     * @param letter A betű amihez vizsgáljuk, hogy tartozik-e szó
     * @return {boolean} True, ha létezik szó a megadott betűhöz, false egyébként.
     */
    wordExistsForLetter(letter) {
      return getWordsForLetterFromList(this.availableWords, letter).length > 0
    },
    /**
     * Visszaadja a kiválasztott szó definícióját.
     * 
     * @param word A szó aminek a definícióját keressük
     * @return {string} A szó definíciója, vagy üres string, ha nem található.
     */
    getDefinitionForSelectedWord(word) {
      return word?.definition || ''
    },
    /**
     * Megjeleníti a szó hozzáadó ablakot.
     */
    showClueCreatorModal() {
      this.isClueCreatorModalOpen = true
      this.$refs.clueCreatorModal.showModal()
    },
    /**
     * Elrejti a szó hozzáadó ablakot.
     */
    hideClueCreatorModal() {
      this.isClueCreatorModalOpen = false
      this.$refs.clueCreatorModal.closeModal()
    },
    async handleClueCreated(clue) {
      if (this.freeFormMode) {
        this.availableWords.push(clue)
        this.editorStore.selectClue(clue)
      } else {
        await this.loadCreatorWords()
      }
    },
    /**
     * Alaphelyzetbe állítja a rejtvénykészítő összes mezőjét, hogy új rejtvényt lehessen létrehozni.
     * Akkor lehet rá szükség, ha pl. a felhasználó a profilról egy meglévő rejtvény szerkesztésére megy,
     * de innen a navigációs sávban lévő "Rejtvény készítése" gombra kattint, így új rejtvényt szeretne létrehozni.
     */
    clearEditor() {
      this.mainSolution = ''
      this.selectedWords = []
      this.title = ''
      this.availableWords = []
      this.wordsLoading = false
      this.wordsError = null
      this.creating = false
      this.createError = null
      this.isClueCreatorModalOpen = false
      this.selectedTopics = []
      this.topics = []
      this.difficulty = 'easy'
      this.editCrosswordId = null
      this.attemptsCount = 0
      this.isPublic = false
      this.isEditMode = false
      this.setPublic = false
      this.isLoadingEditor = false
      this.isDeleting = false
      this.isPopupVisible = false
      this.editorStore.resetEditor()
    },
    /**
     * Megerősíti a rejtvény törlését, és elküldi a backendnek a törlési kérést.
     * Ha sikeres, átirányítja a felhasználót a profil oldalára.
     */
    async confirmDelete() {
      if (!this.isEditMode || this.isDeleting) {
        return
      }

      this.isDeleting = true

      try {
        await deleteCrossword(this.editCrosswordId)

        this.$notify({
          type: 'success',
          title: 'Sikeres törlés',
          text: 'A rejtvényed sikeresen törölve lett.',
        })

        this.$router.push('/profile')
      } catch (error) {
        console.error('Hiba történt a rejtvény törlése során:', error)

        this.$notify({
          type: 'error',
          title: 'Hiba történt',
          text: error?.response?.data?.message
            ?? error?.message
            ?? 'Nem sikerült törölni a rejtvényt. Kérjük, próbáld újra később.',
        })
      } finally {
        this.isDeleting = false
        this.isPopupVisible = false
      }
    },
    /**
     * Mégsem gombra kattintáskor elrejti a törlés megerősítő felugró ablakot.
     */
    cancelDelete() {
      this.isPopupVisible = false
    },
  },
}
</script>

<style scoped>
.box-background {
  /* Ez a color very light blue a variablesből */
  background-color: #E7F1F6;
}

.preview-cell {
  width: 42px;
  height: 42px;
  border: 1px solid #333;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  text-transform: uppercase;
}

.preview-cell-main {
  background-color: #3B82F6; /* Color info a variablesből */
  color: white;
}

.preview-cell-normal {
  background-color: white;
  color: #111;
}

.preview-cell-black {
  background-color: #111;
  color: transparent;
}

:deep(.vs__dropdown-toggle) {
  border: none !important;
  padding: 0.375rem 0.75rem;
  background-color: #fff;
  border-radius: inherit;
}

:deep(.vs--searchable .vs__dropdown-toggle) {
  cursor: pointer;
}

:deep(.vs--open) {
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
  border-radius: 0.375rem;
}

.clue-select {
  min-width: 33%;
  margin-right: 10px;
}

.pointer {
  cursor: pointer;
}

.blur-background {
  filter: blur(4px);
  position: absolute;
  width: 100%;
  height: 100%;
}

.input-max-width {
  max-width: 600px;
}
</style>