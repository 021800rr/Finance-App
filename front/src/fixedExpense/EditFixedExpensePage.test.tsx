import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { type Answer, contractorSearch, fakeApi, sentJson } from '../test/fakeApi'
import { EditFixedExpensePage } from './EditFixedExpensePage'

const NETFLIX = {
  id: 7, contractorId: 4, contractorName: 'Netflix', day: 15, amount: '-49.99', currencyCode: 'PLN',
  description: 'Plan standard',
}

// The API of the page: fixed expense 7, and the contractors whose name contains the text typed. (An unchanged
// contractor staying out of the changes: formRequests.test.tsx.)
function serve(answer?: Answer) {
  return fakeApi({ '/api/fixed_expenses/7': NETFLIX }, contractorSearch([{ id: 5, name: 'Netflix Premium' }]), answer)
}

// The edit of fixed expense 7; the list stands for where the page goes back to.
function showPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/fixed-expense/7/edit']}>
        <Routes>
          <Route path="fixed-expense/:id/edit" element={<EditFixedExpensePage />} />
          <Route path="fixed-expense" element={<p>List of the fixed expenses</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

const saveButton = () => screen.findByRole('button', { name: i18n.t('form.save') })

afterEach(() => { vi.unstubAllGlobals() })

describe('EditFixedExpensePage', () => {
  it('sends the contractor when it changes, and goes back to the list', async () => {
    const user = userEvent.setup()
    const fetch = serve((method, path) => method === 'PATCH' && path === '/api/fixed_expenses/7'
      ? new Response(null, { status: 204 })
      : undefined)
    showPage()
    const contractor = await screen.findByRole('combobox')
    await user.type(contractor, ' Premium')
    await user.click(await screen.findByRole('option', { name: 'Netflix Premium' }))

    await user.click(await saveButton())

    expect(await screen.findByText('List of the fixed expenses')).toBeInTheDocument()
    expect(sentJson(fetch, 'PATCH'))
      .toEqual({ contractorId: 5, day: 15, amount: '-49.99', description: 'Plan standard' })
  })

  it.each([
    ['day', 'form.day', 'fixed_expense.day_required'],
    ['contractorId', 'form.contractor', 'contractor.not_found'],
    ['contractorName', 'form.contractor', 'contractor.name_not_blank'],
    ['amount', 'form.amount', 'fixed_expense.amount_not_blank'],
    ['description', 'form.description', 'common.description_max_length'],
  ])('shows the refusal of %s under its own field, and stays on the form', async (propertyPath, label, code) => {
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
    expect(screen.queryByText('List of the fixed expenses')).not.toBeInTheDocument()
  })

  it('goes back to the list without saving', async () => {
    const user = userEvent.setup()
    const fetch = serve()
    showPage()

    await user.click(await screen.findByRole('button', { name: i18n.t('form.cancel') }))

    expect(await screen.findByText('List of the fixed expenses')).toBeInTheDocument()
    expect(fetch).not.toHaveBeenCalledWith(expect.anything(), expect.objectContaining({ method: 'PATCH' }))
  })

  it('shows the page of a missing address for a fixed expense that does not exist', async () => {
    serve((method, path) => method === 'GET' && path === '/api/fixed_expenses/7'
      ? Response.json({ errorCode: 'fixed_expense.not_found' }, { status: 404 })
      : undefined)
    showPage()

    expect(await screen.findByText(i18n.t('error.not_found_title'))).toBeInTheDocument()
  })
})
