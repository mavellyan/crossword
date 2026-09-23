/**
 * entry struktúra példa:
 * {
 *    clientId: 1,
 *    id: null,
 *    clueId: 1,
 *    solution: 'ALMA',
 *    definition: 'Gyümölcs',
 *    direction: 'horizontal',
 *    startRow: 3,
 *    startCol: 2,
 * }
 * 
 * @typedef {Object} Entry
 */

/**
 * A validáció során előforduló hibák típusai. Megegyezik a backenden lévő hibák típusával.
 * 
 * @typedef {Object} Errors
 * @property {string} LETTER_CONFLICT - A szó betűi ütköznek egy már elhelyezett szó betűivel.
 * @property {string} NEGATIVE_COORDINATE - A szó kezdő koordinátái negatívak.
 * @property {string} ANSWER_TOO_LONG - A szó túl hosszú a maximális hosszúsághoz képest.
 * @property {string} ANSWER_TOO_SHORT - A szó túl rövid a minimális hosszúsághoz képest.
 * @property {string} OUT_OF_BOUNDS - A szó kiterjedése meghaladja a rejtvény határait.
 * @property {string} SAME_DIRECTION_OVERLAP - A szó átfed egy már elhelyezett szóval ugyanabban az irányban.
 * @property {string} BLOCKED_ENDPOINT - A szó kezdő vagy végpontja blokkolva van egy már elhelyezett szó által.
 * @property {string} SIDE_ADJACENCY - A szó szomszédos egy már elhelyezett szóhoz oldalirányban.
 * @property {string} DISCONNECTED_ENTRY - A szó nem kapcsolódik egyetlen már elhelyezett szóhoz sem.
 * @property {string} TOO_FEW_ENTRIES - A rejtvényben túl kevés szó (<2) van elhelyezve.
 * @property {string} DISCONNECTED_LAYOUT - A rejtvényben elhelyezett szavak nem kapcsolódnak egymáshoz.
 */
const Errors = {
  LETTER_CONFLICT: 'letter_conflict',
  NEGATIVE_COORDINATE: 'negative_coordinate',
  ANSWER_TOO_LONG: 'answer_too_long',
  ANSWER_TOO_SHORT: 'answer_too_short',
  OUT_OF_BOUNDS: 'out_of_bounds',
  SAME_DIRECTION_OVERLAP: 'same_direction_overlap',
  BLOCKED_ENDPOINT: 'blocked_endpoint',
  SIDE_ADJACENCY: 'side_adjacency',
  DISCONNECTED_ENTRY: 'disconnected_entry',
  TOO_FEW_ENTRIES: 'too_few_entries',
  DISCONNECTED_LAYOUT: 'disconnected_layout',
}

/**
 * Felbontja a szót betűkre, minden betű külön elemként kerül a tömbbe.
 * 
 * @param {string} value - A szó amit el akarunk helyezni a rejtvényben
 * @returns {array} - A szó betűit tartalmazó tömb, minden betű külön elemként
 */
export function splitLetters(value) {
  return Array.from(String(value).normalize('NFC').toLocaleUpperCase('hu-HU'))
}

/**
 * Felbontja az entry-t cellákra, minden betű külön cellát jelent a rejtvényben.
 * Minden cella tartalmazza a sorát és oszlopát, a betűt és az indexét az entry-ben.
 * 
 * @param {Entry} entry - Az entry amit fel akarunk bontani cellákra
 * @returns {array} - A cellákat tartalmazó tömb, minden cella külön elemként
 */
export function cellsForEntry(entry) {
  return splitLetters(entry.solution).map((letter, index) => ({
    row: entry.startRow + (entry.direction === 'vertical' ? index : 0),
    col: entry.startCol + (entry.direction === 'horizontal' ? index : 0),
    letter,
    index,
  }))
}

/**
 * Létrehoz egy foglaltsági táblázatot az adott entry-k alapján.
 * A táblázat minden celláját tartalmazza, amely tartalmazza a sorát, oszlopát, a betűt és az entry-ket.
 * Emellett a cellákhoz tartozó irányokat is tartalmazza, és hogy melyik entry-k tartoznak hozzájuk.
 * Ez alapján lehet ellenőrizni, hogy van-e átfedés a cellák között, és hogy az entry-k helyesen vannak-e elhelyezve a rejtvényben.
 * 
 * @param {array<Entry>} entries 
 * @returns {Object} - A foglaltsági táblázat, ahol a kulcs a cella sor és oszlop koordinátája, az érték pedig a cella adatai
 */
