import { createPinia, setActivePinia } from 'pinia'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/crosswordCreatorApi', () => ({
  createCrossword: vi.fn(),
  listCreatorWords: vi.fn(),
  getWordsForLetterFromList: vi.fn(() => []),
  getCrosswordForEdit: vi.fn(),
  updateCrossword: vi.fn(),
  deleteCrossword: vi.fn(),
}))

vi.mock('@/services/crosswordApi', () => ({
  listTopics: vi.fn(),
}))

import CrosswordCreator from '@/pages/CrosswordCreator.vue'
import { listCreatorWords } from '@/services/crosswordCreatorApi'
import { listTopics } from '@/services/crosswordApi'
import { useCrosswordEditorStore } from '@/stores/crosswordEditor'

describe('CrosswordCreator', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    listCreatorWords.mockResolvedValue([])
    listTopics.mockResolvedValue([
      { id: 1, name: 'Földrajz' },
      { id: 2, name: 'Nyelvek' },
    ])
  })

  /** Ellenőrzi, hogy témaváltáskor a korábbi clue-választások és elhelyezések törlődnek. */
  it('témaváltáskor törli a guided és free-form entryket', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const wrapper = shallowMount(CrosswordCreator, {
      global: {
        plugins: [pinia],
        mocks: {
          $route: { query: {} },
          $router: { push: vi.fn() },
          $notify: vi.fn(),
        },
        directives: {
          tooltip: {},
        },
        stubs: {
          'v-select': true,
          'font-awesome-icon': true,
          ClueCreatorModal: true,
          CrosswordGridEditor: true,
        },
      },
    })

    await flushPromises()
    vi.clearAllMocks()

    const editor = useCrosswordEditorStore()
    editor.loadEntries([{
      id: 101,
      clue_id: 1,
      solution: 'HELLO',
      definition: 'Angol köszönés',
      direction: 'horizontal',
      start_row: 0,
      start_col: 0,
    }])
    wrapper.vm.selectedWords = [{ id: 1, solution: 'HELLO' }]

    await wrapper.setData({
      selectedTopics: [{ id: 2, name: 'Nyelvek' }],
    })
    await flushPromises()

    expect(wrapper.vm.selectedWords).toEqual([])
    expect(editor.entries).toEqual([])
    expect(listCreatorWords).toHaveBeenCalledWith([2])
  })
})
