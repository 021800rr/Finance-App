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
      <button type="button" onClick={handleClick}>Wyloguj</button>
      {message && <span>{message}</span>}
    </>
  )
}