export function buildOccupancy(entries) {
  const occupancy = {}

  entries.forEach(entry => {
    cellsForEntry(entry).forEach(cell => {
      const key = `${cell.row}:${cell.col}`

      if (!(key in occupancy)) {
        occupancy[key] = {
          row: cell.row,
          col: cell.col,
          letter: cell.letter,
          entries: [],
          directions: [],
        }
      }

      occupancy[key].entries.push(entry.clientId)
      occupancy[key].directions.push(entry.direction)
    })
  })

  return occupancy
}

/**
 * Ellenőrzi, hogy a candidateEntry hozzáadható-e a rejtvényhez az existingEntries alapján.
 * Megegyezik a backenden használt validációval, de ez csak frontenden történik, hogy a felhasználó azonnal lássa, ha egy szó nem helyezhető el a rejtvényben.
 * A backend validáció dönti el, hogy a szó ténylegesen hozzáadható-e a rejtvényhez,
 * de a frontenden történő validáció segít a felhasználónak abban, hogy ne próbáljon meg olyan szót hozzáadni, ami nem helyezhető el a rejtvényben.
 * 
 * @param {array<Entry>} existingEntries - A rejtvényhez már hozzáadott szavak
 * @param {Entry} candidateEntry - A rejtvényhez hozzáadni kívánt szó
 * @param {number} maximumRows - A rejtvény maximális sávszáma
 * @param {number} maximumCols - A rejtvény maximális oszlopszáma
 * @param {boolean} requireConnection - Megadja, hogy a szónak csatlakozni kell-e a többihez
 *                                      Teljes layout validálásnál false disconnected_layout ellenőrzés miatt, amúgy true
 * @returns {{ isValid: boolean, errors: array, intersectionCount: number }} - A validáció eredménye, amely tartalmazza a hibákat,
 *                                                                             hogy hány metszéspont van, és hogy a szó hozzáadható-e a rejtvényhez
 */
