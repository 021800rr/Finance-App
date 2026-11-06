import { useAuth } from './auth/AuthContext'
import { LoginForm } from './auth/LoginForm'
import { LogoutButton } from './auth/LogoutButton'

// The login form until the user is logged in, the app afterwards.
function App() {
  const { checking, email } = useAuth()

  if (checking) {
    return <p>Sprawdzam logowanie…</p>
  }
  if (email === null) {
    return <LoginForm />
  }

  return (
    <>
      <header>
        <span>{email}</span>
        <LogoutButton />
      </header>
      <main>
        <p>Tu będą konta.</p>
      </main>
    </>
  )
}

export default App
