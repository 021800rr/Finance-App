import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router'
import userEvent from '@testing-library/user-event'
import { afterEach, expect, it, vi } from 'vitest'
import { queryKeys } from '../queryKeys'
import { AccountOperationsPage } from './AccountOperationsPage'

const account = {
  id: 1, bankName: 'Bank', typeName: 'ROR', currencyCode: 'PLN', accountNumber: '123', hidden: false,
  saldo: { amount: '100.00', currency: 'PLN' }, saldoToday: { amount: '100.00', currency: 'PLN' },
  hasFutureOperations: false,
}
const operation = {
  id: 7, accountId: 1, contractorId: 4, contractorName: 'Shop', operationDate: '2026-11-10',
  amount: '-10.00', currencyCode: 'PLN', confirmed: false, description: 'Old description',
}

afterEach(() => { vi.unstubAllGlobals() })

it('waits for the new history and balance after saving instead of highlighting a cached old row', async () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  client.setQueryData(queryKeys.account('1'), account)
  client.setQueryData(queryKeys.accountOperations('1', 1), { items: [operation], totalItems: 1, lastPage: 1 })
  let resolveAccount!: (value: Response) => void
  let resolveOperations!: (value: Response) => void
  const accountResponse = new Promise<Response>((done) => { resolveAccount = done })
  const operationsResponse = new Promise<Response>((done) => { resolveOperations = done })
  vi.stubGlobal('fetch', vi.fn((path: string) => path.endsWith('/operations?page=1')
    ? operationsResponse : accountResponse))
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/account/1/operations?highlight=7']}>
        <Routes>
          <Route path="account/:id/operations" element={<AccountOperationsPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )

  expect(screen.queryByText('Old description')).not.toBeInTheDocument()
  expect(screen.queryByText('100,00')).not.toBeInTheDocument()
  await act(async () => {
    resolveAccount(Response.json({ ...account, saldo: { amount: '90.00', currency: 'PLN' } }))
    resolveOperations(Response.json({ member: [{ ...operation, description: 'New description' }], totalItems: 1 }))
    await Promise.all([accountResponse, operationsResponse])
  })

  expect(await screen.findByText('New description')).toBeInTheDocument()
  expect(screen.getByText('90,00', { exact: false })).toBeInTheDocument()
  expect(screen.queryByText('Old description')).not.toBeInTheDocument()
})

it.each([1, 20])('opens the other side on page %s and highlights it', async (page) => {
  const source = { ...operation, id: 52, accountId: 2, transactionUuid: 'transfer-uuid', description: 'Source' }
  const target = { ...operation, id: 51, accountId: 1, transactionUuid: 'transfer-uuid', description: 'Target' }
  const fetch = vi.fn((path: string) => {
    if (path === '/api/account_operations/52/other-side') {
      return Promise.resolve(Response.json({ ...target, accountHistoryPage: page }))
    }
    if (path === '/api/accounts/2/operations?page=1') {
      return Promise.resolve(Response.json({ member: [source], totalItems: 1 }))
    }
    if (path === `/api/accounts/1/operations?page=${page}`) {
      return Promise.resolve(Response.json({
        member: [target], totalItems: 400, view: { last: '/api/accounts/1/operations?page=20' },
      }))
    }
    if (path === '/api/accounts/2' || path === '/api/accounts/1') {
      return Promise.resolve(Response.json({ ...account, id: path.endsWith('/2') ? 2 : 1 }))
    }
    throw new Error(`Unexpected request: ${path}`)
  })
  vi.stubGlobal('fetch', fetch)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  function Page() {
    const location = useLocation()

    return <>
      <output>{location.pathname}{location.search}</output>
      <AccountOperationsPage />
    </>
  }
  const { unmount } = render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/account/2/operations']}>
        <Routes>
          <Route path="account/:id/operations" element={<Page />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )

  await userEvent.click(await screen.findByRole('button', { name: 'Pokaż drugą stronę transakcji' }))

  expect(await screen.findByText('Target')).toBeInTheDocument()
  expect(screen.getByRole('status')).toHaveTextContent(`/account/1/operations?page=${page}&highlight=51`)
  expect(screen.getByText('Target').closest('tr')).toHaveClass('[&>td]:border-blue-500')
  expect(screen.queryByText('Source')).not.toBeInTheDocument()
  expect(fetch).toHaveBeenCalledWith(`/api/accounts/1/operations?page=${page}`, expect.anything())
  if (page !== 1) {
    expect(fetch).not.toHaveBeenCalledWith('/api/accounts/1/operations?page=1', expect.anything())
  }
  unmount()
  client.clear()
})

it('keeps the source page and reports a genuinely missing other side', async () => {
  const source = { ...operation, id: 52, accountId: 2, transactionUuid: 'transfer-uuid', description: 'Source' }
  vi.stubGlobal('fetch', vi.fn((path: string) => {
    if (path === '/api/account_operations/52/other-side') {
      return Promise.resolve(Response.json({ errorCode: 'account_operation.other_side_not_found' }, { status: 404 }))
    }
    if (path.endsWith('/operations?page=3')) {
      return Promise.resolve(Response.json({
        member: [source], totalItems: 60, view: { last: '/api/accounts/2/operations?page=3' },
      }))
    }
    return Promise.resolve(Response.json({ ...account, id: 2 }))
  }))
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const { unmount } = render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/account/2/operations?page=3']}>
        <Routes>
          <Route path="account/:id/operations" element={<AccountOperationsPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )

  await userEvent.click(await screen.findByRole('button', { name: 'Pokaż drugą stronę transakcji' }))

  expect(await screen.findByRole('alert')).toHaveTextContent(/drugiej|powiązanej/)
  // The error can render before React Router commits the highlight parameter.
  await waitFor(() => {
    expect(screen.getByText('Source').closest('tr')).toHaveClass('[&>td]:border-blue-500')
    expect(screen.getByLabelText('Strona 3')).toHaveAttribute('aria-current', 'page')
  })
  unmount()
  client.clear()
})
