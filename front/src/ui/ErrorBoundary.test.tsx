import { render, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { ErrorBoundary } from './ErrorBoundary'

function Page({ fails }: { fails: boolean }) {
  if (fails) {
    throw new Error('The page broke.')
  }

  return <p>The page</p>
}

// React writes a caught error to the console; here it is expected.
beforeEach(() => { vi.spyOn(console, 'error').mockImplementation(() => undefined) })

afterEach(() => { vi.restoreAllMocks() })

describe('ErrorBoundary', () => {
  it('shows a message in place of a page that fails to draw', () => {
    render(<ErrorBoundary resetKey="/a"><Page fails /></ErrorBoundary>)

    expect(screen.getByRole('alert')).toHaveTextContent(i18n.t('app.page_failed_title'))
  })

  it('keeps the message while the address stays the same', () => {
    const { rerender } = render(<ErrorBoundary resetKey="/a"><Page fails /></ErrorBoundary>)

    rerender(<ErrorBoundary resetKey="/a"><Page fails={false} /></ErrorBoundary>)

    expect(screen.getByRole('alert')).toBeInTheDocument()
  })

  // The menu still leads somewhere.
  it('shows the next page once another address is opened', () => {
    const { rerender } = render(<ErrorBoundary resetKey="/a"><Page fails /></ErrorBoundary>)

    rerender(<ErrorBoundary resetKey="/b"><Page fails={false} /></ErrorBoundary>)

    expect(screen.getByText('The page')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })
})
