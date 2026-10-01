import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { useCrosswordEditorStore } from '@/stores/crosswordEditor'

const clue = (id, solution, definition = `Meghatározás ${id}`) => ({
  id,
  solution,
  definition,
})

const loadValidLayout = store => {
  store.loadEntries([
    {
      id: 101,
      clue_id: 1,
      solution: 'HELLO',
      definition: 'Angol köszönés',
      direction: 'horizontal',
      start_row: 1,
      start_col: 0,
    },
    {
      id: 102,
      clue_id: 2,
      solution: 'WORLD',
      definition: 'Világ angolul',
      direction: 'vertical',
      start_row: 0,
      start_col: 4,
    },
  ])
}

describe('crosswordEditor store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  /** Ellenőrzi az első szó elhelyezését és a kiválasztás törlését. */
  it('elhelyezi az első kiválasztott szót', () => {
    const store = useCrosswordEditorStore()
    store.selectClue(clue(1, 'ALMA', 'Gyümölcs'))
    store.selectCell(2, 3)
    store.setDirection('horizontal')

    expect(store.canPlaceCandidate).toBe(true)
    expect(store.placeCandidate()).toBe(true)

    expect(store.entries).toHaveLength(1)
    expect(store.entries[0]).toMatchObject({
      clientId: 1,
      clueId: 1,
      solution: 'ALMA',
      direction: 'horizontal',
      startRow: 2,
      startCol: 3,
    })
    expect(store.selectedCell).toBeNull()
    expect(store.selectedClue).toBeNull()
    expect(store.placementErrors).toEqual([])
  })

  /** Ellenőrzi egy második, érvényesen metsző szó elhelyezését. */
  it('elhelyezi az érvényesen metsző második szót', () => {
    const store = useCrosswordEditorStore()

    store.loadEntries([
      {
        id: 101,
        clue_id: 1,
        solution: 'HELLO',
        definition: 'Angol köszönés',
        direction: 'horizontal',
        start_row: 1,
        start_col: 0,
      },
    ])

    store.selectClue({
      id: 2,
      solution: 'WORLD',
      definition: 'Világ angolul',
    })
    store.selectCell(0, 4)
    store.setDirection('vertical')

    expect(store.canPlaceCandidate).toBe(true)
    expect(store.placeCandidate()).toBe(true)

    expect(store.entries).toHaveLength(2)
    expect(store.entries[1]).toMatchObject({
      clueId: 2,
      solution: 'WORLD',
      direction: 'vertical',
      startRow: 0,
      startCol: 4,
    })

    expect(store.layoutValidation.isValid).toBe(true)
    expect(store.layoutValidation.intersectionCount).toBe(1)
    expect(store.occupancy['1:4'].entries).toEqual([1, 2])
    expect(store.occupancy['1:4'].letter).toBe('O')
  })

  /** Ellenőrzi, hogy hibás jelölt nem kerül az entry-listába. */
  it('nem helyezi el a betűkonfliktust okozó jelöltet', () => {
    const store = useCrosswordEditorStore()
    store.loadEntries([{
      id: 101,
      clue_id: 1,
      solution: 'HELLO',
      definition: 'Angol köszönés',
      direction: 'horizontal',
      start_row: 1,
      start_col: 0,
    }])
    store.selectClue(clue(2, 'DORK'))
    store.selectCell(1, 0)
    store.setDirection('vertical')

    expect(store.placeCandidate()).toBe(false)
    expect(store.entries).toHaveLength(1)
    expect(store.placementErrors.map(error => error.code)).toContain('letter_conflict')
  })

  /** Ellenőrzi a kiválasztott irány hatását a candidate celláira. */
  it('irányváltáskor újraszámolja a candidate celláit', () => {
    const store = useCrosswordEditorStore()
    store.selectClue(clue(1, 'TŰZ'))
    store.selectCell(2, 4)

    store.setDirection('horizontal')
    expect(store.candidateCells.map(cell => [cell.row, cell.col])).toEqual([
      [2, 4], [2, 5], [2, 6],
    ])

    store.setDirection('vertical')
    expect(store.candidateCells.map(cell => [cell.row, cell.col])).toEqual([
      [2, 4], [3, 4], [4, 4],
    ])
  })

  /** Ellenőrzi az entry törlését és a kapcsolódó hibák tisztítását. */
  it('eltávolítja az entryt és törli az aktív kijelölést', () => {
    const store = useCrosswordEditorStore()
    loadValidLayout(store)
    store.selectEntry(1)
    store.serverErrors = [{ code: 'server_error' }]
    store.placementErrors = [{ code: 'letter_conflict' }]

    store.removeEntry(1)

    expect(store.entries.map(entry => entry.clientId)).toEqual([2])
    expect(store.activeEntryId).toBeNull()
    expect(store.serverErrors).toEqual([])
    expect(store.placementErrors).toEqual([])
  })

  /** Ellenőrzi a backendformátumú entry payload előállítását. */
  it('helyes API payloadot készít az elhelyezésekből', () => {
    const store = useCrosswordEditorStore()
    loadValidLayout(store)

    expect(store.toApiEntries()).toEqual([
      {
        clue_id: 1,
        direction: 'horizontal',
        start_row: 1,
        start_col: 0,
      },
      {
        clue_id: 2,
        direction: 'vertical',
        start_row: 0,
        start_col: 4,
      },
    ])
  })

  /** Ellenőrzi a teljes szerkesztőállapot visszaállítását. */
  it('resetEditor minden szerkesztőállapotot alaphelyzetbe állít', () => {
    const store = useCrosswordEditorStore()
    loadValidLayout(store)
    store.selectCell(4, 5)
    store.selectClue(clue(3, 'DARK'))
    store.setDirection('vertical')
    store.selectEntry(1)
    store.serverErrors = [{ code: 'server_error' }]

    store.resetEditor()

    expect(store.entries).toEqual([])
    expect(store.nextClientId).toBe(1)
    expect(store.selectedCell).toBeNull()
    expect(store.selectedClue).toBeNull()
    expect(store.selectedDirection).toBe('horizontal')
    expect(store.activeEntryId).toBeNull()
    expect(store.serverErrors).toEqual([])
  })
})
