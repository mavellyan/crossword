import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import SolverCell from '@/components/crossword-solver/SolverCell.vue'

const cell = overrides => ({
  isBlocked: false,
  isIntersection: false,
  isActive: false,
  isInActiveEntry: false,
  isPending: false,
  isCorrect: false,
  isFilled: false,
  isLocked: false,
  ...overrides,
})

describe('SolverCell', () => {
  /** Ellenőrzi a beírt magyar betű normalizálását és továbbítását. */
  it('nagybetűs magyar karaktert emittál', async () => {
    const wrapper = mount(SolverCell, {
      props: { cell: cell(), modelValue: '' },
    })

    await wrapper.get('input').setValue('ű')

    expect(wrapper.emitted('update:modelValue')).toEqual([['Ű']])
  })

  /** Ellenőrzi, hogy szám és speciális karakter nem kerülhet a cellába. */
  it('kiszűri a nem engedélyezett karaktereket', async () => {
    const wrapper = mount(SolverCell, {
      props: { cell: cell(), modelValue: '' },
    })

    await wrapper.get('input').setValue('1!')

    expect(wrapper.emitted('update:modelValue')).toEqual([['']])
  })

  /** Ellenőrzi, hogy a helyesként zárolt cella nem módosítható. */
  it('nem engedi felülírni a zárolt cellát', async () => {
    const wrapper = mount(SolverCell, {
      props: { cell: cell({ isLocked: true }), modelValue: 'A' },
    })

    await wrapper.get('input').setValue('B')

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(wrapper.get('input').element.value).toBe('A')
  })

  /** Ellenőrzi, hogy zárolt cellán a törlés tiltott, a navigáció továbbra is emittálódik. */
  it('zárolt cellán blokkolja a törlést, de engedi a nyílbillentyűt', async () => {
    const wrapper = mount(SolverCell, {
      props: { cell: cell({ isLocked: true }), modelValue: 'A' },
    })

    await wrapper.get('input').trigger('keydown', { key: 'Backspace' })
    expect(wrapper.emitted('keydown')).toBeUndefined()

    await wrapper.get('input').trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.emitted('keydown')).toHaveLength(1)
  })
})
