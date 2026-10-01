import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/attemptApi', () => ({
  loadAttempt: vi.fn(),
  startAttempt: vi.fn(),
  stopAttempt: vi.fn(),
  abandonAttempt: vi.fn(),
  saveProgress: vi.fn(),
  saveAndStopBeacon: vi.fn(),
  listBestAttempts: vi.fn(),
}))

vi.mock('@/services/crosswordApi', () => ({
  loadCrossword: vi.fn(),
  validateEntryForGuest: vi.fn(),
}))

import {
  abandonAttempt as abandonAttemptApi,
  loadAttempt as loadAttemptApi,
  saveAndStopBeacon,
  saveProgress as saveProgressApi,
  startAttempt as startAttemptApi,
  stopAttempt as stopAttemptApi,
} from '@/services/attemptApi'
import { useAttemptStore } from '@/stores/attempt'
import { useCrosswordStore } from '@/stores/crossword'

const deferred = () => {
  let resolve
  let reject
  const promise = new Promise((res, rej) => {
    resolve = res
    reject = rej
  })
  return { promise, resolve, reject }
}

const crosswordFixture = () => ({
  id: 7,
  title: 'Mentési teszt',
  creator: 'tesztelo',
  grid: [[null, null]],
  width: 2,
  height: 1,
  words: [{
    placement_id: 31,
    definition: 'Két betű',
    direction: 'horizontal',
    start_row: 0,
    start_col: 0,
    cells: [{ row: 0, col: 0 }, { row: 0, col: 1 }],
  }],
})

const initializeStores = () => {
  localStorage.setItem('token', 'token')

  const crosswordStore = useCrosswordStore()
  crosswordStore.initializePlayState(crosswordFixture())
  crosswordStore.cellInputs = { '0:0': 'A', '0:1': 'B' }

  const attemptStore = useAttemptStore()
  attemptStore.id = 101
  attemptStore.status = 'in_progress'
  attemptStore.stateVersion = 4
  attemptStore.startedAt = '2026-01-10T12:00:00Z'
  attemptStore.modified = true

  return { attemptStore, crosswordStore }
}

