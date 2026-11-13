import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import { AuthContext } from './auth/AuthContext'
import i18n from './i18n'

// Every address with the text only its own page shows when the API cannot be read (500 here): proof that the page
// loaded on demand arrived and is the right one.
const PAGES: [string, string][] = [
  ['/', 'app.summary_load_failed'],
  ['/account/1/operations', 'app.account_load_failed'],
  ['/operation/new', 'app.accounts_load_failed'],
  ['/operation/1/edit', 'app.operation_load_failed'],
  ['/transfer/new', 'app.accounts_load_failed'],
  ['/search?q=rent', 'app.operations_load_failed'],
  ['/fixed-expense', 'app.fixed_expenses_load_failed'],
  ['/fixed-expense/1/edit', 'app.fixed_expense_load_failed'],
  ['/rate', 'app.rate_form_load_failed'],
  ['/gold-assets', 'app.gold_assets_load_failed'],
  ['/gold-assets/1/edit', 'app.gold_asset_load_failed'],
  ['/gold-prices', 'app.gold_assets_load_failed'],
  ['/no-such-page', 'error.not_found_title'],
]

afterEach(() => { vi.unstubAllGlobals() })

describe('App', () => {
  it.each(PAGES)('shows the page of %s', async (address, text) => {
    vi.stubGlobal('fetch', vi.fn(() => Promise.resolve(new Response('{}', { status: 500 }))))
    render(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <AuthContext value={{ checking: false, email: 'user@example.com', login: () => Promise.resolve(null),
          logout: () => Promise.resolve() }}>
          <MemoryRouter initialEntries={[address]}>
            <App />
          </MemoryRouter>
        </AuthContext>
      </QueryClientProvider>,
    )

    expect((await screen.findAllByText(i18n.t(text), {}, { timeout: 4000 })).length).toBeGreaterThan(0)
    expect(screen.queryByText(i18n.t('app.page_failed_title'))).toBeNull()
  })
})
