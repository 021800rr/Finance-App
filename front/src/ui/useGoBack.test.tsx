import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { describe, expect, it } from 'vitest'
import { useGoBack } from './useGoBack'

function Edit() {
  const goBack = useGoBack('/home')

  return <button type="button" onClick={goBack}>Cancel</button>
}

// The edit page, opened last of the addresses of the tab.
function show(addresses: string[]) {
  render(
    <MemoryRouter initialEntries={addresses} initialIndex={addresses.length - 1}>
      <Routes>
        <Route path="edit" element={<Edit />} />
        <Route path="list" element={<p>List</p>} />
        <Route path="home" element={<p>Home</p>} />
      </Routes>
    </MemoryRouter>,
  )
}

describe('useGoBack', () => {
  it('goes back where the user came from', async () => {
    show(['/list', '/edit'])

    await userEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.getByText('List')).toBeInTheDocument()
  })

  // Opened from a bookmark or a typed address: going back would leave the app.
  it('goes to the fallback from the first page of the tab', async () => {
    show(['/edit'])

    await userEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.getByText('Home')).toBeInTheDocument()
  })
})
