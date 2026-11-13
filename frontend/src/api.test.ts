import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, apiFetch, getJson, setSessionEndHandler } from './api'

function deferred<T>() {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((done) => { resolve = done })

  return { promise, resolve }
}

afterEach(() => {
  vi.unstubAllGlobals()
  setSessionEndHandler(() => {})
})

describe('apiFetch', () => {
  it('shares one refresh between concurrent 401 responses and retries the original requests', async () => {
    const refresh = deferred<Response>()
    const ended = vi.fn()
    setSessionEndHandler(ended)
    const fetch = vi.fn()
      .mockResolvedValueOnce(new Response(null, { status: 401 }))
      .mockResolvedValueOnce(new Response(null, { status: 401 }))
      .mockReturnValueOnce(refresh.promise)
      .mockResolvedValue(new Response(null, { status: 200 }))
    vi.stubGlobal('fetch', fetch)
    const init = { method: 'PATCH', body: '{"confirmed":true}' }
    const first = apiFetch('/api/one', init)
    const second = apiFetch('/api/two')

    await vi.waitFor(() => { expect(fetch).toHaveBeenCalledTimes(3) })
    expect(fetch).toHaveBeenNthCalledWith(3, '/api/token/refresh', { method: 'POST' })
    refresh.resolve(new Response(null, { status: 200 }))

    expect((await first).status).toBe(200)
    expect((await second).status).toBe(200)
    expect(fetch).toHaveBeenCalledWith('/api/one', init)
    expect(fetch).toHaveBeenCalledTimes(5)
    expect(ended).not.toHaveBeenCalled()
  })

  it('ends the session after a refused refresh, without retrying the request', async () => {
    const ended = vi.fn()
    setSessionEndHandler(ended)
    const fetch = vi.fn().mockResolvedValue(new Response(null, { status: 401 }))
    vi.stubGlobal('fetch', fetch)

    expect((await apiFetch('/api/one')).status).toBe(401)
    expect(fetch).toHaveBeenCalledTimes(2)
    expect(ended).toHaveBeenCalledTimes(1)
  })

  it('does not loop if the retry is also unauthorized', async () => {
    const ended = vi.fn()
    setSessionEndHandler(ended)
    const fetch = vi.fn()
      .mockResolvedValueOnce(new Response(null, { status: 401 }))
      .mockResolvedValueOnce(new Response(null, { status: 200 }))
      .mockResolvedValueOnce(new Response(null, { status: 401 }))
    vi.stubGlobal('fetch', fetch)

    expect((await apiFetch('/api/one')).status).toBe(401)
    expect(fetch).toHaveBeenCalledTimes(3)
    expect(ended).toHaveBeenCalledTimes(1)
  })

  it('propagates a network error and allows a later refresh to succeed', async () => {
    const ended = vi.fn()
    setSessionEndHandler(ended)
    const fetch = vi.fn()
      .mockResolvedValueOnce(new Response(null, { status: 401 }))
      .mockRejectedValueOnce(new TypeError('offline'))
      .mockResolvedValueOnce(new Response(null, { status: 401 }))
      .mockResolvedValueOnce(new Response(null, { status: 200 }))
      .mockResolvedValueOnce(new Response(null, { status: 200 }))
    vi.stubGlobal('fetch', fetch)

    await expect(apiFetch('/api/one')).rejects.toThrow('offline')
    expect((await apiFetch('/api/one')).status).toBe(200)
    expect(ended).not.toHaveBeenCalled()
  })
})

describe('API error boundary', () => {
  it.each(['<html>proxy error</html>', 'null', '[]', '{"violations":[null,12]}'])(
    'turns an unexpected error body into ApiError: %s', async (body) => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(body, { status: 502 })))

      await expect(getJson('/api/one')).rejects.toMatchObject({
        status: 502, message: 'GET /api/one: 502', violations: [],
      })
      await expect(getJson('/api/one')).rejects.toBeInstanceOf(ApiError)
    },
  )

  it('preserves valid field errors and drops malformed entries and parameters', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(Response.json({
      errorCode: 'validation.failed',
      violations: [null, { propertyPath: 'amount', message: 'Too big', code: 'limit', params: { max: '10', bad: 3 } }],
    }, { status: 422 })))

    await expect(getJson('/api/one')).rejects.toMatchObject({
      errorCode: 'validation.failed',
      violations: [{ propertyPath: 'amount', message: 'Too big', code: 'limit', params: { max: '10' } }],
    })
  })
})
