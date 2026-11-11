import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { renderHook, waitFor } from '@testing-library/react'
import type { ReactNode } from 'react'
import { afterEach, expect, it, vi } from 'vitest'
import { useOperationSearch } from './searchApi'

afterEach(() => { vi.unstubAllGlobals() })

it('keeps results between pages of one phrase, but hides them when the phrase changes', async () => {
  const fetch = vi.fn()
    .mockResolvedValueOnce(Response.json({ member: [], totalItems: 42, view: { last: '?page=3' } }))
    .mockImplementation(() => new Promise(() => {}))
  vi.stubGlobal('fetch', fetch)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  function wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }
  const { result, rerender, unmount } = renderHook(
    ({ phrase, page }) => useOperationSearch(phrase, page),
    { wrapper, initialProps: { phrase: 'rent', page: 1 } },
  )
  await waitFor(() => { expect(result.current.data?.totalItems).toBe(42) })

  rerender({ phrase: 'rent', page: 2 })
  expect(result.current.isPlaceholderData).toBe(true)
  expect(result.current.data?.totalItems).toBe(42)

  rerender({ phrase: 'salary', page: 2 })
  expect(result.current.data).toBeUndefined()
  expect(result.current.isPending).toBe(true)
  unmount()
  client.clear()
})
