import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { formatAmount } from '../money'
import { type Answer, fakeApi, sentJson } from '../test/fakeApi'
import { GoldAssetsPage } from './GoldAssetsPage'

const FORMS = [{ id: 1, name: 'moneta' }, { id: 2, name: 'sztabka' }]

type Asset = { id: number; formId: number; formName: string; description: string; ounces: string }

const KRUGERRAND: Asset = { id: 1, formId: 1, formName: 'moneta', description: 'Krugerrand 1 oz', ounces: '1.0000' }

// The API of the page: the gold forms and the assets.
function serve(assets: Asset[], answer?: Answer) {
  return fakeApi({ '/api/gold_forms': FORMS, '/api/gold_assets': assets }, answer)
}

function showPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <GoldAssetsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

afterEach(() => { vi.unstubAllGlobals() })

describe('GoldAssetsPage', () => {
  it('lists the assets, each with a link to its edit', async () => {
    serve([KRUGERRAND])
    showPage()

    const rows = within(await screen.findByRole('table')).getAllByRole('row').slice(1)
    expect(rows.map((row) => within(row).getAllByRole('cell').slice(0, 4).map((cell) => cell.textContent)))
      .toEqual([['1', 'moneta', formatAmount('1.0000'), 'Krugerrand 1 oz']])
    expect(screen.getByRole('link', { name: i18n.t('operation.edit') })).toHaveAttribute('href', '/gold-assets/1/edit')
  })

  it('says there are no assets yet', async () => {
    serve([])
    showPage()

    expect(await screen.findByText(i18n.t('gold_asset.none_found'))).toBeInTheDocument()
  })

  it('adds an asset and starts a new, empty form', async () => {
    const user = userEvent.setup()
    const fetch = serve([], (method, path) => method === 'POST' && path === '/api/gold_assets'
      ? Response.json({ id: 2, formId: 2, formName: 'sztabka', description: 'Sztabka 100 g', ounces: '3.2151' },
        { status: 201 })
      : undefined)
    showPage()
    await user.selectOptions(await screen.findByLabelText(i18n.t('gold_asset.shape')), 'sztabka')
    fireEvent.change(screen.getByLabelText(i18n.t('gold_asset.ounces')), { target: { value: '3,2151' } })
    await user.type(screen.getByLabelText(i18n.t('form.description')), 'Sztabka 100 g')

    await user.click(screen.getByRole('button', { name: i18n.t('common.add') }))

    await waitFor(() => { expect(screen.getByLabelText(i18n.t('form.description'))).toHaveValue('') })
    expect(screen.getByLabelText(i18n.t('gold_asset.ounces'))).toHaveValue('')
    expect(sentJson(fetch, 'POST')).toEqual({ formId: 2, ounces: '3.2151', description: 'Sztabka 100 g' })
    // the list reads the assets again, the new one with them
    expect(fetch.mock.calls.filter(([path]) => path === '/api/gold_assets').length).toBeGreaterThan(1)
  })

  it.each([
    ['formId', 'gold_asset.shape', 'gold_asset.form_not_null'],
    ['ounces', 'gold_asset.ounces', 'gold_asset.ounces_not_blank'],
    ['description', 'form.description', 'gold_asset.description_not_blank'],
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

  it('deletes an asset once confirmed', async () => {
    const user = userEvent.setup()
    const fetch = serve([KRUGERRAND], (method) => method === 'DELETE' ? new Response(null, { status: 204 }) : undefined)
    showPage()

    await user.click(await screen.findByRole('button', { name: i18n.t('operation.delete') }))
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: i18n.t('app.confirm') }))

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith('/api/gold_assets/1', expect.objectContaining({ method: 'DELETE' }))
    })
  })

  it('says when an asset could not be deleted', async () => {
    const user = userEvent.setup()
    serve([KRUGERRAND], (method) => method === 'DELETE' ? Response.json({}, { status: 500 }) : undefined)
    showPage()

    await user.click(await screen.findByRole('button', { name: i18n.t('operation.delete') }))
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: i18n.t('app.confirm') }))

    expect(await screen.findByText(i18n.t('app.delete_gold_asset_failed'))).toBeInTheDocument()
  })
})
