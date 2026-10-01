import { afterEach, beforeEach, vi } from 'vitest'

beforeEach(() => {
  localStorage.clear()
})

afterEach(() => {
  vi.clearAllTimers()
  vi.useRealTimers()
})
