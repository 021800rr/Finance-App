import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, render, renderHook, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { getJson } from '../api'
import App from '../App'
import i18n from '../i18n'
import { queryKeys } from '../queryKeys'
import { type Answer, fakeApi, sentJson } from '../test/fakeApi'
import { useAuth } from './AuthContext'
import { AuthProvider } from './AuthProvider'

const EMAIL = 'user@example.com'

type Session = { email: string | null }

// The API of the login: GET /api/me names the user of the session, or refuses without one; a refresh never helps.
// `answer` replies to anything else.
function serve(session: Session, answer: Answer = () => undefined) {
  return fakeApi({}, (method, path) => {
    if (method === 'GET' && path === '/api/me') {
      return session.email === null
        ? Response.json({ errorCode: 'auth.token_missing' }, { status: 401 })
        : Response.json({ email: session.email })
    }
    if (method === 'POST' && path === '/api/token/refresh') {
      return Response.json({ errorCode: 'auth.refresh_token_missing' }, { status: 401 })
    }

    return answer(method, path)
  })
}

// The app as main.tsx starts it, opened at an address of its own (the page for an unknown one: it reads nothing).
function showApp() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/no-such-page']}>
        <AuthProvider>
          <App />
        </AuthProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )

  return client
}

async function logIn(password: string) {
  const user = userEvent.setup()
  await user.type(await screen.findByLabelText(i18n.t('login.email')), EMAIL)
  await user.type(screen.getByLabelText(i18n.t('login.password')), password)
  await user.click(screen.getByRole('button', { name: i18n.t('login.submit') }))
}

const loginForm = () => screen.findByRole('button', { name: i18n.t('login.submit') })
const logoutButton = () => screen.findByRole('button', { name: i18n.t('nav.logout') })

afterEach(() => {
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

describe('logging in and out', () => {
  it('asks for a login when the cookies hold none', async () => {
    serve({ email: null })
    showApp()

    expect(screen.getByText(i18n.t('app.checking_login'))).toBeInTheDocument()
    expect(await loginForm()).toBeInTheDocument()
  })

  it('asks for a login when the server does not answer', async () => {
    fakeApi({}, () => { throw new TypeError('Failed to fetch') })
    showApp()

    expect(await loginForm()).toBeInTheDocument()
  })

  it('logs in and then opens the address the link pointed to', async () => {
    const session: Session = { email: null }
    const fetch = serve(session, (method, path) => {
      if (method !== 'POST' || path !== '/api/login_check') {
        return undefined
      }
      session.email = EMAIL

      return new Response(null, { status: 204 })
    })
    showApp()

    await logIn('secret')

    expect(await screen.findByText(i18n.t('error.not_found_title'))).toBeInTheDocument()
    expect(await logoutButton()).toBeInTheDocument()
    expect(sentJson(fetch, 'POST', '/api/login_check')).toEqual({ email: EMAIL, password: 'secret' })
  })

  it('says why the login was refused', async () => {
    serve({ email: null }, (method, path) => method === 'POST' && path === '/api/login_check'
      ? Response.json({ errorCode: 'auth.invalid_credentials' }, { status: 401 })
      : undefined)
    showApp()

    await logIn('wrong')

    expect(await screen.findByRole('alert')).toHaveTextContent(i18n.t('auth.invalid_credentials'))
    expect(screen.getByRole('button', { name: i18n.t('login.submit') })).toBeEnabled()
  })

  it('says the server is down when the login does not reach it', async () => {
    serve({ email: null }, (method, path) => {
      if (method === 'POST' && path === '/api/login_check') {
        throw new TypeError('Failed to fetch')
      }

      return undefined
    })
    showApp()

    await logIn('secret')

    expect(await screen.findByRole('alert')).toHaveTextContent(i18n.t('app.server_down'))
    expect(screen.getByRole('button', { name: i18n.t('login.submit') })).toBeEnabled()
  })

  // Nothing read while logged in may stay in memory for the next user of the browser.
  it('logs out and forgets what was read', async () => {
    const session: Session = { email: EMAIL }
    serve(session, (method, path) => {
      if (method !== 'POST' || path !== '/api/token/invalidate') {
        return undefined
      }
      session.email = null

      return new Response(null, { status: 204 })
    })
    const client = showApp()
    const logout = await logoutButton()
    client.setQueryData(queryKeys.accounts(), [])

    await userEvent.click(logout)

    expect(await loginForm()).toBeInTheDocument()
    expect(client.getQueryCache().getAll()).toHaveLength(0)
  })

  // The cookies still work then: staying logged in without saying so would look like a logout that happened.
  it('stays logged in and says so when the logout does not reach the server', async () => {
    serve({ email: EMAIL }, (method, path) => {
      if (method === 'POST' && path === '/api/token/invalidate') {
        throw new TypeError('Failed to fetch')
      }

      return undefined
    })
    showApp()

    await userEvent.click(await logoutButton())

    expect(await screen.findByRole('alert')).toHaveTextContent(i18n.t('app.server_down'))
    expect(screen.getByRole('button', { name: i18n.t('nav.logout') })).toBeEnabled()
  })

  // Logged out in another tab, or the refresh token expired: the next request is refused, and so is the refresh.
  it('asks for a login again when the login has ended elsewhere, and forgets what was read', async () => {
    serve({ email: EMAIL }, (method, path) => method === 'GET' && path === '/api/accounts'
      ? Response.json({ errorCode: 'auth.token_expired' }, { status: 401 })
      : undefined)
    const client = showApp()
    await logoutButton()
    client.setQueryData(queryKeys.accounts(), [])

    await act(() => getJson('/api/accounts').catch(() => undefined))

    expect(await loginForm()).toBeInTheDocument()
    expect(client.getQueryCache().getAll()).toHaveLength(0)
  })
})

describe('useAuth', () => {
  it('names the mistake when used outside AuthProvider', () => {
    // React reports the error it rethrows; the test expects it.
    vi.spyOn(console, 'error').mockImplementation(() => undefined)

    expect(() => renderHook(() => useAuth())).toThrow('useAuth() works only inside <AuthProvider>.')
  })
})
