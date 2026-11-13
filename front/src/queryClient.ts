import { QueryClient } from '@tanstack/react-query'
import { ApiError } from './api'

// Keeps what was read from the API, so a page opened again shows it at once and refreshes it in the background.
export function createQueryClient(): QueryClient {
  return new QueryClient({ defaultOptions: { queries: { retry: shouldRetry } } })
}

// One retry, for a passing network hiccup or a server error. An answer about the request itself (404, 422…) would
// only come again, and a 401 is handled by apiFetch.
export function shouldRetry(failures: number, error: Error): boolean {
  return failures < 1 && !(error instanceof ApiError && error.status < 500)
}
