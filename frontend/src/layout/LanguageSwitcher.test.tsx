import { act, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, expect, it } from 'vitest'
import i18n from '../i18n'
import { LanguageSwitcher } from './LanguageSwitcher'

afterEach(async () => { await act(() => i18n.changeLanguage('pl')) })

it('offers the other language and switches to it at once, the page saying which one it is in', async () => {
  const user = userEvent.setup()
  render(<LanguageSwitcher />)

  await user.click(screen.getByRole('button', { name: 'English' }))

  expect(i18n.resolvedLanguage).toBe('en')
  expect(document.documentElement).toHaveAttribute('lang', 'en')
  const back = screen.getByRole('button', { name: 'Polski' })
  expect(back).toHaveTextContent('PL')
  expect(back).toHaveAttribute('lang', 'pl')

  await user.click(back)

  expect(i18n.resolvedLanguage).toBe('pl')
  expect(screen.getByRole('button', { name: 'English' })).toHaveTextContent('EN')
})
