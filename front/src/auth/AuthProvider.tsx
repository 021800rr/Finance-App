import * as React from 'react'
import * as api from '../api'
import { AuthContext } from './AuthContext'

// Keeps the login state for the whole app: asks the API on start and changes it on login and logout.
export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [checking, setChecking] = React.useState(true)
  const [email, setEmail] = React.useState<string | null>(null)

  React.useEffect(() => {
    api.currentUser()
      .then(setEmail)
      // The server does not answer: show the login form, logging in will say what is wrong.
      .catch(() => setEmail(null))
      .finally(() => setChecking(false))
  }, [])

  async function login(loginEmail: string, password: string): Promise<string | null> {
    const error = await api.login(loginEmail, password)
    if (error === null) {
      setEmail(await api.currentUser())
    }

    return error
  }

  async function logout(): Promise<void> {
    await api.logout()
    setEmail(null)
  }

  return <AuthContext value={{ checking, email, login, logout }}>{children}</AuthContext>
}
