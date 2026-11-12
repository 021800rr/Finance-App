import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { formatAmount } from '../money'
import { type Answer, fakeApi, sentJson } from '../test/fakeApi'
import { today } from '../ui/form'
import { RatesPage } from './RatesPage'

vi.mock('../ui/LineChart', () => import('../test/LineChartStandIn'))

const BANKS = [
  { id: 1, name: 'Bank CHF' }, { id: 2, name: 'Bank PLN' }, { id: 3, name: 'Bank hidden EUR' },
  { id: 4, name: 'Bank EUR' },
]

const account = (id: number, bankName: string, currencyCode: string, hidden = false) => ({
  id, bankName, currencyCode, hidden, accountNumber: `account-${id}`, typeName: 'ROR', householdName: 'Home',
  displayLabel: `Account ${id}`, isMain: false,
})

// Two banks take rates, CHF and EUR. The others do not: one has an account in PLN only, one a foreign one, but hidden.
const ACCOUNTS = [
  account(1, 'Bank CHF', 'CHF'), account(2, 'Bank PLN', 'PLN'), account(3, 'Bank hidden EUR', 'EUR', true),
  account(4, 'Bank EUR', 'EUR'),
]

type Rate = { id: number; bankId: number; currencyCode: string; rateDate: string; rateToPln: string }

const rate = (id: number, bankId: number, rateDate: string, rateToPln: string): Rate => (
  { id, bankId, currencyCode: 'CHF', rateDate, rateToPln }
)

// The API of the page: the banks, accounts and currencies, and the same rates for the chart and the list.
function serve(rates: Rate[], answer?: Answer) {
  return fakeApi({
    '/api/banks': BANKS,
    '/api/accounts': ACCOUNTS,
    '/api/currencies': [{ code: 'CHF' }, { code: 'EUR' }, { code: 'PLN' }],
    '/api/currency_exchange_rates/recent': rates,
    '/api/currency_exchange_rates/chart': rates,
  }, answer)
}

function showPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(<QueryClientProvider client={client}><RatesPage /></QueryClientProvider>)
}

const options = (label: string) => within(screen.getByLabelText(label)).getAllByRole('option')
  .map((option) => option.textContent)

afterEach(() => { vi.unstubAllGlobals() })

describe('RatesPage', () => {
  // A bank the list no longer has (deleted meanwhile, in another tab) is named by its id.
  it('names the banks in the chart and in the list', async () => {
    serve([rate(5, 1, '2026-11-10', '4.5590'), rate(4, 9, '2026-11-09', '4.6012')])
    showPage()

    const chart = await screen.findByRole('list', { name: 'chart' })
    expect(within(chart).getAllByRole('listitem').map((line) => line.textContent)).toEqual([
      'Bank CHF: {"2026-11-10":"4.5590"}',
      '#9: {"2026-11-09":"4.6012"}',
    ])
    const rows = within(await screen.findByRole('table')).getAllByRole('row').slice(1)
    expect(rows.map((row) => within(row).getAllByRole('cell').slice(0, 4).map((cell) => cell.textContent)))
      .toEqual([
        ['Bank CHF', 'CHF', '2026-11-10', formatAmount('4.5590')],
        ['#9', 'CHF', '2026-11-09', formatAmount('4.6012')],
      ])
  })

  it('says there are no rates yet, in the chart and in the list', async () => {
    serve([])
    showPage()

    await waitFor(() => { expect(screen.getAllByText(i18n.t('rate.none_found'))).toHaveLength(2) })
  })

  it('offers the banks with a visible account in a foreign currency, and every currency but PLN', async () => {
    serve([])
    showPage()

    expect(await screen.findByRole('button', { name: i18n.t('common.add') })).toBeInTheDocument()
    expect(options(i18n.t('account.bank'))).toEqual(['Bank CHF', 'Bank EUR'])
    expect(options(i18n.t('account.currency'))).toEqual(['CHF', 'EUR'])
  })

  it('adds a rate and starts a new, empty form', async () => {
    const user = userEvent.setup()
    const fetch = serve([], (method, path) => method === 'POST' && path === '/api/currency_exchange_rates'
      ? Response.json({ ...rate(6, 4, today(), '4.9870'), currencyCode: 'EUR' }, { status: 201 })
      : undefined)
    showPage()
    await user.selectOptions(await screen.findByLabelText(i18n.t('account.bank')), 'Bank EUR')
    await user.selectOptions(screen.getByLabelText(i18n.t('account.currency')), 'EUR')
    const value = screen.getByLabelText(i18n.t('rate.value'))
    fireEvent.change(value, { target: { value: '4,9870' } })
    expect(value).toHaveValue('4,9870')

    await user.click(screen.getByRole('button', { name: i18n.t('common.add') }))

    await waitFor(() => { expect(screen.getByLabelText(i18n.t('rate.value'))).toHaveValue('') })
    expect(sentJson(fetch, 'POST')).toEqual({ bankId: 4, currencyCode: 'EUR', rateDate: today(), rateToPln: '4.9870' })
    // the chart and the list read the rates again, the new one with them
    expect(fetch.mock.calls.filter(([path]) => path === '/api/currency_exchange_rates/recent').length)
      .toBeGreaterThan(1)
  })

  it.each([
    ['bankId', 'account.bank', 'currency_exchange_rate.bank_required'],
    ['currencyCode', 'account.currency', 'currency_exchange_rate.currency_required'],
    ['rateDate', 'form.date', 'common.date_not_blank'],
    ['rateToPln', 'rate.value', 'currency_exchange_rate.rate_not_blank'],
  ])('shows the refusal of %s under its own field', async (propertyPath, label, code) => {
    const user = userEvent.setup()
    serve([], (method) => method === 'POST'
      ? Response.json({
        errorCode: 'validation.failed', violations: [{ propertyPath, message: 'Refused.', code, params: {} }],
      }, { status: 422 })
      : undefined)
    showPage()

    await user.click(await screen.findByRole('button', { name: i18n.t('common.add') }))

    const field = screen.getByLabelText(i18n.t(label))
    await waitFor(() => { expect(field).toHaveAccessibleDescription(i18n.t(code, { ns: 'validators' })) })
  })

  it('deletes a rate once confirmed', async () => {
    const user = userEvent.setup()
    const fetch = serve([rate(5, 1, '2026-11-10', '4.5590')], (method) => method === 'DELETE'
      ? new Response(null, { status: 204 })
      : undefined)
    showPage()

    await user.click(await screen.findByRole('button', { name: i18n.t('operation.delete') }))
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: i18n.t('app.confirm') }))

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        '/api/currency_exchange_rates/5',
        expect.objectContaining({ method: 'DELETE' }),
      )
    })
  })

  it('says when a rate could not be deleted', async () => {
    const user = userEvent.setup()
    serve([rate(5, 1, '2026-11-10', '4.5590')], (method) => method === 'DELETE'
      ? Response.json({}, { status: 500 })
      : undefined)
    showPage()

    await user.click(await screen.findByRole('button', { name: i18n.t('operation.delete') }))
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: i18n.t('app.confirm') }))

    expect(await screen.findByText(i18n.t('app.delete_rate_failed'))).toBeInTheDocument()
  })
})
