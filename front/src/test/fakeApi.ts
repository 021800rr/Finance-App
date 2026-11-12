import { vi } from 'vitest'

// Replies to what a page saves or deletes: a response, or undefined for a request it does not know.
export type Answer = (method: string, path: string) => Response | undefined

// The API of a page test: `reads` answers the GET requests by path, `answer` anything else (saving, deleting). A
// request neither knows fails the test, so a page cannot quietly ask for more than the test expects.
export function fakeApi(reads: Record<string, unknown>, answer: Answer = () => undefined) {
  const fetch = vi.fn((path: string, init?: RequestInit) => {
    const method = init?.method ?? 'GET'
    const response = answer(method, path)
      ?? (method === 'GET' && Object.hasOwn(reads, path) ? Response.json(reads[path]) : undefined)
    if (response === undefined) {
      throw new Error(`Unexpected request: ${method} ${path}`)
    }

    return Promise.resolve(response)
  })
  vi.stubGlobal('fetch', fetch)

  return fetch
}

// The JSON the page sent with the first request of the method.
export function sentJson(fetch: ReturnType<typeof fakeApi>, method: string): unknown {
  const body = fetch.mock.calls.find(([, init]) => init?.method === method)?.[1]?.body

  return JSON.parse(typeof body === 'string' ? body : '')
}
