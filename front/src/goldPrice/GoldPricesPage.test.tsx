import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { formatAmount } from '../money'
import { type Answer, fakeApi, sentJson } from '../test/fakeApi'
import { today } from '../ui/form'
import { GoldPricesPage } from './GoldPricesPage'

vi.mock('../ui/LineChart', () => import('../test/LineChartStandIn'))

const ASSETS = [
  { id: 1, formId: 1, formName: 'moneta', description: 'Krugerrand 1 oz', ounces: '1.0000' },
  { id: 2, formId: 2, formName: 'sztabka', description: 'Sztabka 100 g', ounces: '3.2151' },
]

type Price = { id: number; goldAssetId: number; priceDate: string; pricePln: string; currencyCode: string }

const price = (id: number, goldAssetId: number, priceDate: string, pricePln: string): Price => (
  { id, goldAssetId, priceDate, pricePln, currencyCode: 'PLN' }
)

// The API of the page: the assets, and the same prices for the chart and the list.
function serve(prices: Price[], answer?: Answer) {
  return fakeApi({
    '/api/gold_assets': ASSETS, '/api/gold_prices/recent': prices, '/api/gold_prices/chart': prices,
  }, answer)
}

function showPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(<QueryClientProvider client={client}><GoldPricesPage /></QueryClientProvider>)
}

afterEach(() => { vi.unstubAllGlobals() })

describe('GoldPricesPage', () => {
  // An asset deleted meanwhile (in another tab) is no longer on the list of assets: its id stands in for its name.
  it('names the assets in the chart and in the list', async () => {
    serve([price(5, 1, '2026-11-10', '8650.00'), price(4, 9, '2026-11-09', '420.50')])
    showPage()

    const chart = await screen.findByRole('list', { name: 'chart' })
    expect(within(chart).getAllByRole('listitem').map((line) => line.textContent)).toEqual([
      'Krugerrand 1 oz: {"2026-11-10":"8650.00"}',
      '#9: {"2026-11-09":"420.50"}',
    ])
    const rows = within(await screen.findByRole('table')).getAllByRole('row').slice(1)
    expect(rows.map((row) => within(row).getAllByRole('cell').slice(0, 4).map((cell) => cell.textContent)))
      .toEqual([
        ['5', 'Krugerrand 1 oz', '2026-11-10', formatAmount('8650.00')],
        ['4', '#9', '2026-11-09', formatAmount('420.50')],
      ])
  })

  it('says there are no prices yet, in the chart and in the list', async () => {
    serve([])
    showPage()

    await waitFor(() => { expect(screen.getAllByText(i18n.t('gold_price.none_found'))).toHaveLength(2) })
  })

  it('adds a price and starts a new, empty form', async () => {
    const user = userEvent.setup()
    const fetch = serve([], (method, path) => method === 'POST' && path === '/api/gold_prices'
      ? Response.json(price(6, 2, today(), '8650.00'), { status: 201 })
      : undefined)
    showPage()
    await user.selectOptions(await screen.findByLabelText(i18n.t('gold_price.asset')), 'Sztabka 100 g')
    const amount = screen.getByLabelText(i18n.t('gold_price.price_pln'))
    fireEvent.change(amount, { target: { value: '8650,00' } })
    expect(amount).toHaveValue('8650,00')

    await user.click(screen.getByRole('button', { name: i18n.t('common.add') }))

    await waitFor(() => { expect(screen.getByLabelText(i18n.t('gold_price.price_pln'))).toHaveValue('') })
    expect(sentJson(fetch, 'POST')).toEqual({ goldAssetId: 2, priceDate: today(), pricePln: '8650.00' })
    // the chart and the list read the prices again, the new one with them
    expect(fetch.mock.calls.filter(([path]) => path === '/api/gold_prices/recent').length).toBeGreaterThan(1)
  })

  it.each([
    ['goldAssetId', 'gold_price.asset', 'gold_price.asset_not_null'],
    ['priceDate', 'form.date', 'common.date_not_blank'],
    ['pricePln', 'gold_price.price_pln', 'gold_price.price_not_blank'],
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

  it('deletes a price once confirmed', async () => {
    const user = userEvent.setup()
    const fetch = serve([price(5, 1, '2026-11-10', '8650.00')], (method) => method === 'DELETE'
      ? new Response(null, { status: 204 })
      : undefined)
    showPage()

    await user.click(await screen.findByRole('button', { name: i18n.t('operation.delete') }))
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: i18n.t('app.confirm') }))

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith('/api/gold_prices/5', expect.objectContaining({ method: 'DELETE' }))
    })
  })

  it('says when a price could not be deleted', async () => {
    const user = userEvent.setup()
    serve([price(5, 1, '2026-11-10', '8650.00')], (method) => method === 'DELETE'
      ? Response.json({}, { status: 500 })
      : undefined)
    showPage()

    await user.click(await screen.findByRole('button', { name: i18n.t('operation.delete') }))
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: i18n.t('app.confirm') }))

    expect(await screen.findByText(i18n.t('app.delete_gold_price_failed'))).toBeInTheDocument()
  })
})
