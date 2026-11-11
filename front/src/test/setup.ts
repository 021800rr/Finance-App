import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach } from 'vitest'
import i18n from '../i18n'

// What runs before the tests: the matchers of jest-dom (toBeInTheDocument, toHaveValue…), and the texts in Polish,
// whatever language jsdom reports.
await i18n.changeLanguage('pl')

// Every test starts with an empty page.
afterEach(() => {
  cleanup()
})
