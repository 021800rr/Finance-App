import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach } from 'vitest'
import i18n from '../i18n'

// What runs before the tests: the matchers of jest-dom (toBeInTheDocument, toHaveValue…), and the texts in Polish,
// whatever language jsdom reports.
await i18n.changeLanguage('pl')

// jsdom does not implement modal dialogs; the browser supplies focus trapping and the backdrop.
Object.defineProperties(HTMLDialogElement.prototype, {
  showModal: {
    configurable: true,
    value(this: HTMLDialogElement) { this.setAttribute('open', '') },
  },
  close: {
    configurable: true,
    value(this: HTMLDialogElement) { this.removeAttribute('open') },
  },
})

// Every test starts with an empty page.
afterEach(() => {
  cleanup()
})
