import { act, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router'
import { describe, expect, it } from 'vitest'
import { AuthContext } from '../auth/AuthContext'
import i18n from '../i18n'
import { Layout } from './Layout'

// Every page says its address, so a test sees where a link or the search led.
function Page() {
  const location = useLocation()

  return <p>Page {location.pathname}{location.search}</p>
}

function showLayout(address = '/') {
  render(
    <AuthContext value={{ checking: false, email: 'user@example.com', login: () => Promise.resolve(null),
      logout: () => Promise.resolve() }}>
      <MemoryRouter initialEntries={[address]}>
        <Routes>
          <Route element={<Layout />}>
            <Route path="*" element={<Page />} />
          </Route>
        </Routes>
      </MemoryRouter>
    </AuthContext>,
  )
}

const toggle = () => screen.getByRole('button', { name: /menu/i })

// The links, the search, the language and the logout: shown on a narrow screen only while open.
function menu() {
  const menuId = toggle().getAttribute('aria-controls')

  return document.getElementById(menuId ?? '')
}

const search = () => screen.getByRole('textbox', { name: i18n.t('nav.search') })

describe('Layout', () => {
  it('opens and closes the menu with its button', async () => {
    const user = userEvent.setup()
    showLayout()
    expect(toggle()).toHaveAccessibleName(i18n.t('nav.open_menu'))
    expect(menu()).toHaveClass('hidden')

    await user.click(toggle())

    expect(toggle()).toHaveAttribute('aria-expanded', 'true')
    expect(toggle()).toHaveAccessibleName(i18n.t('nav.close_menu'))
    expect(menu()).toHaveClass('flex')

    await user.click(toggle())

    expect(toggle()).toHaveAttribute('aria-expanded', 'false')
    expect(menu()).toHaveClass('hidden')
  })

  it('closes the menu with Escape, the focus going back to its button', async () => {
    const user = userEvent.setup()
    showLayout()
    await user.click(toggle())
    act(() => { screen.getByRole('link', { name: i18n.t('nav.transfer') }).focus() })

    await user.keyboard('{Escape}')

    expect(menu()).toHaveClass('hidden')
    expect(toggle()).toHaveFocus()
  })

  it('closes the menu once one of its pages opens, and marks that page', async () => {
    const user = userEvent.setup()
    showLayout()
    await user.click(toggle())

    await user.click(screen.getByRole('link', { name: i18n.t('nav.transfer') }))

    expect(screen.getByText('Page /transfer/new')).toBeInTheDocument()
    expect(menu()).toHaveClass('hidden')
    expect(screen.getByRole('link', { name: i18n.t('nav.transfer') })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('link', { name: i18n.t('nav.rates') })).not.toHaveAttribute('aria-current')
  })

  it('opens the results of the phrase typed in the header, the phrase staying in the field', async () => {
    const user = userEvent.setup()
    showLayout()

    await user.type(search(), '  rent {Enter}')

    expect(screen.getByText('Page /search?q=rent')).toBeInTheDocument()
    expect(search()).toHaveValue('rent')
  })

  it('empties the search field once another page opens', async () => {
    const user = userEvent.setup()
    showLayout('/search?q=rent')
    expect(search()).toHaveValue('rent')

    await user.click(screen.getByRole('link', { name: i18n.t('nav.new_operation') }))

    expect(search()).toHaveValue('')
  })
})
