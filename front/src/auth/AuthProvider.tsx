import { useQueryClient } from '@tanstack/react-query'
import * as React from 'react'
import * as api from '../api'
import i18n from '../i18n'
import { errorMessage } from '../messages'
import { AuthContext } from './AuthContext'

// Keeps the login state for the whole app: asks the API on start and changes it on login and logout.
export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [checking, setChecking] = React.useState(true)
  const [email, setEmail] = React.useState<string | null>(null)
  const queryClient = useQueryClient()

  React.useEffect(() => {
    // Logged out: nothing read while logged in may stay in memory.
    api.setSessionEndHandler(() => {
      setEmail(null)
      queryClient.clear()
    })
    api.currentUser()
      .then(setEmail)
      // The server does not answer: show the login form, logging in will say what is wrong.
      .catch(() => setEmail(null))
      .finally(() => setChecking(false))
  }, [queryClient])

  async function login(loginEmail: string, password: string): Promise<string | null> {
    const error = await api.login(loginEmail, password)
    if (error !== null) {
      return errorMessage(error, i18n.t('app.login_failed'))
    }
    setEmail(await api.currentUser())

    return null
  }

  async function logout(): Promise<void> {
    await api.logout()
    setEmail(null)
    queryClient.clear()
  }

  return <AuthContext value={{ checking, email, login, logout }}>{children}</AuthContext>
}
