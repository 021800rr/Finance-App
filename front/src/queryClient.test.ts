import { describe, expect, it } from 'vitest'
import { ApiError } from './api'
import { createQueryClient, shouldRetry } from './queryClient'

const refused = (status: number) => new ApiError(status, undefined, `GET /api/accounts: ${status}`)

describe('shouldRetry', () => {
  it.each([
    ['a network hiccup', new TypeError('Failed to fetch')],
    ['a server error', refused(500)],
    ['a gateway timeout', refused(504)],
  ])('reads once more after %s, and only once', (_, error) => {
    expect(shouldRetry(0, error)).toBe(true)
    expect(shouldRetry(1, error)).toBe(false)
  })

  // The same request would get the same answer.
  it.each([401, 403, 404, 422])('does not read again after an answer about the request: %s', (status) => {
    expect(shouldRetry(0, refused(status))).toBe(false)
  })

  it('is the rule of the app', () => {
    expect(createQueryClient().getDefaultOptions().queries?.retry).toBe(shouldRetry)
  })
})
