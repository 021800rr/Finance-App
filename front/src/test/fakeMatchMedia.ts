import { vi } from 'vitest'

// window.matchMedia, which jsdom lacks: every query answers `matches` until change() switches it, as the system would,
// telling the listeners.
export function fakeMatchMedia(matches: boolean) {
  const queries: string[] = []
  const listeners = new Set<() => void>()
  let current = matches
  vi.stubGlobal('matchMedia', (query: string) => {
    queries.push(query)

    return {
      media: query,
      get matches() { return current },
      addEventListener: (_: string, listener: () => void) => listeners.add(listener),
      removeEventListener: (_: string, listener: () => void) => listeners.delete(listener),
    }
  })

  return {
    queries,
    listeners,
    change(next: boolean) {
      current = next
      listeners.forEach((listener) => { listener() })
    },
  }
}
