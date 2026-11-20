import type { components } from './generated/api'

// The backend keeps both tokens in HttpOnly cookies (BEARER and refresh_token). The browser stores them and sends
// them with every request to /api by itself; this code never sees them.

// A refused value of the API, as the forms use it: the field (a property, "lines[0].amount", or a query parameter),
// the English text, a stable code and the values for the message.
export type Violation = { propertyPath: string; message: string; code: string; params: Record<string, string> }

// What the app reads from an error of the API (RFC 9457 Problem Details): the code at the end of its "type", the
// members of that type as the values of the message, and the refused values of a validation problem.
type Problem = { errorCode?: string; detail?: string; params: Record<string, string>; violations: Violation[] }

const TYPE_PREFIX = 'urn:finance-app:problem:'

// The members every problem has, and "errors" of a validation problem; any other member belongs to the type.
const STANDARD_MEMBERS = new Set(['type', 'title', 'status', 'detail', 'instance', 'errors'])

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function stringParams(value: unknown): Record<string, string> {
  return isRecord(value)
    ? Object.fromEntries(
      Object.entries(value).filter((entry): entry is [string, string] => typeof entry[1] === 'string'),
    )
    : {}
}

// A JSON Pointer of the API in its URI fragment form (RFC 6901) as the property it names: '#/lines/0/amount' →
// 'lines[0].amount'.
function propertyOf(pointer: string): string {
  return pointer.split('/').slice(1)
    .map((token) => decodeURIComponent(token).replace(/~1/g, '/').replace(/~0/g, '~'))
    .map((token, index) => (/^\d+$/.test(token) ? `[${token}]` : `${index === 0 ? '' : '.'}${token}`))
    .join('')
}

function readViolation(item: unknown): Violation | undefined {
  if (!isRecord(item) || typeof item.detail !== 'string') {
    return undefined
  }
  const where = typeof item.pointer === 'string' ? propertyOf(item.pointer) : item.parameter
  if (typeof where !== 'string') {
    return undefined
  }

  return {
    propertyPath: where,
    message: item.detail,
    code: typeof item.code === 'string' ? item.code : '',
    params: stringParams(item.params),
  }
}

// A proxy may send HTML, null or JSON unrelated to our API. Keep usable details without trusting that shape.
function readProblem(value: unknown): Problem {
  if (!isRecord(value)) {
    return { params: {}, violations: [] }
  }
  const items: unknown[] = Array.isArray(value.errors) ? value.errors : []

  return {
    ...(typeof value.type === 'string' && value.type.startsWith(TYPE_PREFIX)
      && { errorCode: value.type.slice(TYPE_PREFIX.length) }),
    ...(typeof value.detail === 'string' && { detail: value.detail }),
    params: stringParams(Object.fromEntries(Object.entries(value).filter(([name]) => !STANDARD_MEMBERS.has(name)))),
    violations: items.map(readViolation).filter((violation) => violation !== undefined),
  }
}

// Successful payloads follow the generated API contract; this assertion does not validate JSON at runtime.
function readJson<T>(response: Response): Promise<T> {
  return response.json() as Promise<T>
}

// An answer of the API other than 2xx: the code of its problem type with the values of its message, and for a
// validation problem the field errors.
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
  const body: unknown = await response.json().catch(() => undefined)
  const problem = readProblem(body)

  return new ApiError(
    response.status,
    problem.errorCode,
    problem.detail ?? `${method} ${path}: ${response.status}`,
    problem.params,
    problem.violations,
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

  return readJson<T>(response)
}

// One page of a list of the API.
export type Page<T> = { items: T[]; totalItems: number; lastPage: number }

// A page of a list as the API gives it: the rows in "content", the numbers of the page in "page" (the other pages
// are also in the Link header).
type PagedList<T> = { content: T[]; page: { size: number; number: number; totalElements: number; totalPages: number } }

// Reads one page of a list: its rows, how many rows there are in all, and the number of the last page.
export async function getPage<T>(path: string): Promise<Page<T>> {
  const response = await apiFetch(path, { headers: { Accept: 'application/json' } })
  if (!response.ok) {
    throw await apiError('GET', path, response)
  }
  const list = await readJson<PagedList<T>>(response)

  return { items: list.content, totalItems: list.page.totalElements, lastPage: Math.max(1, list.page.totalPages) }
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

  return readJson<T>(response)
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
  const me = await readJson<Me>(response)

  return me.email
}
