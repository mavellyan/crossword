import { createPinia, setActivePinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import EntryPlacementPanel from '@/components/crossword-editor/EntryPlacementPanel.vue'
import { useCrosswordEditorStore } from '@/stores/crosswordEditor'

describe('EntryPlacementPanel', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  /** Ellenőrzi, hogy a frontend validáció konkrét hibaüzenete megjelenik. */
  it('megjeleníti a jelölt elhelyezés validációs hibáját', () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const editor = useCrosswordEditorStore()

    editor.loadEntries([{
      id: 101,
      clue_id: 1,
      solution: 'HELLO',
      definition: 'Angol köszönés',
      direction: 'horizontal',
      start_row: 1,
      start_col: 0,
    }])
    editor.selectClue({ id: 2, solution: 'DORK', definition: 'Teszt' })
    editor.selectCell(1, 0)
    editor.setDirection('vertical')

    const wrapper = mount(EntryPlacementPanel, {
      props: {
        words: [],
        wordsLoading: false,
        wordsError: null,
        isReadOnly: false,
      },
      global: {
        plugins: [pinia],
        stubs: {
          'v-select': true,
          'font-awesome-icon': true,
        },
      },
    })

    expect(wrapper.text()).toContain(
      'A szó betűi ütköznek egy már elhelyezett szó betűivel!'
    )
    expect(wrapper.get('button.btn-primary').attributes('disabled')).toBeDefined()
  })
})