export function validateCandidate(existingEntries, candidateEntry, maximumRows = 20, maximumCols = 20, requireConnection = true) {
  let errors = []

  const answerLength = splitLetters(candidateEntry.solution).length

  if (answerLength > 20) {
    errors.push({
      code: Errors.ANSWER_TOO_LONG,
      row: candidateEntry.startRow,
      col: candidateEntry.startCol,
      message: 'A szó túl hosszú, maximum 20 betűs lehet!',
    })
  }

  if (answerLength < 2) {
    errors.push({
      code: Errors.ANSWER_TOO_SHORT,
      row: candidateEntry.startRow,
      col: candidateEntry.startCol,
      message: 'A szó túl rövid, legalább 2 betűsnek kell lennie!',
    })
  }

  if (candidateEntry.startRow < 0 || candidateEntry.startCol < 0) {
    errors.push({
      code: Errors.NEGATIVE_COORDINATE,
      row: candidateEntry.startRow,
      col: candidateEntry.startCol,
      message: 'A szó kezdő koordinátái nem lehetnek negatívak!',
    })
  }

  if (candidateEntry.startRow >= maximumRows || candidateEntry.startCol >= maximumCols) {
    errors.push({
      code: Errors.OUT_OF_BOUNDS,
      row: candidateEntry.startRow,
      col: candidateEntry.startCol,
      message: 'A szó kezdő koordinátái meghaladják a rejtvény határait!',
    })
  }

  const endRow = candidateEntry.startRow + (candidateEntry.direction === 'vertical' ? answerLength - 1 : 0)
  const endCol = candidateEntry.startCol + (candidateEntry.direction === 'horizontal' ? answerLength - 1 : 0)

  if (endRow >= maximumRows || endCol >= maximumCols) {
    errors.push({
      code: Errors.OUT_OF_BOUNDS,
      row: endRow,
      col: endCol,
      message: 'A szó kiterjedése meghaladja a rejtvény határait!',
    })
  }
  
  const beforeRow = candidateEntry.startRow - (candidateEntry.direction === 'vertical' ? 1 : 0)
  const beforeCol = candidateEntry.startCol - (candidateEntry.direction === 'horizontal' ? 1 : 0)

  const beforeKey = `${beforeRow}:${beforeCol}`

  const afterRow = candidateEntry.startRow + (candidateEntry.direction === 'vertical' ? answerLength : 0)
  const afterCol = candidateEntry.startCol + (candidateEntry.direction === 'horizontal' ? answerLength : 0)

  const afterKey = `${afterRow}:${afterCol}`

  const map = buildOccupancy(existingEntries)

  if (beforeKey in map) {
    errors.push({
      code: Errors.BLOCKED_ENDPOINT,
      row: beforeRow,
      col: beforeCol,
      message: 'A szó kezdőpontja blokkolva van egy már elhelyezett szó által!',
    })
  }

  if (afterKey in map) {
    errors.push({
      code: Errors.BLOCKED_ENDPOINT,
      row: afterRow,
      col: afterCol,
      message: 'A szó végpontja blokkolva van egy már elhelyezett szó által!',
    })
  }

  const candidateCells = cellsForEntry(candidateEntry)

  let intersectionCount = 0

  candidateCells.forEach(cell => {
    const key = `${cell.row}:${cell.col}`

    const sidePositionBefore = candidateEntry.direction === 'vertical' ? `${cell.row}:${cell.col - 1}` : `${cell.row - 1}:${cell.col}`
    const sidePositionAfter = candidateEntry.direction === 'vertical' ? `${cell.row}:${cell.col + 1}` : `${cell.row + 1}:${cell.col}`

    if (key in map) {
      if (map[key].letter !== cell.letter) {
        errors.push({
          code: Errors.LETTER_CONFLICT,
          row: cell.row,
          col: cell.col,
          message: 'A szó betűi ütköznek egy már elhelyezett szó betűivel!',
        })
      }

      if (map[key].directions.includes(candidateEntry.direction)) {
        errors.push({
          code: Errors.SAME_DIRECTION_OVERLAP,
          row: cell.row,
          col: cell.col,
          message: 'A szó átfed egy már elhelyezett szóval ugyanabban az irányban!',
        })
      }
    }

    // Ha a szó szomszédos egy már elhelyezett szóhoz oldalirányban, de nem metszik egymást, akkor az is hiba
    if ((sidePositionBefore in map || sidePositionAfter in map) && !((key in map) && map[key].letter === cell.letter)) {
      errors.push({
        code: Errors.SIDE_ADJACENCY,
        row: cell.row,
        col: cell.col,
        message: 'A szó szomszédos egy másikkal, de nem metszik egymást!',
      })
    }

    const isOccupied = key in map
    const letterMatches = isOccupied && map[key].letter === cell.letter

    const sameDirection = isOccupied && map[key].directions.includes(candidateEntry.direction)

    const isIntersection = letterMatches && !sameDirection

    if (isIntersection) {
      intersectionCount++
    }
  })

  if (existingEntries.length > 0 && intersectionCount === 0 && requireConnection) {
    errors.push({
      code: Errors.DISCONNECTED_ENTRY,
      row: candidateEntry.startRow,
      col: candidateEntry.startCol,
      message: 'A szó nem kapcsolódik egyetlen már elhelyezett szóhoz sem!',
    })
  }

  return {
    isValid: errors.length === 0,
    errors: errors,
    intersectionCount: intersectionCount,
  }
}

/**
 * Validálja a rejtvény elrendezését az adott entry-k alapján.
 * 
 * @param {array<Entry>} entries - A rejtvényhez hozzáadni kívánt szavak
 * @param {number} maximumRows - A rejtvény maximális sávszáma
 * @param {number} maximumCols - A rejtvény maximális oszlopszáma
 * @returns {{ isValid: boolean, errors: array, intersectionCount: number }} - A validáció eredménye, amely tartalmazza a hibákat,
 *                                                                             hogy hány metszéspont van, és hogy az elrendezés érvényes-e
 */
