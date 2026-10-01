import { createPinia, setActivePinia } from 'pinia'
import { shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import CrosswordGridSolver from '@/components/crossword-solver/CrosswordGridSolver.vue'
import { useCrosswordStore } from '@/stores/crossword'

const crosswordFixture = () => ({
  id: 15,
  title: 'Metszéspont teszt',
  creator: 'tesztelo',
  grid: [
    ['#', '#', null, '#', '#'],
    ['#', '#', null, '#', '#'],
    ['#', null, null, null, null],
    ['#', '#', null, '#', '#'],
  ],
  width: 5,
  height: 4,
  words: [
    {
      placement_id: 11,
      definition: 'Gyümölcs',
      direction: 'horizontal',
      start_row: 2,
      start_col: 1,
      cells: [
        { row: 2, col: 1 },
        { row: 2, col: 2 },
        { row: 2, col: 3 },
        { row: 2, col: 4 },
      ],
    },
    {
      placement_id: 12,
      definition: 'Elv',
      direction: 'vertical',
      start_row: 1,
      start_col: 2,
      cells: [
        { row: 1, col: 2 },
        { row: 2, col: 2 },
        { row: 3, col: 2 },
      ],
    },
  ],
})

describe('CrosswordGridSolver', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  /** Ellenőrzi, hogy ismételt kattintás vált a metszéspont két entryje között. */
  it('metszéspont ismételt kiválasztásakor irányt vált', () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const store = useCrosswordStore()
    store.initializePlayState(crosswordFixture())

    const wrapper = shallowMount(CrosswordGridSolver, {
      props: { isCrosswordStarted: true },
      global: {
        plugins: [pinia],
        stubs: {
          SolverCell: true,
          ClueList: true,
        },
      },
    })

    wrapper.vm.setCellAndEntryActive(2, 2, ['11', '12'])
    expect(store.activeEntryId).toBe('11')
    expect(store.activeDirection).toBe('horizontal')

    wrapper.vm.setCellAndEntryActive(2, 2, ['11', '12'])
    expect(store.activeEntryId).toBe('12')
    expect(store.activeDirection).toBe('vertical')
  })

  /** Ellenőrzi, hogy metszéspontba írás közös cellát módosít és továbbléptet. */
  it('metszéspontba íráskor mindkét entry közös cellája frissül', () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const store = useCrosswordStore()
    store.initializePlayState(crosswordFixture())
    store.selectEntry('11')
    store.selectCell(2, 2)

    const wrapper = shallowMount(CrosswordGridSolver, {
      props: { isCrosswordStarted: true },
      global: {
        plugins: [pinia],
        stubs: {
          SolverCell: true,
          ClueList: true,
        },
      },
    })
    vi.spyOn(wrapper.vm, 'focusInput').mockImplementation(() => {})

    wrapper.vm.onCellModelValueUpdate('2:2', 'l')

    expect(store.getCellValue(2, 2)).toBe('L')
    expect(store.getEntryInput('11')).toBe('L')
    expect(store.getEntryInput('12')).toBe('L')
    expect(store.activeCellKey).toBe('2:3')
  })

  it('gépeléskor átugorja a helyes keresztező szó zárolt celláját', () => {
    const pinia = createPinia()
    setActivePinia(pinia)

    const store = useCrosswordStore()
    store.initializePlayState(crosswordFixture())

    // A vízszintes bejegyzés már helyes, ezért annak minden cellája,
    // köztük a 2:2 metszéspont is zárolt.
    store.updateEntryStatuses([11])

    // A függőleges bejegyzést fejtjük.
    store.selectEntry('12')
    store.selectCell(1, 2)

    const wrapper = shallowMount(CrosswordGridSolver, {
      props: {
        isCrosswordStarted: true,
      },
      global: {
        plugins: [pinia],
        stubs: {
          SolverCell: true,
          ClueList: true,
        },
      },
    })

    vi.spyOn(wrapper.vm, 'focusInput').mockImplementation(() => {})

    wrapper.vm.onCellModelValueUpdate('1:2', 'E')

    // A 2:2 cellát átugorja, és közvetlenül a 3:2 cellára lép.
    expect(store.activeCellKey).toBe('3:2')
    expect(wrapper.vm.focusInput).toHaveBeenCalledWith(3, 2)
  })

  it('visszafelé navigálva is átugorja a zárolt cellát', () => {
    const store = useCrosswordStore()
    store.initializePlayState(crosswordFixture())
    store.updateEntryStatuses([11])

    expect(
      store.getPreviousCellInEntry('12', '3:2'),
    ).toEqual({
      row: 1,
      col: 2,
    })
  })
})
