import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useSearchParams } from 'react-router'
import { describe, expect, it } from 'vitest'
import i18n from '../i18n'
import { SearchBox } from './SearchBox'

// The results page says which phrase its address carries.
function Results() {
  const [params] = useSearchParams()

  return <p>Results for [{params.get('q') ?? 'no phrase'}]</p>
}

function show() {
  render(
    <MemoryRouter>
      <SearchBox initial="" />
      <Routes>
        <Route path="search" element={<Results />} />
        <Route path="*" element={null} />
      </Routes>
    </MemoryRouter>,
  )
}

describe('SearchBox', () => {
  // & and # would otherwise cut the phrase short in the address.
  it('carries a phrase with any characters to the results', async () => {
    show()

    await userEvent.type(screen.getByRole('textbox', { name: i18n.t('nav.search') }), 'R&D #2{Enter}')

    expect(screen.getByText('Results for [R&D #2]')).toBeInTheDocument()
  })

  it('opens the results page without a phrase for an empty field', async () => {
    show()

    await userEvent.click(screen.getByRole('button', { name: i18n.t('nav.search') }))

    expect(screen.getByText('Results for [no phrase]')).toBeInTheDocument()
  })
})
