import * as React from 'react'
import { SERVER_DOWN } from '../messages'
import { useAuth } from './AuthContext'

const INPUT = 'mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 '
  + 'focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500'

export function LoginForm() {
  const { login } = useAuth()
  const [message, setMessage] = React.useState('')
  // true while the login is on its way: the button waits, so one click sends one login
  const [sending, setSending] = React.useState(false)

  async function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    // Without this the browser would send the form itself and reload the page.
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setSending(true)
    try {
      const error = await login(String(form.get('email') ?? ''), String(form.get('password') ?? ''))
      setMessage(error ?? '')
    } catch {
      setMessage(SERVER_DOWN)
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-100 px-4">
      <form onSubmit={handleSubmit} className="w-full max-w-sm space-y-4 rounded-xl bg-white p-8 shadow">
        <h1 className="text-2xl font-semibold text-slate-900">Finance</h1>
        <label className="block">
          <span className="text-sm font-medium text-slate-700">E-mail</span>
          <input name="email" type="email" autoComplete="username" required className={INPUT} />
        </label>
        <label className="block">
          <span className="text-sm font-medium text-slate-700">Hasło</span>
          <input name="password" type="password" autoComplete="current-password" required className={INPUT} />
        </label>
        <button
          type="submit"
          disabled={sending}
          className="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700
            disabled:opacity-50"
        >
          {sending ? 'Loguję…' : 'Zaloguj'}
        </button>
        {message && <p role="alert" className="text-sm text-red-600">{message}</p>}
      </form>
    </div>
  )
}
