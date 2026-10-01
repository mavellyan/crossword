import { createPinia, setActivePinia } from 'pinia'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/crosswordApi', () => ({
  loadCrossword: vi.fn(),
  validateEntryForGuest: vi.fn(),
}))

import { loadCrossword, validateEntryForGuest } from '@/services/crosswordApi'
import { useCrosswordStore } from '@/stores/crossword'
import { useAuthStore } from '@/stores/auth'
import { useAttemptStore } from '@/stores/attempt'

const deferred = () => {
  let resolve
  let reject
  const promise = new Promise((res, rej) => {
    resolve = res
    reject = rej
  })
  return { promise, resolve, reject }
}

const publicCrossword = () => ({
  id: 9,
  title: 'Teszt rejtvény',
  creator: 'tesztelo',
  grid: [
    ['#', '#', '#', '#', null],
    [null, null, null, null, null],
    ['#', '#', '#', '#', null],
    ['#', '#', '#', '#', null],
    ['#', '#', '#', '#', null],
  ],
  width: 5,
  height: 5,
  words: [
    {
      placement_id: 11,
      definition: 'Angol köszönés',
      direction: 'horizontal',
      start_row: 1,
      start_col: 0,
      cells: [
        { row: 1, col: 0 },
        { row: 1, col: 1 },
        { row: 1, col: 2 },
        { row: 1, col: 3 },
        { row: 1, col: 4 },
      ],
    },
    {
      placement_id: 12,
      definition: 'Világ angolul',
      direction: 'vertical',
      start_row: 0,
      start_col: 4,
      cells: [
        { row: 0, col: 4 },
        { row: 1, col: 4 },
        { row: 2, col: 4 },
        { row: 3, col: 4 },
        { row: 4, col: 4 },
      ],
    },
  ],
})

const shortCrossword = () => ({
  id: 10,
  title: 'Rövid rejtvény',
  creator: 'tesztelo',
  grid: [[null, null]],
  width: 2,
  height: 1,
  words: [{
    placement_id: 21,
    definition: 'Két betű',
    direction: 'horizontal',
    start_row: 0,
    start_col: 0,
    cells: [{ row: 0, col: 0 }, { row: 0, col: 1 }],
  }],
})

