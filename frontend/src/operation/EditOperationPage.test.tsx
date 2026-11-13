import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { type Answer, fakeApi, sentJson } from '../test/fakeApi'
import { EditOperationPage } from './EditOperationPage'

const account = (id: number, hidden = false) => ({
  id, hidden, currencyCode: 'PLN', accountNumber: `account-${id}`, bankName: 'Bank', typeName: 'ROR',
  householdName: 'Home', displayLabel: `Account ${id}`, isMain: id === 1,
})

// Operation 7 of account 2, which has been hidden since. (An unchanged contractor staying out of the changes:
// formRequests.test.tsx.)
const OPERATION = {
  id: 7, accountId: 2, contractorId: 4, contractorName: 'Shop', operationDate: '2026-11-10', amount: '-10.00',
  currencyCode: 'PLN', confirmed: false, description: 'Bread',
}

function serve(answer?: Answer) {
  return fakeApi({ '/api/account_operations/7': OPERATION, '/api/accounts': [account(1), account(2, true)] }, answer)
}

// Every other page says its address, so a test sees where the form led.
function Elsewhere() {
  const location = useLocation()

  return <p>Page {location.pathname}{location.search}</p>
}

function showPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/operation/7/edit']}>
        <Routes>
          <Route path="operation/:id/edit" element={<EditOperationPage />} />
          <Route path="*" element={<Elsewhere />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

const saveButton = () => screen.findByRole('button', { name: i18n.t('form.save') })

afterEach(() => { vi.unstubAllGlobals() })

describe('EditOperationPage', () => {
  it('shows the account of the operation only, fixed, though hidden since', async () => {
    serve()
    showPage()

    const accounts = await screen.findByLabelText(i18n.t('form.account'))
    expect(within(accounts).getAllByRole('option').map((option) => option.textContent)).toEqual(['Account 2'])
    expect(accounts).toBeDisabled()
  })

  it('saves the changes, then shows the operation highlighted in the history of its account', async () => {
    const user = userEvent.setup()
    const fetch = serve((method) => method === 'PATCH' ? new Response(null, { status: 204 }) : undefined)
    showPage()
    await user.click(await screen.findByLabelText(i18n.t('form.confirmed')))

    await user.click(await saveButton())

    expect(await screen.findByText('Page /account/2/operations?highlight=7')).toBeInTheDocument()
    expect(sentJson(fetch, 'PATCH', '/api/account_operations/7')).toMatchObject({ confirmed: true })
  })

  it.each([
    ['contractorId', 'form.contractor', 'contractor.not_found'],
    ['contractorName', 'form.contractor', 'contractor.name_not_blank'],
    ['operationDate', 'form.date', 'operation.date_not_blank'],
    ['amount', 'form.amount', 'operation.amount_not_blank'],
    ['description', 'form.description', 'common.description_max_length'],
  ])('shows the refusal of %s under its own field', async (propertyPath, label, code) => {
    const user = userEvent.setup()
    serve((method) => method === 'PATCH'
      ? Response.json({
        errorCode: 'validation.failed', violations: [{ propertyPath, message: 'Refused.', code, params: {} }],
      }, { status: 422 })
      : undefined)
    showPage()

    await user.click(await saveButton())

    const field = screen.getByLabelText(i18n.t(label))
    await waitFor(() => { expect(field).toHaveAccessibleDescription(i18n.t(code, { ns: 'validators' })) })
  })

  it('goes back without saving, opened first in the tab, to the history of its account', async () => {
    const user = userEvent.setup()
    const fetch = serve()
    showPage()

    await user.click(await screen.findByRole('button', { name: i18n.t('form.cancel') }))

    expect(screen.getByText('Page /account/2/operations')).toBeInTheDocument()
    expect(fetch).not.toHaveBeenCalledWith(expect.anything(), expect.objectContaining({ method: 'PATCH' }))
  })
})
