<template>
  <div
    :class="{'blur-background': isClueCreatorModalOpen}"
  >
    <h1 class="text-center pb-5">Hozz létre saját rejtvényt!</h1>
    <div class="d-flex align-items-center justify-content-center mb-3">
      <label class="h4 w-auto">Mi legyen a rejtvényed címe?</label>
      <input
        v-model="title"
        class="form-control w-25 mx-3 border border-primary border-2"
        minlength="5"
        maxlength="255"
        placeholder="pl: A világ legnehezebb rejtvénye"
      />
    </div>
    <p
      v-if="hasTitle && !isTitleValid"
      class="text-muted mb-3 alert alert-danger w-25 mx-auto mt-3 text-center pb-2 pt-2"
    >
      A címnek legalább 5 és legfeljebb 255 karakterből kell állnia.
    </p>
    <div class="d-flex align-items-center justify-content-center mb-3">
      <label class="h4 w-auto">Mi legyen a rejtvényed főmegoldása?</label>
      <input
        v-model="mainSolution"
        class="form-control w-25 mx-3 border border-primary border-2 text-uppercase"
        maxlength="20"
        placeholder="pl: piros"
        @input="mainSolution = mainSolution.toUpperCase()"
      />
    </div>
    <p
      v-if="hasMainSolution && !isMainSolutionValid"
      class="text-muted mb-3 alert alert-danger w-25 mx-auto mt-3 text-center pb-2 pt-2"
    >
      A főmegoldás csak a magyar ábécé betűit tartalmazhatja, számok, szóköz és egyéb speciális karakterek nélkül.
    </p>
    <div class="d-flex align-items-center justify-content-center mb-3">
      <label class="h4 w-auto">Mi legyen a rejtvényed témája?</label>
      <v-select
        v-model="selectedTopics"
        name="topic-v-select"
        class="w-25 mx-3 border border-primary border-2 rounded"
        label="name"
        :placeholder="'Válassz egy témát'"
        :options="topics"
        multiple
        >
      </v-select>
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
              :disabled="!isMainSolutionValid || wordsLoading || !!wordsError"
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
            v-if="selectedWords[charIndex] == null"
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
    <button
      v-if="isCrosswordVisible"
      type="button"
      class="btn btn-primary d-block mx-auto mt-4"
      :disabled="!canCreateCrossword || wordsLoading || !!wordsError"
      @click="submitCrossword"
    >
      {{  creating ? 'Létrehozás...' : 'Rejtvény létrehozása' }}
    </button>

    <p v-if="createError" class="alert alert-danger text-center w-75 mx-auto mt-3">
      {{ createError }}
    </p>
  </div>
  <ClueCreatorModal
    ref="clueCreatorModal"
    :topic-options="topics"
    :selected-topics="selectedTopics"
    @closed="hideClueCreatorModal"
    @clue-created="loadCreatorWords"
  />
</template>

<script>
import { createCrossword, listCreatorWords, getWordsForLetterFromList  } from '../services/crosswordCreatorApi'
import { listTopics } from '../services/crosswordApi'
import ClueCreatorModal from '../components/ClueCreatorModal.vue'

export default {
  name: 'CrosswordCreator',
  components: {
    ClueCreatorModal,
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
    }
  },
  async mounted() {
    await this.loadCreatorWords()
    await this.loadTopics()
  },
  computed: {
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
      return this.isCrosswordVisible && !this.creating &&
        this.selectedWords.length === this.mainSolutionChars.length &&
        this.selectedWords.every(word => word !== null) && this.isTitleValid
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
      this.selectedWords = []
      this.loadCreatorWords()
    },
  },
  methods: {
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
        console.log('creator words error:', error)
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
        console.log('load topics error:', error)

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
      this.creating = true
      this.createError = null

      try {
        const crossword = await createCrossword({
          title: this.title.trim(),
          main_solution: this.normalizeMainSolution,
          clue_ids: this.selectedWords.map(word => word.id),
          topic_ids: this.selectedTopics?.map(topic => topic.id) || [],
          difficulty: 'easy',
          is_public: true,
        })

        this.$router.push(`/crossword/${crossword.id}`)
      } catch (error) {
        console.log('create crossword error:', error)
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
</style>