export function  validateLayout(entries, maximumRows = 20, maximumCols = 20) {
  let errors = []

  if (entries.length < 2) {
    return {
      isValid: false,
      errors: [{
        code: Errors.TOO_FEW_ENTRIES,
        message: 'A rejtvényben túl kevés szó (<2) van elhelyezve!',
      }],
      intersectionCount: 0,
    }
  }

  entries.forEach(entry => {
    const result = validateCandidate(entries.filter(e => e.clientId !== entry.clientId), entry, maximumRows, maximumCols, false)

    if (!result.isValid) {
      errors = errors.concat(result.errors)
    }
  })

  let adjacencyMap = []

  // Létrehozunk egy szomszédsági mátrixot, amely megmutatja, hogy mely entry-k kapcsolódnak egymáshoz
  entries.forEach((entry, index) => {
    adjacencyMap[index] = []
  })

  let intersectionCount = 0

  // Végigmegyünk az összes elhelyezés páron, úgy, hogy minden pár csak egyszer legyen ellenőrizve, 
  // azaz, ha a jobbIndex kisebb vagy egyenlő a balIndex-szel, akkor kihagyjuk, pl. indexek: (0,1), (0,2), (0,3), (1,2), (1,3), (2,3) stb.,
  // így nem lesz (0,1) és (1,0) pár (ugyanaz csak más sorrendben), illetve (1,1) pár sem (önmaga).
  // Ha két elhelyezés metszi egymást, akkor hozzáadjuk az adott index szomszédsági tömbjéhez a másik indexet.
  for (let leftIndex = 0; leftIndex < entries.length; leftIndex++) {
    for (let rightIndex = leftIndex + 1; rightIndex < entries.length; rightIndex++) {
      if (rightIndex <= leftIndex) {
        continue
      }

      if (placementsIntersect(entries[leftIndex], entries[rightIndex])) {
        adjacencyMap[leftIndex].push(rightIndex)
        adjacencyMap[rightIndex].push(leftIndex)
        intersectionCount++
      }
    }
  }

  let visited = new Set() // A látogatott elhelyezések indexeit tartalmazó halmaz
  let stack = [0]

  // Ezután egy mélységi keresés algoritmust használunk, hogy ellenőrizzük, hogy az összes elhelyezés összekapcsolódik-e.
  while (stack.length > 0) {
    // Kivesszük a legutolsó indexet a veremből
    const currentIndex = stack.pop()

    // Megnézzük, hogy az index már látogatott-e, ha igen, akkor kihagyjuk
    if (visited.has(currentIndex)) {
      continue
    }

    // Ha még nem látogatott, akkor jelöljük meg látogatottnak
    visited.add(currentIndex)

    // Hozzáadjuk a szomszédos indexeket a veremhez, hogy később ellenőrizzük őket
    adjacencyMap[currentIndex].forEach(neighborIndex => {
      if (!visited.has(neighborIndex)) {
        stack.push(neighborIndex)
      }
    })
  }

  // Ha a látogatott elhelyezések száma nem egyezik meg az összes elhelyezés számával, akkor az elrendezés nem jó, nincs rendesen összekapcsolva
  if (visited.size !== entries.length) {
    errors.push({
      code: Errors.DISCONNECTED_LAYOUT,
      message: 'A rejtvényben elhelyezett szavak nem kapcsolódnak egymáshoz!',
    })
  }

  // Eltávolítjuk a duplikált hibákat, hogy ne legyenek többször ugyanazok a hibák a validációs eredményben
  errors = errors.filter((error, index, self) =>
    index === self.findIndex((e) =>
      e.code === error.code &&
      e.row === error.row &&
      e.col === error.col
    )
  )

  return {
    isValid: errors.length === 0,
    errors: errors,
    intersectionCount: intersectionCount,
  }
}

/**
 * Ellenőrzi, hogy két elhelyezés metszik-e egymást. Két elhelyezés akkor metszi egymást,
 * ha van legalább egy cellájuk, amely ugyanazon a soron és oszlopon van, ugyanaz a betűjük, és az irányuk különböző (azaz az egyik vízszintes, a másik függőleges).
 * 
 * @param {Entry} entryA - Az első elhelyezés.
 * @param {Entry} entryB - A második elhelyezés.
 * @return {bool} - Igaz, ha a két elhelyezés metszi egymást, hamis egyébként.
 */
function placementsIntersect(entryA, entryB) {
  if (entryA.direction === entryB.direction) {
    return false
  }

  const aCells = cellsForEntry(entryA)
  const bCells = cellsForEntry(entryB)

  return aCells.some(aCell => 
    bCells.some(bCell =>
      aCell.row === bCell.row &&
      aCell.col === bCell.col &&
      aCell.letter === bCell.letter
    )
  )
}