describe('crossword store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  /** Ellenőrzi a publikus rejtvény betöltését és a futásidejű térképek felépítését. */
  it('betölti a publikus rejtvényt és felépíti a cellatérképeket', async () => {
    loadCrossword.mockResolvedValue(publicCrossword())
    const store = useCrosswordStore()

    await store.loadCrossword(9)

    expect(loadCrossword).toHaveBeenCalledWith(9)
    expect(store.id).toBe(9)
    expect(store.title).toBe('Teszt rejtvény')
    expect(store.width).toBe(5)
    expect(store.height).toBe(5)
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
    expect(store.entriesById['11'].placement_id).toBe(11)
    expect(store.cellEntries['1:4']).toEqual(['11', '12'])
  })

  /** Ellenőrzi, hogy a metszéspont egyetlen közös cellaértéket használ. */
  it('közös cellaértéket használ mindkét metsző entryhez', () => {
    const store = useCrosswordStore()
    store.initializePlayState(publicCrossword())

    store.cellInputs['1:4'] = 'O'

    expect(store.getCellValue(1, 4)).toBe('O')
    expect(store.getEntryInput('11')).toBe('O')
    expect(store.getEntryInput('12')).toBe('O')
  })

  /** Ellenőrzi az input nagybetűsítését, Unicode-kezelését és egy karakterre vágását. */
  it('normalizálja a cellába írt értéket', () => {
    const store = useCrosswordStore()
    store.initializePlayState(shortCrossword())

    store.updateCell(0, 0, ' űz ')

    expect(store.cellInputs['0:0']).toBe('Ű')
  })

  /** Ellenőrzi, hogy a backendállapot csak valós cellákat alkalmaz és frissíti a státuszokat. */
  it('alkalmazza a hiteles attempt állapotot és kiszűri az ismeretlen cellákat', () => {
    const store = useCrosswordStore()
    store.initializePlayState(shortCrossword())

    store.applyAttemptState({
      cellInputs: {
        '0:0': ' á ',
        '0:1': 'b',
        '9:9': 'X',
      },
      correctEntryIds: [21],
    })

    expect(store.cellInputs).toEqual({ '0:0': 'Á', '0:1': 'B' })
    expect(store.entryStatus['21']).toEqual({
      filled: true,
      correct: true,
      pending: false,
    })
    expect(store.isCompleted).toBe(true)
  })

  /** Ellenőrzi a kitöltött, helyes és függő státuszok számítását. */
  it('helyesen frissíti az entry státuszát cellamódosításkor', () => {
    const store = useCrosswordStore()
    store.initializePlayState(shortCrossword())

    store.updateCell(0, 0, 'A')
    expect(store.entryStatus['21']).toEqual({
      filled: false,
      correct: false,
      pending: false,
    })

    store.updateCell(0, 1, 'B')
    expect(store.entryStatus['21']).toEqual({
      filled: true,
      correct: false,
      pending: true,
    })
  })

  /** Ellenőrzi, hogy egy régi vendégvalidációs válasz nem írja felül az újabb inputot. */
  it('figyelmen kívül hagyja az elavult vendégvalidációs választ', async () => {
    const firstResponse = deferred()
    const secondResponse = deferred()
    validateEntryForGuest
      .mockReturnValueOnce(firstResponse.promise)
      .mockReturnValueOnce(secondResponse.promise)

    const store = useCrosswordStore()
    store.initializePlayState(shortCrossword())
    store.cellInputs['0:0'] = 'A'

    store.updateCell(0, 1, 'B')
    await Promise.resolve()

    store.updateCell(0, 1, 'C')
    await Promise.resolve()

    firstResponse.resolve(true)
    await flushPromises()

    expect(store.cellInputs['0:1']).toBe('C')
    expect(store.entryStatus['21'].correct).toBe(false)
    expect(store.entryStatus['21'].pending).toBe(true)

    secondResponse.resolve(false)
    await flushPromises()

    expect(store.entryStatus['21']).toEqual({
      filled: true,
      correct: false,
      pending: false,
    })
  })

  /** Ellenőrzi a vendég befejezését és az újrakezdéshez használt resetet. */
  it('helyesen kezeli a vendég befejezést és resetet', async () => {
    validateEntryForGuest.mockResolvedValue(true)
    const store = useCrosswordStore()
    store.initializePlayState(shortCrossword())
    store.cellInputs = { '0:0': 'A', '0:1': 'B' }
    store.updateEntryStatuses([])

    await store.validateGuestEntry('21')

    expect(validateEntryForGuest).toHaveBeenCalledWith(10, '21', 'AB')
    expect(store.entryStatus['21'].correct).toBe(true)
    expect(store.isCompleted).toBe(true)

    store.guestReset()

    expect(store.cellInputs).toEqual({})
    expect(store.entryStatus).toEqual({})
    expect(store.activeEntryId).toBeNull()
    expect(store.activeCellKey).toBeNull()
    expect(store.isCompleted).toBe(false)
  })

  /** Ellenőrzi, hogy belépett felhasználónál a módosítás mentésre jelölődik. */
  it('belépett felhasználónál módosítottnak jelöli az attemptet', () => {
    localStorage.setItem('token', 'token')
    const authStore = useAuthStore()
    expect(authStore.isLoggedIn).toBe(true)

    const store = useCrosswordStore()
    store.initializePlayState(shortCrossword())
    store.updateCell(0, 0, 'a')

    const attemptStore = useAttemptStore()
    expect(attemptStore.modified).toBe(true)
    store.cancelScheduledSave()
  })
})
