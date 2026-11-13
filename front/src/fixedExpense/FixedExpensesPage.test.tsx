import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useParams } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { formatAmount } from '../money'
import { type Answer, contractorSearch, fakeApi, sentJson } from '../test/fakeApi'
import { FixedExpensesPage } from './FixedExpensesPage'

type FixedExpense = {
  id: number; contractorId: number; contractorName: string; day: number; amount: string; currencyCode: string
  description?: string
}

const NETFLIX: FixedExpense = {
  id: 7, contractorId: 4, contractorName: 'Netflix', day: 15, amount: '-49.99', currencyCode: 'PLN',
  description: 'Plan standard',
}

const CONTRACTORS = [{ id: 4, name: 'Netflix' }]

// The API of the page: the fixed expenses, and the contractors whose name contains the text typed. `answer` replies
// to anything else (saving, planning, deleting).
function serve(fixedExpenses: FixedExpense[], answer?: Answer) {
  return fakeApi({ '/api/fixed_expenses': fixedExpenses }, contractorSearch(CONTRACTORS), answer)
}

// Where planning leads: the history of the main account.
function History() {
  return <p>History of account {useParams().id}</p>
}

function showPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/fixed-expense']}>
        <Routes>
          <Route path="fixed-expense" element={<FixedExpensesPage />} />
          <Route path="account/:id/operations" element={<History />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

const field = (label: string) => screen.getByLabelText(i18n.t(label))

async function pickContractor(text: string, option: string) {
  const user = userEvent.setup()
  await user.type(screen.getByRole('combobox'), text)
  await user.click(await screen.findByRole('option', { name: option }))
}

async function confirmIn(dialogButton: string) {
  const user = userEvent.setup()
  await user.click(await screen.findByRole('button', { name: i18n.t('fixed_expense.plan_button') }))
  await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: i18n.t(dialogButton) }))
}

afterEach(() => { vi.unstubAllGlobals() })

