import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ReactNode } from 'react'
import { MemoryRouter, Route, Routes } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { EditFixedExpensePage } from './fixedExpense/EditFixedExpensePage'
import { EditOperationPage } from './operation/EditOperationPage'
import { TransferPage } from './transfer/TransferPage'

const account = (id: number, currencyCode = 'PLN') => ({
  id, currencyCode, accountNumber: `account-${id}`, bankName: 'Bank', typeName: 'ROR',
  householdName: 'Home', displayLabel: `Account ${id}`, hidden: false, isMain: id === 1,
})
const operation = {
  id: 7, accountId: 1, contractorId: 4, contractorName: 'Shop', operationDate: '2026-11-10',
  amount: '-10.00', currencyCode: 'PLN', confirmed: false,
}

function show(path: string, route: string, element: ReactNode) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route path={route} element={element} />
          <Route path="*" element={<p>Saved</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

afterEach(() => { vi.unstubAllGlobals() })

describe('form request bodies', () => {
  it.each(['operation', 'fixed-expense'])('omits an unchanged contractor when editing %s', async (kind) => {
    const fetch = vi.fn((path: string, init?: RequestInit) => {
      if (init?.method === 'PATCH') {
        return Promise.resolve(new Response(null, { status: 204 }))
      }
      const data = path === '/api/accounts' ? [account(1)]
        : path.startsWith('/api/contractors') ? [{ id: 4, name: 'Shop' }]
        : { ...operation, day: 10 }
      return Promise.resolve(Response.json(data))
    })
    vi.stubGlobal('fetch', fetch)
    show(`/${kind}/7/edit`, `/${kind}/:id/edit`,
      kind === 'operation' ? <EditOperationPage /> : <EditFixedExpensePage />)
    const save = await screen.findByRole('button', { name: 'Zapisz' })

    await userEvent.click(save)

    expect(await screen.findByText('Saved')).toBeInTheDocument()
    const request = fetch.mock.calls.find(([, init]) => init?.method === 'PATCH')?.[1]
    expect(request?.body).toBeDefined()
    expect(JSON.parse(typeof request?.body === 'string' ? request.body : '')).toEqual({
      ...(kind === 'operation' ? { operationDate: '2026-11-10', confirmed: false } : { day: 10 }),
      amount: '-10.00', description: null,
    })
  })

  it.each(['PLN', 'EUR'])('sends amountTo only for a transfer to another currency: %s', async (currency) => {
    const fetch = vi.fn((path: string, init?: RequestInit) => Promise.resolve(Response.json(
      init?.method === 'POST'
        ? { fromAccountId: 1, outOperationId: 7 }
        : path === '/api/accounts' ? [account(1), account(2, currency)] : [],
    )))
    vi.stubGlobal('fetch', fetch)
    show('/transfer/new', '/transfer/new', <TransferPage />)
    const debit = await screen.findByLabelText('Obciążenie')
    fireEvent.change(debit, { target: { value: '20,00' } })
    if (currency === 'EUR') {
      fireEvent.change(screen.getByLabelText('Uznanie'), { target: { value: '4,50' } })
    }

    await userEvent.click(screen.getByRole('button', { name: 'Zapisz' }))

    await waitFor(() => { expect(screen.getByText('Saved')).toBeInTheDocument() })
    const request = fetch.mock.calls.find(([, init]) => init?.method === 'POST')?.[1]
    expect(JSON.parse(typeof request?.body === 'string' ? request.body : '')).toMatchObject({ amountFrom: '20.00' })
    if (currency === 'EUR') {
      expect(JSON.parse(typeof request?.body === 'string' ? request.body : '')).toHaveProperty('amountTo', '4.50')
    } else {
      expect(JSON.parse(typeof request?.body === 'string' ? request.body : '')).not.toHaveProperty('amountTo')
    }
  })
})