describe('attempt store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  /** Ellenőrzi, hogy mentési feltétel hiányában nem történik API-hívás. */
  it('skipped eredményt ad, ha nincs mentendő módosítás', async () => {
    const { attemptStore } = initializeStores()
    attemptStore.modified = false

    await expect(attemptStore.saveProgress()).resolves.toEqual({ status: 'skipped' })
    expect(saveProgressApi).not.toHaveBeenCalled()
  })

  /** Ellenőrzi a sikeres mentést és a state version frissítését. */
  it('saved eredménnyel frissíti a verziót és a helyes entryket', async () => {
    saveProgressApi.mockResolvedValue({
      attempt: {
        status: 'in_progress',
        state_version: 5,
        correct_entry_ids: [31],
      },
    })
    const { attemptStore, crosswordStore } = initializeStores()

    await expect(attemptStore.saveProgress()).resolves.toEqual({ status: 'saved' })

    expect(saveProgressApi).toHaveBeenCalledWith(
      101,
      { '0:0': 'A', '0:1': 'B' },
      4,
    )
    expect(attemptStore.stateVersion).toBe(5)
    expect(attemptStore.correctEntryIds).toEqual([31])
    expect(attemptStore.modified).toBe(false)
    expect(attemptStore.saving).toBe(false)
    expect(crosswordStore.entryStatus['31'].correct).toBe(true)
  })

  /** Ellenőrzi, hogy a mentés közben történt újabb módosítás nem vész el. */
  it('sikeres mentés után is megőrzi a modified állapotot, ha közben változott a rács', async () => {
    const pendingSave = deferred()
    saveProgressApi.mockReturnValue(pendingSave.promise)
    const { attemptStore, crosswordStore } = initializeStores()
    const scheduleSpy = vi.spyOn(crosswordStore, 'scheduleSave').mockImplementation(() => {})

    const savePromise = attemptStore.saveProgress()
    await Promise.resolve()

    crosswordStore.cellInputs['0:1'] = 'C'
    pendingSave.resolve({
      attempt: {
        status: 'in_progress',
        state_version: 5,
        correct_entry_ids: [],
      },
    })

    await expect(savePromise).resolves.toEqual({ status: 'saved' })
    expect(attemptStore.modified).toBe(true)
    expect(scheduleSpy).toHaveBeenCalledOnce()
  })

  /** Ellenőrzi, hogy sikertelen mentés után a módosítás mentetlen marad. */
  it('failed eredménynél megőrzi a modified állapotot', async () => {
    saveProgressApi.mockRejectedValue(new Error('Hálózati hiba'))
    const { attemptStore } = initializeStores()

    await expect(attemptStore.saveProgress()).resolves.toEqual({ status: 'failed' })

    expect(attemptStore.modified).toBe(true)
    expect(attemptStore.saving).toBe(false)
    expect(attemptStore.displayError).toBe('Hálózati hiba')
  })

  /** Ellenőrzi, hogy konfliktus után a backend hiteles állapota kerül a store-okba. */
  it('conflict után betölti és alkalmazza a hiteles cellaállapotot', async () => {
    saveProgressApi.mockRejectedValue({
      response: {
        status: 409,
        data: { save_status: 'conflict' },
      },
    })
    loadAttemptApi.mockResolvedValue({
      attempt: {
        id: 101,
        status: 'in_progress',
        state_version: 8,
        cell_inputs: {
          '0:0': 'X',
          '0:1': 'Y',
          '9:9': 'Z',
        },
        correct_entry_ids: [31],
        elapsed_time: 22,
        started_at: null,
      },
      bestTime: 18,
    })
    const { attemptStore, crosswordStore } = initializeStores()
    const cancelSpy = vi.spyOn(crosswordStore, 'cancelScheduledSave')

    await expect(attemptStore.saveProgress()).resolves.toEqual({
      status: 'conflict',
      recovered: true,
    })

    expect(cancelSpy).toHaveBeenCalledOnce()
    expect(loadAttemptApi).toHaveBeenCalledWith(7)
    expect(attemptStore.stateVersion).toBe(8)
    expect(attemptStore.bestTime).toBe(18)
    expect(attemptStore.modified).toBe(false)
    expect(crosswordStore.cellInputs).toEqual({ '0:0': 'X', '0:1': 'Y' })
    expect(crosswordStore.entryStatus['31'].correct).toBe(true)
    expect(attemptStore.displayError).toContain('legfrissebb mentett állapotot betöltöttük')
  })

  /** Ellenőrzi az attempt indításakor visszakapott timerállapot alkalmazását. */
  it('elindítja az attemptet és alkalmazza a backend timeradatait', async () => {
    startAttemptApi.mockResolvedValue({
      attempt: {
        status: 'in_progress',
        elapsed_time: 12,
        started_at: '2026-01-10T12:00:00Z',
      },
    })
    const { attemptStore } = initializeStores()
    attemptStore.status = 'not_started'
    attemptStore.startedAt = null

    await expect(attemptStore.startAttempt()).resolves.toBe(true)
    expect(startAttemptApi).toHaveBeenCalledWith(101)
    expect(attemptStore.status).toBe('in_progress')
    expect(attemptStore.elapsedTime).toBe(12)
    expect(attemptStore.startedAt).toBe('2026-01-10T12:00:00Z')
  })

  /** Ellenőrzi az attempt leállításakor visszakapott eltelt idő alkalmazását. */
  it('leállítja az attemptet és nullázza a startedAt értéket', async () => {
    stopAttemptApi.mockResolvedValue({
      attempt: {
        status: 'in_progress',
        elapsed_time: 37,
        started_at: null,
      },
    })
    const { attemptStore } = initializeStores()

    await expect(attemptStore.stopAttempt()).resolves.toBe(true)
    expect(stopAttemptApi).toHaveBeenCalledWith(101)
    expect(attemptStore.elapsedTime).toBe(37)
    expect(attemptStore.startedAt).toBeNull()
  })

  /** Ellenőrzi, hogy a befejezés frissíti a timer- és legjobb idő adatokat. */
  it('befejezéskor frissíti a completed és best time állapotot', async () => {
    saveProgressApi.mockResolvedValue({
      attempt: {
        status: 'completed',
        state_version: 5,
        correct_entry_ids: [31],
        elapsed_time: 40,
        started_at: null,
      },
      best_time: 40,
    })
    const { attemptStore } = initializeStores()
    vi.spyOn(attemptStore, 'loadBestAttempts').mockResolvedValue(true)

    await attemptStore.saveProgress()

    expect(attemptStore.status).toBe('completed')
    expect(attemptStore.isCompleted).toBe(true)
    expect(attemptStore.elapsedTime).toBe(40)
    expect(attemptStore.startedAt).toBeNull()
    expect(attemptStore.bestTime).toBe(40)
    expect(attemptStore.loadBestAttempts).toHaveBeenCalledWith(7)
  })

  /** Ellenőrzi az unload beacon pontos payloadját. */
  it('aktuális cellákkal és verzióval küldi el a beacont', () => {
    const { attemptStore } = initializeStores()

    attemptStore.flushOnUnload()

    expect(saveAndStopBeacon).toHaveBeenCalledWith(
      101,
      { '0:0': 'A', '0:1': 'B' },
      4,
    )
  })

  /** Ellenőrzi, hogy feladás után minden kapcsolódó állapot újratöltődik. */
  it('feladás után újratölti az attemptet, rejtvényt és eredménylistát', async () => {
    abandonAttemptApi.mockResolvedValue({ success: true })
    const { attemptStore, crosswordStore } = initializeStores()
    vi.spyOn(attemptStore, 'loadAttempt').mockResolvedValue(true)
    vi.spyOn(attemptStore, 'loadBestAttempts').mockResolvedValue(true)
    vi.spyOn(crosswordStore, 'loadCrossword').mockResolvedValue()

    await expect(attemptStore.abandonAttempt()).resolves.toBe(true)

    expect(abandonAttemptApi).toHaveBeenCalledWith(101)
    expect(attemptStore.loadAttempt).toHaveBeenCalledWith(7)
    expect(crosswordStore.loadCrossword).toHaveBeenCalledWith(7)
    expect(attemptStore.loadBestAttempts).toHaveBeenCalledWith(7)
  })
})
