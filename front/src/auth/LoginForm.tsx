import * as React from 'react'
import { SERVER_DOWN } from '../messages'
import { useAuth } from './AuthContext'

export function LoginForm() {
  const { login } = useAuth()
  const [message, setMessage] = React.useState('')

  async function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    // Without this the browser would send the form itself and reload the page.
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    try {
      const error = await login(String(form.get('email') ?? ''), String(form.get('password') ?? ''))
      setMessage(error ?? '')
    } catch {
      setMessage(SERVER_DOWN)
    }
  }

  return (
    <form onSubmit={handleSubmit}>
      <label>
        E-mail
        <input name="email" type="email" autoComplete="username" required />
      </label>
      <label>
        Hasło
        <input name="password" type="password" autoComplete="current-password" required />
      </label>
      <button type="submit">Zaloguj</button>
      {message && <p>{message}</p>}
    </form>
  )
}
