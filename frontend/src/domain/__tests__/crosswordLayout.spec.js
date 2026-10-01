import { describe, expect, it } from 'vitest'
import {
  buildOccupancy,
  cellsForEntry,
  splitLetters,
  validateCandidate,
  validateLayout,
} from '@/domain/crosswordLayout'

const entry = ({
  clientId,
  solution,
  direction = 'horizontal',
  startRow = 0,
  startCol = 0,
}) => ({
  clientId,
  id: null,
  clueId: clientId,
  solution,
  definition: `Meghatározás ${clientId}`,
  direction,
  startRow,
  startCol,
})

const errorCodes = result => result.errors.map(error => error.code)

describe('crosswordLayout', () => {
  /** Ellenőrzi a magyar Unicode-betűk NFC-normalizálását és nagybetűsítését. */
  it('helyesen bontja fel a magyar Unicode-betűket', () => {
    expect(splitLetters('tűz')).toEqual(['T', 'Ű', 'Z'])
    expect(splitLetters('tu\u030Bz')).toEqual(['T', 'Ű', 'Z'])
  })

  /** Ellenőrzi a vízszintes és függőleges cellakoordináták előállítását. */
  it('helyes cellákat készít mindkét irányhoz', () => {
    expect(cellsForEntry(entry({
      clientId: 1,
      solution: 'TŰZ',
      startRow: 2,
      startCol: 4,
    }))).toEqual([
      { row: 2, col: 4, letter: 'T', index: 0 },
      { row: 2, col: 5, letter: 'Ű', index: 1 },
      { row: 2, col: 6, letter: 'Z', index: 2 },
    ])

    expect(cellsForEntry(entry({
      clientId: 2,
      solution: 'ÉG',
      direction: 'vertical',
      startRow: 1,
      startCol: 3,
    }))).toEqual([
      { row: 1, col: 3, letter: 'É', index: 0 },
      { row: 2, col: 3, letter: 'G', index: 1 },
    ])
  })

  /** Ellenőrzi, hogy a közös cellában mindkét entry megjelenik. */
  it('közös foglaltsági cellát készít a metszéspontban', () => {
    const horizontal = entry({
      clientId: 1,
      solution: 'HELLO',
      startRow: 1,
      startCol: 0,
    })
    const vertical = entry({
      clientId: 2,
      solution: 'WORLD',
      direction: 'vertical',
      startRow: 0,
      startCol: 4,
    })

    expect(buildOccupancy([horizontal, vertical])['1:4']).toMatchObject({
      letter: 'O',
      entries: [1, 2],
      directions: ['horizontal', 'vertical'],
    })
  })

  /** Ellenőrzi az érvényes vízszintes-függőleges metszést. */
  it('elfogadja az egyező betűvel létrejövő metszést', () => {
    const horizontal = entry({
      clientId: 1,
      solution: 'HELLO',
      startRow: 1,
      startCol: 0,
    })
    const vertical = entry({
      clientId: 2,
      solution: 'WORLD',
      direction: 'vertical',
      startRow: 0,
      startCol: 4,
    })

    expect(validateCandidate([horizontal], vertical)).toEqual({
      isValid: true,
      errors: [],
      intersectionCount: 1,
    })
  })

  /** Ellenőrzi az eltérő betűk konfliktusát. */
  it('elutasítja az eltérő betűvel létrejövő metszést', () => {
    const horizontal = entry({ clientId: 1, solution: 'HELLO', startRow: 1 })
    const vertical = entry({
      clientId: 2,
      solution: 'DORK',
      direction: 'vertical',
      startRow: 1,
      startCol: 0,
    })

    expect(errorCodes(validateCandidate([horizontal], vertical))).toContain('letter_conflict')
  })

  /** Ellenőrzi az azonos irányú átfedés tiltását. */
  it('elutasítja az azonos irányú átfedést', () => {
    const first = entry({ clientId: 1, solution: 'ALMA', startRow: 2, startCol: 1 })
    const second = entry({ clientId: 2, solution: 'LAP', startRow: 2, startCol: 2 })

    expect(errorCodes(validateCandidate([first], second))).toContain('same_direction_overlap')
  })

  /** Ellenőrzi az oldalirányú szomszédság tiltását. */
  it('elutasítja az oldalirányban szomszédos szavakat', () => {
    const first = entry({ clientId: 1, solution: 'ALMA', startRow: 2, startCol: 1 })
    const second = entry({ clientId: 2, solution: 'KÖRTE', startRow: 3, startCol: 1 })

    expect(errorCodes(validateCandidate([first], second))).toContain('side_adjacency')
  })

  /** Ellenőrzi a foglalt kezdő- és végpont felismerését. */
  it('elutasítja a blokkolt végpontot', () => {
    const blocker = entry({
      clientId: 1,
      solution: 'AB',
      direction: 'vertical',
      startRow: 1,
      startCol: 0,
    })
    const candidate = entry({
      clientId: 2,
      solution: 'HELLO',
      startRow: 1,
      startCol: 1,
    })

    expect(errorCodes(validateCandidate([blocker], candidate))).toContain('blocked_endpoint')
  })

  /** Ellenőrzi a negatív és rácson kívüli koordinátákat. */
  it('elutasítja a negatív és túlcsorduló elhelyezéseket', () => {
    const negative = entry({ clientId: 1, solution: 'ALMA', startRow: -1 })
    const overflow = entry({ clientId: 2, solution: 'ALMA', startRow: 0, startCol: 17 })
    const exactEdge = entry({ clientId: 3, solution: 'ALMA', startRow: 0, startCol: 16 })

    expect(errorCodes(validateCandidate([], negative))).toContain('negative_coordinate')
    expect(errorCodes(validateCandidate([], overflow))).toContain('out_of_bounds')
    expect(validateCandidate([], exactEdge).isValid).toBe(true)
  })

  /** Ellenőrzi a kapcsolódás nélküli új entry elutasítását. */
  it('elutasítja a meglévő elrendezéstől különálló entryt', () => {
    const first = entry({ clientId: 1, solution: 'ALMA' })
    const second = entry({ clientId: 2, solution: 'KÖRTE', startRow: 5, startCol: 5 })

    expect(errorCodes(validateCandidate([first], second))).toContain('disconnected_entry')
  })

  /** Ellenőrzi a teljes layout összefüggőségét. */
  it('elutasítja a szétkapcsolt teljes elrendezést', () => {
    const first = entry({ clientId: 1, solution: 'ALMA' })
    const second = entry({ clientId: 2, solution: 'KÖRTE', startRow: 5, startCol: 5 })

    expect(errorCodes(validateLayout([first, second]))).toContain('disconnected_layout')
  })

  /** Ellenőrzi, hogy a layout eredménye független az entry-k sorrendjétől. */
  it('minden beküldési sorrendben elfogadja ugyanazt az összefüggő layoutot', () => {
    const hello = entry({ clientId: 1, solution: 'HELLO', startRow: 1, startCol: 0 })
    const world = entry({
      clientId: 2,
      solution: 'WORLD',
      direction: 'vertical',
      startRow: 0,
      startCol: 4,
    })
    const dark = entry({ clientId: 3, solution: 'DARK', startRow: 4, startCol: 4 })

    const orders = [
      [hello, world, dark],
      [hello, dark, world],
      [world, hello, dark],
      [world, dark, hello],
      [dark, hello, world],
      [dark, world, hello],
    ]

    orders.forEach(layout => {
      const result = validateLayout(layout)
      expect(result.isValid).toBe(true)
      expect(result.intersectionCount).toBe(2)
    })
  })
})
