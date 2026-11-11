import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { useAuth } from './AuthContext'

export function LogoutButton() {
  const { t } = useTranslation()
  const { logout } = useAuth()
  const [failed, setFailed] = React.useState(false)
  // true while the logout is on its way: one click sends one logout
  const [pending, setPending] = React.useState(false)

  async function handleClick() {
    setPending(true)
    setFailed(false)
    try {
      await logout()
    } catch {
      // The server did not get the logout, so the cookies still work: stay logged in and say so.
      setFailed(true)
    } finally {
      setPending(false)
    }
  }

  return (
    <>
      {failed && <span role="alert" className="text-red-600 dark:text-red-400">{t('app.server_down')}</span>}
      <button
        type="button"
        onClick={() => { void handleClick() }}
        disabled={pending}
        className="cursor-pointer rounded-md border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-slate-700
          dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:cursor-wait disabled:opacity-50"
      >
        {t('nav.logout')}
      </button>
    </>
  )
}
