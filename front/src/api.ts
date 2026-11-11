import type { components } from './generated/api'

// The backend keeps both tokens in HttpOnly cookies (BEARER and refresh_token). The browser stores them and sends
// them with every request to /api by itself; this code never sees them.

// One field error of a 422 answer: the field (e.g. "amount"), a stable code and the values of its message.
export type Violation = { propertyPath: string; message: string; code?: string; params?: Record<string, string> }

// The error body of the API (RFC 9457 Problem Details): errorCode is stable, detail is English text for curl.
type Problem = { detail?: string; errorCode?: string; params?: Record<string, string>; violations?: Violation[] }

// An answer of the API other than 2xx: the errorCode of its body with the values of its message, and for 422 the
// field errors.
export class ApiError extends Error {
  readonly status: number
  readonly errorCode: string | undefined
  readonly params: Record<string, string>
  readonly violations: Violation[]

  constructor(
    status: number,
    errorCode: string | undefined,
    message: string,
    params: Record<string, string> = {},
    violations: Violation[] = [],
  ) {
    super(message)
    this.status = status
    this.errorCode = errorCode
    this.params = params
    this.violations = violations
  }
}

async function apiError(method: string, path: string, response: Response): Promise<ApiError> {
  // A proxy in between may answer with something that is not JSON.
  const problem: Problem = await response.json().catch(() => ({}))

  return new ApiError(
    response.status,
    problem.errorCode,
    problem.detail ?? `${method} ${path}: ${response.status}`,
    problem.params ?? {},
    problem.violations ?? [],
  )
}

let refreshing: Promise<boolean> | null = null

let onSessionEnd = (): void => {}

// Called when the API refuses a request even after a refresh: the login has ended (logged out in another tab,
// or the refresh token expired). AuthProvider shows the login form then.
export function setSessionEndHandler(handler: () => void): void {
  onSessionEnd = handler
}

// One refresh at a time: a refresh token works only once, and sending the same one twice (two requests getting
// 401 together) would end the whole login. Every caller waits for the same refresh.
function refreshTokens(): Promise<boolean> {
  refreshing ??= fetch('/api/token/refresh', { method: 'POST' })
    .then((response) => response.ok)
    .finally(() => {
      refreshing = null
    })

  return refreshing
}

// A request to the API. When the JWT has expired (401), it gets new tokens once and repeats the request.
export async function apiFetch(path: string, init?: RequestInit): Promise<Response> {
  const response = await fetch(path, init)
  if (response.status !== 401) {
    return response
  }
  const retried = (await refreshTokens()) ? await fetch(path, init) : response
  if (retried.status === 401) {
    onSessionEnd()
  }

  return retried
}

// Reads a resource of the API as plain JSON (without the JSON-LD fields); any answer but 2xx is an error.
export async function getJson<T>(path: string): Promise<T> {
  const response = await apiFetch(path, { headers: { Accept: 'application/json' } })
  if (!response.ok) {
    throw await apiError('GET', path, response)
  }

  return response.json()
}

// One page of a list of the API.
export type Page<T> = { items: T[]; totalItems: number; lastPage: number }

type JsonLdCollection<T> = { member: T[]; totalItems: number; view?: { last?: string } }

// Reads one page of a list. Plain JSON gives the rows only; JSON-LD also says how many rows there are and links the
// last page ("view.last", absent when there is just one page).
export async function getPage<T>(path: string): Promise<Page<T>> {
  const response = await apiFetch(path, { headers: { Accept: 'application/ld+json' } })
  if (!response.ok) {
    throw await apiError('GET', path, response)
  }
  const collection: JsonLdCollection<T> = await response.json()
  const last = collection.view?.last

  return {
    items: collection.member,
    totalItems: collection.totalItems,
    lastPage: last === undefined ? 1 : Number(new URL(last, window.location.origin).searchParams.get('page') ?? 1),
  }
}

// Creates a resource and returns it as the API stored it (with its id).
export async function postJson<T>(path: string, body: object): Promise<T> {
  const response = await apiFetch(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(body),
  })
  if (!response.ok) {
    throw await apiError('POST', path, response)
  }

  return response.json()
}

// Changes some fields of a resource: the fields sent change, the others stay (JSON Merge Patch).
export async function patchJson(path: string, changes: object): Promise<void> {
  const response = await apiFetch(path, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/merge-patch+json', Accept: 'application/json' },
    body: JSON.stringify(changes),
  })
  if (!response.ok) {
    throw await apiError('PATCH', path, response)
  }
}

// Deletes a resource; the API answers 204 No Content.
export async function deleteResource(path: string): Promise<void> {
  const response = await apiFetch(path, { method: 'DELETE', headers: { Accept: 'application/json' } })
  if (!response.ok) {
    throw await apiError('DELETE', path, response)
  }
}

// Logs in. Returns null on success, otherwise why not (e.g. a wrong password, or the login locked for a while).
export async function login(email: string, password: string): Promise<ApiError | null> {
  const response = await fetch('/api/login_check', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  })

  return response.ok ? null : apiError('POST', '/api/login_check', response)
}

// Deletes the refresh token, blocks the JWT and clears both cookies. 400 only means there was no refresh token
// left to delete: logged out either way.
export async function logout(): Promise<void> {
  await fetch('/api/token/invalidate', { method: 'POST' })
}

// generated from the OpenAPI description of the API (npm run api-types)
type Me = components['schemas']['Me']

// The logged-in user's email, or null when logged out. The cookies are invisible to JavaScript, so asking the API
// is the only way to know.
export async function currentUser(): Promise<string | null> {
  const response = await apiFetch('/api/me', { headers: { Accept: 'application/json' } })
  if (response.status === 401) {
    return null
  }
  if (!response.ok) {
    throw new Error(`GET /api/me answered ${response.status}.`)
  }
  const me: Me = await response.json()

  return me.email
}
