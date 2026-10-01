import { createPinia, setActivePinia } from 'pinia'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import CrosswordPage from '@/pages/CrosswordPage.vue'
import { useAttemptStore } from '@/stores/attempt'
import { useAuthStore } from '@/stores/auth'
import { useCrosswordStore } from '@/stores/crossword'

describe('CrosswordPage', () => {
  /** Ellenőrzi, hogy a mentési konfliktus üzenete és az újrapróbálás megjelenik. */
  it('megjeleníti a konfliktusüzenetet és újrapróbálja a mentést', async () => {
    localStorage.setItem('token', 'token')
    const pinia = createPinia()
    setActivePinia(pinia)

    const authStore = useAuthStore()
    expect(authStore.isLoggedIn).toBe(true)

    const crosswordStore = useCrosswordStore()
    crosswordStore.id = 7
    crosswordStore.title = 'Konfliktusos rejtvény'
    crosswordStore.creator = 'tesztelo'
    crosswordStore.words = []
    crosswordStore.grid = []
    crosswordStore.width = 0
    crosswordStore.height = 0
    vi.spyOn(crosswordStore, 'loadCrossword').mockResolvedValue()

    const attemptStore = useAttemptStore()
    attemptStore.id = 101
    attemptStore.status = 'in_progress'
    attemptStore.modified = true
    attemptStore.displayError = 'A próbálkozás egy másik lapon módosult.'
    vi.spyOn(attemptStore, 'loadAttempt').mockResolvedValue(true)
    vi.spyOn(attemptStore, 'loadBestAttempts').mockResolvedValue(true)

    const wrapper = shallowMount(CrosswordPage, {
      props: { id: '7' },
      global: {
        plugins: [pinia],
        mocks: {
          $notify: vi.fn(),
        },
        directives: {
          tooltip: {},
        },
        stubs: {
          CrosswordGridSolver: true,
          RouterLink: true,
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('A próbálkozás egy másik lapon módosult.')
    expect(wrapper.text()).toContain('Mentés újrapróbálása')

    const saveSpy = vi
      .spyOn(attemptStore, 'saveProgress')
      .mockResolvedValue({ status: 'saved' })

    const retryButton = wrapper
      .findAll('button')
      .find(button => button.text().includes('Mentés újrapróbálása'))

    expect(retryButton).toBeDefined()

    await retryButton.trigger('click')
    await flushPromises()

    expect(saveSpy).toHaveBeenCalledOnce()

    wrapper.unmount()
  })
})
