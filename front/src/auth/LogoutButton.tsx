import * as React from 'react'
import { SERVER_DOWN } from '../messages'
import { useAuth } from './AuthContext'

export function LogoutButton() {
  const { logout } = useAuth()
  const [message, setMessage] = React.useState('')

  async function handleClick() {
    try {
      await logout()
    } catch {
      // The server did not get the logout, so the cookies still work: stay logged in and say so.
      setMessage(SERVER_DOWN)
    }
  }

  return (
    <>
      {message && <span role="alert" className="text-red-600">{message}</span>}
      <button
        type="button"
        onClick={handleClick}
        className="rounded-md border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50"
      >
        Wyloguj
      </button>
    </>
  )
}
