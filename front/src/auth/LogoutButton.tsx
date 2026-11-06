import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { useAuth } from './AuthContext'

export function LogoutButton() {
  const { t } = useTranslation()
  const { logout } = useAuth()
  const [failed, setFailed] = React.useState(false)

  async function handleClick() {
    try {
      await logout()
    } catch {
      // The server did not get the logout, so the cookies still work: stay logged in and say so.
      setFailed(true)
    }
  }

  return (
    <>
      {failed && <span role="alert" className="text-red-600">{t('app.server_down')}</span>}
      <button
        type="button"
        onClick={handleClick}
        className="cursor-pointer rounded-md border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50"
      >
        {t('nav.logout')}
      </button>
    </>
  )
}
