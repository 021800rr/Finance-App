import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, within } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { fakeApi } from '../test/fakeApi'
import { SearchPage } from './SearchPage'

const account = (id: number) => ({
  id, hidden: false, currencyCode: 'PLN', accountNumber: `Bank${id}-ROR`, bankName: 'Bank', typeName: 'ROR',
  householdName: 'Home', displayLabel: `Account ${id}`, isMain: id === 1,
})

const operation = (id: number, accountId: number, fields: Record<string, unknown> = {}) => ({
  id, accountId, contractorId: 4, contractorName: 'Landlord', operationDate: '2026-11-10', amount: '-1500.00',
  currencyCode: 'PLN', confirmed: true, description: 'Rent', ...fields,
})

// The API of the page: the accounts, and the results of the phrase on each page the test gives.
function serve(results: Record<string, unknown> = {}) {
  return fakeApi({ '/api/accounts': [account(1), account(2)], ...results })
}

function showPage(address: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[address]}>
        <Routes>
          <Route path="search" element={<SearchPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

afterEach(() => { vi.unstubAllGlobals() })

describe('SearchPage', () => {
  it('asks for a phrase, and asks the API for nothing without one', async () => {
    const fetch = serve()
    showPage('/search?q=%20%20')

    expect(screen.getByText(i18n.t('search.prompt'))).toBeInTheDocument()
    await vi.waitFor(() => { expect(fetch).toHaveBeenCalledWith('/api/accounts', expect.anything()) })
    expect(fetch).not.toHaveBeenCalledWith(expect.stringContaining('/api/account_operations'), expect.anything())
  })

  // As in the web app: every result names its account, and there is no 🔗 to the other side of a transfer.
  it('lists the operations found in all accounts, each with its account, and how many there are', async () => {
    serve({
      '/api/account_operations?q=rent&page=1': {
        member: [operation(7, 1), operation(8, 2, { transactionUuid: 'transfer' })], totalItems: 2,
      },
    })
    showPage('/search?q=%20rent%20')

    const rows = within(await screen.findByRole('table')).getAllByRole('row').slice(1)
    expect(rows.map((row) => within(row).getAllByRole('cell')[1]?.textContent)).toEqual(['Bank1-ROR', 'Bank2-ROR'])
    expect(screen.getByText('rent').closest('p'))
      .toHaveTextContent(`${i18n.t('search.query_label')}: rent · ${i18n.t('search.results_label')}: 2`)
    expect(screen.queryByRole('button', { name: i18n.t('operation.show_other_side') })).not.toBeInTheDocument()
  })

  it('says when nothing was found for the phrase', async () => {
    serve({ '/api/account_operations?q=rent&page=1': { member: [], totalItems: 0 } })
    showPage('/search?q=rent')

    expect(await screen.findByText(i18n.t('search.none_found_for', { query: 'rent' }))).toBeInTheDocument()
  })

  it('opens the page of the results the address asks for', async () => {
    serve({
      '/api/account_operations?q=rent&page=2': {
        member: [operation(7, 1)], totalItems: 61, view: { last: '/api/account_operations?q=rent&page=3' },
      },
    })
    showPage('/search?q=rent&page=2')

    expect(await screen.findByLabelText(i18n.t('pagination.page', { page: 2 }))).toHaveAttribute('aria-current', 'page')
    expect(screen.getByLabelText(i18n.t('pagination.page', { page: 3 }))).toBeInTheDocument()
  })
})
