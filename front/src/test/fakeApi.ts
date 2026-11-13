import { vi } from 'vitest'

// Replies to what a page asks beyond its plain reads (saving, deleting, searching): a response, or undefined for a
// request it does not know.
export type Answer = (method: string, path: string) => Response | undefined

// The API of a page test: the `answers` reply in turn, the first that knows a request; `reads` answers the other GET
// requests by path. A request none knows fails the test, so a page cannot quietly ask for more than the test expects.
export function fakeApi(reads: Record<string, unknown>, ...answers: (Answer | undefined)[]) {
  const fetch = vi.fn((path: string, init?: RequestInit) => {
    const method = init?.method ?? 'GET'
    const response = answers.reduce<Response | undefined>((found, answer) => found ?? answer?.(method, path), undefined)
      ?? (method === 'GET' && Object.hasOwn(reads, path) ? Response.json(reads[path]) : undefined)
    if (response === undefined) {
      throw new Error(`Unexpected request: ${method} ${path}`)
    }

    return Promise.resolve(response)
  })
  vi.stubGlobal('fetch', fetch)

  return fetch
}

// GET /api/contractors?name=… as the API answers it: the contractors whose name contains the text, in any letter case.
export function contractorSearch(contractors: { id: number; name: string }[]): Answer {
  return (method, path) => {
    const text = new URL(path, 'http://localhost').searchParams.get('name')
    if (method !== 'GET' || !path.startsWith('/api/contractors?') || text === null) {
      return undefined
    }

    return Response.json(contractors.filter((contractor) => contractor.name.toLowerCase().includes(text.toLowerCase())))
  }
}

// The JSON the page sent with the first request of the method, to the path when one is given.
export function sentJson(fetch: ReturnType<typeof fakeApi>, method: string, path?: string): unknown {
  const body = fetch.mock.calls
    .find(([sentTo, init]) => init?.method === method && (path === undefined || sentTo === path))?.[1]?.body

  return JSON.parse(typeof body === 'string' ? body : '')
}
