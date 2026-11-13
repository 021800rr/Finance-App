import * as React from 'react'

export type Auth = {
  // true until the API has said, on start, whether the cookies still hold a login
  checking: boolean
  // the logged-in user's email; null when logged out
  email: string | null
  // null on success, otherwise the message to show
  login: (email: string, password: string) => Promise<string | null>
  logout: () => Promise<void>
}

export const AuthContext = React.createContext<Auth | null>(null)

// The login state, for any component inside <AuthProvider>.
export function useAuth(): Auth {
  const auth = React.useContext(AuthContext)
  if (auth === null) {
    throw new Error('useAuth() works only inside <AuthProvider>.')
  }

  return auth
}
