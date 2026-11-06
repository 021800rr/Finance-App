// The backend keeps both tokens in HttpOnly cookies (BEARER and refresh_token). The browser stores them and sends
// them with every request to /api by itself; this code never sees them.

type Problem = { detail?: string }

let refreshing: Promise<boolean> | null = null

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
  if (response.status !== 401 || !(await refreshTokens())) {
    return response
  }

  return fetch(path, init)
}

// Logs in. Returns null on success, otherwise the backend's message to show (e.g. wrong password, login locked).
export async function login(email: string, password: string): Promise<string | null> {
  const response = await fetch('/api/login_check', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  })
  if (response.ok) {
    return null
  }
  const problem: Problem = await response.json()

  return problem.detail ?? 'Logowanie nie powiodło się.'
}

// Deletes the refresh token, blocks the JWT and clears both cookies. 400 only means there was no refresh token
// left to delete: logged out either way.
export async function logout(): Promise<void> {
  await fetch('/api/token/invalidate', { method: 'POST' })
}

type Me = { email: string }

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