describe('FixedExpensesPage', () => {
  it('lists the fixed expenses, each with a link to its edit', async () => {
    serve([NETFLIX])
    showPage()

    const rows = within(await screen.findByRole('table')).getAllByRole('row').slice(1)
    expect(rows.map((row) => within(row).getAllByRole('cell').slice(0, 5).map((cell) => cell.textContent)))
      .toEqual([['7', '15', 'Netflix', 'Plan standard', formatAmount('-49.99')]])
    expect(screen.getByRole('link', { name: i18n.t('operation.edit') }))
      .toHaveAttribute('href', '/fixed-expense/7/edit')
  })

  it('says there are no fixed expenses yet', async () => {
    serve([])
    showPage()

    expect(await screen.findByText(i18n.t('fixed_expense.none_found'))).toBeInTheDocument()
  })

  it('adds one with a contractor from the list, and starts a new, empty form', async () => {
    const user = userEvent.setup()
    const fetch = serve([], (method, path) => method === 'POST' && path === '/api/fixed_expenses'
      ? Response.json(NETFLIX, { status: 201 })
      : undefined)
    showPage()
    // the day takes digits only
    await user.type(await screen.findByLabelText(i18n.t('form.day')), '1a5')
    expect(field('form.day')).toHaveValue('15')
    await pickContractor('Netf', 'Netflix')
    fireEvent.change(field('form.amount'), { target: { value: '-49,99' } })
    await user.type(field('form.description'), 'Plan standard')

    await user.click(screen.getByRole('button', { name: i18n.t('fixed_expense.add') }))

    await waitFor(() => { expect(field('form.day')).toHaveValue('') })
    expect(screen.getByRole('combobox')).toHaveValue('')
    expect(sentJson(fetch, 'POST'))
      .toEqual({ contractorId: 4, day: 15, amount: '-49.99', description: 'Plan standard' })
  })

  it('adds one with a new contractor by its name, and no description as null', async () => {
    const user = userEvent.setup()
    const fetch = serve([], (method, path) => method === 'POST' && path === '/api/fixed_expenses'
      ? Response.json({ ...NETFLIX, contractorId: 5, contractorName: 'Spotify' }, { status: 201 })
      : undefined)
    showPage()
    await user.type(await screen.findByLabelText(i18n.t('form.day')), '1')
    await pickContractor('Spotify', i18n.t('contractor_autocomplete.add', { name: 'Spotify' }))
    fireEvent.change(field('form.amount'), { target: { value: '-19,99' } })
    await user.type(field('form.description'), '   ')

    await user.click(screen.getByRole('button', { name: i18n.t('fixed_expense.add') }))

    await waitFor(() => { expect(field('form.day')).toHaveValue('') })
    expect(sentJson(fetch, 'POST'))
      .toEqual({ contractorName: 'Spotify', day: 1, amount: '-19.99', description: null })
  })

  // The API cannot tell a name typed but not picked: it would take it for a new contractor.
  it('asks for a contractor picked from the list, without sending anything', async () => {
    const user = userEvent.setup()
    const fetch = serve([])
    showPage()
    await user.type(await screen.findByRole('combobox'), 'Netf')

    await user.click(screen.getByRole('button', { name: i18n.t('fixed_expense.add') }))

    expect(screen.getByRole('combobox'))
      .toHaveAccessibleDescription(i18n.t('contractor.required', { ns: 'validators' }))
    expect(fetch).not.toHaveBeenCalledWith('/api/fixed_expenses', expect.objectContaining({ method: 'POST' }))
  })

  it('plans the next month once confirmed, and opens the history of the main account', async () => {
    serve([NETFLIX], (method, path) => method === 'POST' && path === '/api/fixed_expenses/plan'
      ? Response.json({ accountId: 1, month: '2026-12', createdCount: 1 }, { status: 201 })
      : undefined)
    showPage()

    await confirmIn('app.confirm')

    expect(await screen.findByText('History of account 1')).toBeInTheDocument()
  })

  it('plans nothing when the planning is cancelled', async () => {
    const fetch = serve([NETFLIX])
    showPage()

    await confirmIn('form.cancel')

    expect(screen.getByRole('dialog', { hidden: true })).not.toBeVisible()
    expect(fetch).not.toHaveBeenCalledWith('/api/fixed_expenses/plan', expect.anything())
  })

  it('says why the API refused to plan', async () => {
    const params = { contractor: 'Netflix', amount: '-49,99', date: '2026-12-15' }
    serve([NETFLIX], (method, path) => method === 'POST' && path === '/api/fixed_expenses/plan'
      ? Response.json({ errorCode: 'fixed_expense.plan_duplicate', params }, { status: 409 })
      : undefined)
    showPage()

    await confirmIn('app.confirm')

    expect(await screen.findByRole('alert')).toHaveTextContent(i18n.t('fixed_expense.plan_duplicate', params))
  })

  it('deletes a fixed expense once confirmed', async () => {
    const user = userEvent.setup()
    const fetch = serve([NETFLIX], (method) => method === 'DELETE' ? new Response(null, { status: 204 }) : undefined)
    showPage()

    await user.click(await screen.findByRole('button', { name: i18n.t('operation.delete') }))
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: i18n.t('app.confirm') }))

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith('/api/fixed_expenses/7', expect.objectContaining({ method: 'DELETE' }))
    })
  })

  it('says when a fixed expense could not be deleted', async () => {
    const user = userEvent.setup()
    serve([NETFLIX], (method) => method === 'DELETE' ? Response.json({}, { status: 500 }) : undefined)
    showPage()

    await user.click(await screen.findByRole('button', { name: i18n.t('operation.delete') }))
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: i18n.t('app.confirm') }))

    expect(await screen.findByText(i18n.t('app.delete_fixed_expense_failed'))).toBeInTheDocument()
  })
})
