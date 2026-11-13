import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { type Answer, fakeApi, sentJson } from '../test/fakeApi'
import { today } from '../ui/form'
import { TransferPage } from './TransferPage'

const account = (id: number, currencyCode = 'PLN', hidden = false) => ({
  id, currencyCode, hidden, accountNumber: `account-${id}`, bankName: 'Bank', typeName: 'ROR', householdName: 'Home',
  displayLabel: `Account ${id} ${currencyCode}`, isMain: id === 1,
})

// Two accounts in PLN and one in EUR to offer; a hidden one the form leaves out. (Which of the amounts goes for one
// currency and for two: formRequests.test.tsx.)
const ACCOUNTS = [account(1), account(2), account(3, 'EUR'), account(4, 'PLN', true)]

function serve(accounts = ACCOUNTS, answer?: Answer) {
  return fakeApi({ '/api/accounts': accounts }, answer)
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
      <MemoryRouter initialEntries={['/transfer/new']}>
        <Routes>
          <Route path="transfer/new" element={<TransferPage />} />
          <Route path="*" element={<Elsewhere />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

// What the API answers when it refuses: field errors, or for some refusals an errorCode alone.
type Refusal = { violations?: { propertyPath: string; code: string }[]; errorCode?: string }

const field = (label: string) => screen.getByLabelText(i18n.t(label))
const save = () => userEvent.click(screen.getByRole('button', { name: i18n.t('form.save') }))

afterEach(() => { vi.unstubAllGlobals() })

describe('TransferPage', () => {
  it('offers the accounts that are not hidden, at first a different one on each side', async () => {
    serve()
    showPage()

    const from = await screen.findByLabelText(i18n.t('transfer.from_account'))
    expect(within(from).getAllByRole('option').map((option) => option.textContent))
      .toEqual(['Account 1 PLN', 'Account 2 PLN', 'Account 3 EUR'])
    expect(from).toHaveDisplayValue('Account 1 PLN')
    expect(field('transfer.to_account')).toHaveDisplayValue('Account 2 PLN')
  })

  it('transfers between the accounts chosen, then shows the operation that left highlighted', async () => {
    const user = userEvent.setup()
    const fetch = serve(ACCOUNTS, (method, path) => method === 'POST' && path === '/api/transfers'
      ? Response.json({ fromAccountId: 2, outOperationId: 31 }, { status: 201 })
      : undefined)
    showPage()
    await user.selectOptions(await screen.findByLabelText(i18n.t('transfer.from_account')), 'Account 2 PLN')
    await user.selectOptions(field('transfer.to_account'), 'Account 1 PLN')
    fireEvent.change(field('transfer.debit'), { target: { value: '20,00' } })
    await user.type(field('form.description'), 'Savings')

    await save()

    expect(await screen.findByText('Page /account/2/operations?highlight=31')).toBeInTheDocument()
    expect(sentJson(fetch, 'POST')).toEqual({
      fromAccountId: 2, toAccountId: 1, operationDate: today(), amountFrom: '20.00', description: 'Savings',
    })
  })

  // With one currency what arrives is what leaves.
  it('blocks the second amount for one currency, and gives back what was typed in it for two', async () => {
    const user = userEvent.setup()
    serve()
    showPage()
    const to = await screen.findByLabelText(i18n.t('transfer.to_account'))
    expect(field('transfer.credit')).toBeDisabled()
    expect(field('transfer.credit')).toHaveAttribute('placeholder', i18n.t('transfer.credit_same_currency'))

    await user.selectOptions(to, 'Account 3 EUR')
    fireEvent.change(field('transfer.credit'), { target: { value: '4,50' } })
    await user.selectOptions(to, 'Account 2 PLN')

    expect(field('transfer.credit')).toBeDisabled()
    expect(field('transfer.credit')).toHaveValue('')

    await user.selectOptions(to, 'Account 3 EUR')

    expect(field('transfer.credit')).toBeEnabled()
    expect(field('transfer.credit')).toHaveValue('4,50')
  })

  // The last two come as an errorCode alone, without a field error.
  it.each<[string, Refusal]>([
    ['transfer.from_account', { violations: [{ propertyPath: 'fromAccountId', code: 'account.not_found' }] }],
    ['transfer.to_account', { violations: [{ propertyPath: 'toAccountId', code: 'account.not_found' }] }],
    ['transfer.debit', { violations: [{ propertyPath: 'amountFrom', code: 'transfer.amount_from_required' }] }],
    ['transfer.credit', { violations: [{ propertyPath: 'amountTo', code: 'transfer.amount_out_of_range' }] }],
    ['form.date', { violations: [{ propertyPath: 'operationDate', code: 'common.date_not_blank' }] }],
    ['form.description', { violations: [{ propertyPath: 'description', code: 'common.description_max_length' }] }],
    ['transfer.to_account', { errorCode: 'transfer.accounts_must_differ' }],
    ['transfer.credit', { errorCode: 'transfer.amount_to_required_for_currency_exchange' }],
  ])('shows a refusal under %s', async (label, refusal) => {
    const user = userEvent.setup()
    const violations = (refusal.violations ?? [])
      .map((violation) => ({ ...violation, message: 'Refused.', params: {} }))
    const code = refusal.errorCode ?? refusal.violations?.[0]?.code ?? ''
    serve(ACCOUNTS, (method) => method === 'POST'
      ? Response.json({ errorCode: refusal.errorCode ?? 'validation.failed', violations }, { status: 422 })
      : undefined)
    showPage()
    // two currencies, so the second amount is there to refuse
    await user.selectOptions(await screen.findByLabelText(i18n.t('transfer.to_account')), 'Account 3 EUR')

    await save()

    const refused = field(label)
    await waitFor(() => { expect(refused).toHaveAccessibleDescription(i18n.t(code, { ns: 'validators' })) })
  })

  it('asks for an account when there is none to choose, without sending anything', async () => {
    const fetch = serve([account(4, 'PLN', true)])
    showPage()
    await screen.findByLabelText(i18n.t('transfer.from_account'))

    await save()

    expect(field('transfer.from_account'))
      .toHaveAccessibleDescription(i18n.t('transfer.from_account_required', { ns: 'validators' }))
    expect(fetch).not.toHaveBeenCalledWith('/api/transfers', expect.anything())
  })

  it('goes back without saving, opened first in the tab, to the home page', async () => {
    const fetch = serve()
    showPage()

    await userEvent.click(await screen.findByRole('button', { name: i18n.t('form.cancel') }))

    expect(screen.getByText('Page /')).toBeInTheDocument()
    expect(fetch).not.toHaveBeenCalledWith('/api/transfers', expect.anything())
  })
})
