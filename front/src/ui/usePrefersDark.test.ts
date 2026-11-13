import { act, renderHook } from '@testing-library/react'
import { afterEach, expect, it, vi } from 'vitest'
import { fakeMatchMedia } from '../test/fakeMatchMedia'
import { usePrefersDark } from './usePrefersDark'

afterEach(() => { vi.unstubAllGlobals() })

it('follows the dark style of the system, a change at once, and stops listening when gone', () => {
  const media = fakeMatchMedia(false)
  const { result, unmount } = renderHook(() => usePrefersDark())

  expect(result.current).toBe(false)
  expect(media.queries).toContain('(prefers-color-scheme: dark)')

  act(() => { media.change(true) })

  expect(result.current).toBe(true)

  unmount()

  expect(media.listeners.size).toBe(0)
})
