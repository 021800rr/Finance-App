import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { LanguageSwitcher } from '../layout/LanguageSwitcher'
import { inputClass } from '../ui/form'
import { useAuth } from './AuthContext'

export function LoginForm() {
  const { t } = useTranslation()
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
      const email = form.get('email')
      const password = form.get('password')
      const error = await login(
        typeof email === 'string' ? email : '',
        typeof password === 'string' ? password : '',
      )
      setMessage(error ?? '')
    } catch {
      setMessage(t('app.server_down'))
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-100 dark:bg-slate-950 px-4">
      <form
        onSubmit={(event) => { void handleSubmit(event) }}
        className="w-full max-w-sm space-y-4 rounded-xl bg-white dark:bg-slate-900 p-8 shadow"
      >
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-semibold text-slate-900 dark:text-slate-100">Finance</h1>
          <LanguageSwitcher />
        </div>
        <label className="block">
          <span className="text-sm font-medium text-slate-700 dark:text-slate-300">{t('login.email')}</span>
          <input name="email" type="email" autoComplete="username" required
            className={`mt-1 ${inputClass(undefined)}`} />
        </label>
        <label className="block">
          <span className="text-sm font-medium text-slate-700 dark:text-slate-300">{t('login.password')}</span>
          <input name="password" type="password" autoComplete="current-password" required
            className={`mt-1 ${inputClass(undefined)}`} />
        </label>
        <button
          type="submit"
          disabled={sending}
          className="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700
            disabled:opacity-50"
        >
          {sending ? t('app.logging_in') : t('login.submit')}
        </button>
        {message && <p role="alert" className="text-sm text-red-600 dark:text-red-400">{message}</p>}
      </form>
    </div>
  )
}
